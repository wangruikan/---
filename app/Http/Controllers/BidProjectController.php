<?php

namespace App\Http\Controllers;

use App\Models\BidProject;
use App\Models\BidDocument;
use App\Models\BidProgressLog;
use App\Models\BidReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Traits\ChecksPermission;

class BidProjectController extends Controller
{
    use ChecksPermission;
    /**
     * 获取投标项目列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('bid_projects.view')) {
            return $response;
        }
        
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $query = $this->visibleBidProjectQuery($user, $accountSetId)
            ->with('creator:id,name');

        // 搜索条件
        if ($request->has('keyword') && $request->keyword) {
            $keyword = $request->keyword;
            $query->where(function($q) use ($keyword) {
                $q->where('project_name', 'like', "%{$keyword}%")
                  ->orWhere('project_code', 'like', "%{$keyword}%")
                  ->orWhere('client_name', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('project_category') && $request->project_category) {
            $query->where('project_category', $request->project_category);
        }

        if ($request->has('bid_result') && $request->bid_result) {
            $query->where('bid_result', $request->bid_result);
        }

        // 时间范围筛选
        if ($request->has('deadline_start') && $request->deadline_start) {
            $query->where('bid_deadline', '>=', $request->deadline_start);
        }

        if ($request->has('deadline_end') && $request->deadline_end) {
            $query->where('bid_deadline', '<=', $request->deadline_end);
        }

        // 排序
        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // 分页
        $pageSize = $request->input('page_size', 15);
        $projects = $query->paginate($pageSize);

        // 添加额外信息
        $projects->getCollection()->transform(function ($project) {
            $project->status_text = BidProject::getStatusText($project->status);
            $project->result_text = BidProject::getResultText($project->bid_result);
            $project->is_deadline_approaching = $project->isDeadlineApproaching();
            $project->is_overdue = $project->isOverdue();
            $project->document_count = $project->documents()->count();
            $project->progress_log_count = $project->progressLogs()->count();
            return $project;
        });

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    /**
     * 获取项目详情
     */
    public function show($id, Request $request)
    {
        if ($response = $this->checkPermission('bid_projects.view')) {
            return $response;
        }
        
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $project = $this->visibleBidProjectQuery($user, $accountSetId)
            ->where('id', $id)
            ->with(['documents', 'progressLogs' => function($query) {
                $query->orderBy('log_time', 'desc');
            }])
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        $project->status_text = BidProject::getStatusText($project->status);
        $project->result_text = BidProject::getResultText($project->bid_result);
        $project->is_deadline_approaching = $project->isDeadlineApproaching();
        $project->is_overdue = $project->isOverdue();

        return response()->json([
            'success' => true,
            'data' => $project
        ]);
    }

    /**
     * 创建投标项目
     */
    public function store(Request $request)
    {
        if ($response = $this->checkPermission('bid_projects.create')) {
            return $response;
        }
        
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $validator = Validator::make($request->all(), [
            'project_code' => 'required|string|max:50',
            'project_name' => 'required|string|max:200',
            'project_category' => 'nullable|string|max:50',
            'client_name' => 'nullable|string|max:200',
            'client_contact' => 'nullable|string|max:100',
            'client_phone' => 'nullable|string|max:20',
            'project_budget' => 'nullable|numeric|min:0',
            'bid_bond' => 'nullable|numeric|min:0',
            'bond_paid_at' => 'nullable|date',
            'project_location' => 'nullable|string|max:200',
            'project_scale' => 'nullable|string',
            'service_period' => 'nullable|string|max:100',
            'bid_deadline' => 'nullable|date',
            'bid_opening_time' => 'nullable|date',
            'bid_method' => 'nullable|string|max:50',
            'information_source' => 'nullable|string|max:100',
            'responsible_person' => 'nullable|string|max:100',
            'responsible_department' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $codeExists = BidProject::where('account_set_id', $accountSetId)
            ->where('project_code', $request->project_code)
            ->exists();
        if ($codeExists) {
            return response()->json([
                'success' => false,
                'message' => '项目编号已存在'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $project = BidProject::create(array_merge($request->all(), [
                'account_set_id' => $accountSetId,
                'status' => BidProject::STATUS_PREPARING,
                'created_by' => $user->id,
            ]));

            // 创建初始进度记录
            BidProgressLog::create([
                'bid_project_id' => $project->id,
                'log_type' => BidProgressLog::TYPE_STATUS_CHANGE,
                'log_title' => '项目创建',
                'log_content' => '投标项目创建成功',
                'new_status' => BidProject::STATUS_PREPARING,
                'log_time' => now(),
                'operator_id' => $user->id,
                'operator_name' => $user->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '项目创建成功',
                'data' => $project
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建投标项目失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '创建失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新投标项目
     */
    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('bid_projects.edit')) {
            return $response;
        }
        
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $project = $this->visibleBidProjectQuery($user, $accountSetId)
            ->where('id', $id)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'project_code' => 'required|string|max:50',
            'project_name' => 'required|string|max:200',
            'project_category' => 'nullable|string|max:50',
            'client_name' => 'nullable|string|max:200',
            'client_contact' => 'nullable|string|max:100',
            'client_phone' => 'nullable|string|max:20',
            'project_budget' => 'nullable|numeric|min:0',
            'bid_bond' => 'nullable|numeric|min:0',
            'bond_paid_at' => 'nullable|date',
            'bond_refunded_at' => 'nullable|date',
            'project_location' => 'nullable|string|max:200',
            'project_scale' => 'nullable|string',
            'service_period' => 'nullable|string|max:100',
            'bid_deadline' => 'nullable|date',
            'bid_opening_time' => 'nullable|date',
            'bid_method' => 'nullable|string|max:50',
            'information_source' => 'nullable|string|max:100',
            'responsible_person' => 'nullable|string|max:100',
            'responsible_department' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $codeExists = BidProject::where('account_set_id', $accountSetId)
            ->where('project_code', $request->project_code)
            ->where('id', '!=', $project->id)
            ->exists();
        if ($codeExists) {
            return response()->json([
                'success' => false,
                'message' => '项目编号已存在'
            ], 422);
        }

        try {
            $project->update(array_merge($request->all(), [
                'updated_by' => $user->id,
            ]));

            return response()->json([
                'success' => true,
                'message' => '项目更新成功',
                'data' => $project
            ]);
        } catch (\Exception $e) {
            \Log::error('更新投标项目失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '更新失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除投标项目
     */
    public function destroy($id, Request $request)
    {
        if ($response = $this->checkPermission('bid_projects.delete')) {
            return $response;
        }
        
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $project = $this->visibleBidProjectQuery($user, $accountSetId)
            ->where('id', $id)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // 删除关联文件
            $documents = $project->documents;
            foreach ($documents as $document) {
                if ($document->file_path && Storage::exists($document->file_path)) {
                    Storage::delete($document->file_path);
                }
                $document->delete();
            }

            // 删除进度记录
            $project->progressLogs()->delete();

            // 删除提醒
            $project->reminders()->delete();

            // 删除项目
            $project->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '项目删除成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('删除投标项目失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新项目状态
     */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:preparing,submitted,opened,evaluating,won,lost,abandoned,contracted,completed,cancelled',
            'lost_reason' => 'nullable|string|in:废标,流标,未列首标',
            'attachment' => 'nullable|file|max:51200',
            'remarks' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->status !== BidProject::STATUS_LOST) {
                return;
            }

            if (!$request->lost_reason) {
                $validator->errors()->add('lost_reason', '请选择未中标原因');
                return;
            }

            if (in_array($request->lost_reason, ['流标', '未列首标'], true) && !$request->hasFile('attachment')) {
                $validator->errors()->add('attachment', '请上传未中标附件');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = $this->visibleBidProjectQuery($user, $accountSetId)
            ->where('id', $id)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $project->status;
            $newStatus = $request->status;

            $updateData = ['status' => $newStatus];
            if ($newStatus === BidProject::STATUS_LOST) {
                $updateData['bid_result'] = BidProject::RESULT_LOST;
                $updateData['lost_reason'] = $request->lost_reason;
            }

            $project->update($updateData);

            $attachment = null;
            if ($newStatus === BidProject::STATUS_LOST && $request->hasFile('attachment')) {
                $attachment = $this->storeBidDocumentFromUpload(
                    $request,
                    $project,
                    BidDocument::TYPE_LOST_REASON_ATTACHMENT,
                    '未中标附件-' . $request->lost_reason,
                    $request->remarks
                );
            }

            // 记录状态变更
            BidProgressLog::create([
                'bid_project_id' => $project->id,
                'log_type' => BidProgressLog::TYPE_STATUS_CHANGE,
                'log_title' => '状态变更',
                'log_content' => $request->remarks
                    ?? '项目状态由【' . BidProject::getStatusText($oldStatus) . '】变更为【' . BidProject::getStatusText($newStatus) . '】'
                        . ($request->lost_reason ? '，未中标原因：' . $request->lost_reason : '')
                        . ($attachment ? '，已上传附件：' . $attachment->document_name : ''),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'log_time' => now(),
                'operator_id' => $user->id,
                'operator_name' => $user->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '状态更新成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('更新项目状态失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '更新失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 设置投标结果
     */
    public function setBidResult(Request $request, $id)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $validator = Validator::make($request->all(), [
            'bid_result' => 'required|string|in:won,lost,abandoned',
            'lost_reason' => 'nullable|string|in:废标,流标,未列首标',
            'win_amount' => 'nullable|numeric|min:0',
            'win_date' => 'nullable|date',
            'attachment' => 'nullable|file|max:51200',
            'remarks' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->bid_result !== BidProject::RESULT_LOST) {
                return;
            }

            if (!$request->lost_reason) {
                $validator->errors()->add('lost_reason', '请选择未中标原因');
                return;
            }

            if (in_array($request->lost_reason, ['流标', '未列首标'], true) && !$request->hasFile('attachment')) {
                $validator->errors()->add('attachment', '请上传未中标附件');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = $this->visibleBidProjectQuery($user, $accountSetId)
            ->where('id', $id)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $updateData = [
                'bid_result' => $request->bid_result,
            ];
            $oldStatus = $project->status;

            // 根据结果更新状态
            if ($request->bid_result === 'won') {
                $updateData['status'] = BidProject::STATUS_WON;
                $updateData['win_amount'] = $request->win_amount;
                $updateData['win_date'] = $request->win_date ?? now()->toDateString();
                $updateData['lost_reason'] = null;
            } elseif ($request->bid_result === 'lost') {
                $updateData['status'] = BidProject::STATUS_LOST;
                $updateData['lost_reason'] = $request->lost_reason;
            } else {
                $updateData['status'] = BidProject::STATUS_ABANDONED;
                $updateData['lost_reason'] = null;
            }

            $project->update($updateData);

            $attachment = null;
            if ($request->bid_result === BidProject::RESULT_LOST && $request->hasFile('attachment')) {
                $attachment = $this->storeBidDocumentFromUpload(
                    $request,
                    $project,
                    BidDocument::TYPE_LOST_REASON_ATTACHMENT,
                    '未中标附件-' . $request->lost_reason,
                    $request->remarks
                );
            }

            // 记录日志
            BidProgressLog::create([
                'bid_project_id' => $project->id,
                'log_type' => BidProgressLog::TYPE_STATUS_CHANGE,
                'log_title' => '投标结果确定',
                'log_content' => $request->remarks
                    ?? '投标结果：' . BidProject::getResultText($request->bid_result)
                        . ($request->lost_reason ? '，未中标原因：' . $request->lost_reason : '')
                        . ($attachment ? '，已上传附件：' . $attachment->document_name : ''),
                'old_status' => $oldStatus,
                'new_status' => $updateData['status'],
                'log_time' => now(),
                'operator_id' => $user->id,
                'operator_name' => $user->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '投标结果设置成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('设置投标结果失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '设置失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上传文件
     */
    public function uploadDocument(Request $request, $id)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string',
            'document_name' => 'required|string|max:200',
            'file' => 'required|file|max:51200', // 最大50MB
            'version' => 'nullable|string|max:20',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = $this->visibleBidProjectQuery($user, $accountSetId)
            ->where('id', $id)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $document = $this->storeBidDocumentFromUpload(
                $request,
                $project,
                $request->document_type,
                $request->document_name,
                $request->remarks,
                $request->version ?? '1.0'
            );

            // 记录日志
            BidProgressLog::create([
                'bid_project_id' => $id,
                'log_type' => BidProgressLog::TYPE_DOCUMENT_UPLOAD,
                'log_title' => '文档上传',
                'log_content' => '上传文档：' . $request->document_name . '（' . BidDocument::getTypeText($request->document_type) . '）',
                'log_time' => now(),
                'operator_id' => $user->id,
                'operator_name' => $user->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '文件上传成功',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('上传文档失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除文件
     */
    public function deleteDocument($projectId, $documentId, Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $project = $this->visibleBidProjectQuery($user, $accountSetId)
            ->where('id', $projectId)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        $document = BidDocument::where('id', $documentId)
            ->where('bid_project_id', $projectId)
            ->first();

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => '文档不存在'
            ], 404);
        }

        try {
            // 删除文件
            if ($document->file_path && Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }

            $document->delete();

            return response()->json([
                'success' => true,
                'message' => '文档删除成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('删除文档失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 添加进度记录
     */
    public function addProgressLog(Request $request, $id)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $validator = Validator::make($request->all(), [
            'log_type' => 'required|string',
            'log_title' => 'required|string|max:200',
            'log_content' => 'nullable|string',
            'log_time' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = $this->visibleBidProjectQuery($user, $accountSetId)
            ->where('id', $id)
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        try {
            $log = BidProgressLog::create([
                'bid_project_id' => $id,
                'log_type' => $request->log_type,
                'log_title' => $request->log_title,
                'log_content' => $request->log_content,
                'log_time' => $request->log_time ?? now(),
                'operator_id' => $user->id,
                'operator_name' => $user->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => '记录添加成功',
                'data' => $log
            ]);
        } catch (\Exception $e) {
            \Log::error('添加进度记录失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '添加失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取统计数据
     */
    public function statistics(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        \Log::info('投标项目统计', [
            'account_set_id' => $accountSetId,
            'user_id' => $user->id,
        ]);

        // 统计各种状态的数量
        $stats = [
            'total' => $this->visibleBidProjectQuery($user, $accountSetId)->count(),
            'preparing' => $this->visibleBidProjectQuery($user, $accountSetId)->where('status', BidProject::STATUS_PREPARING)->count(),
            'submitted' => $this->visibleBidProjectQuery($user, $accountSetId)->where('status', BidProject::STATUS_SUBMITTED)->count(),
            // 中标：可能status是won，或者bid_result是won
            'won' => $this->visibleBidProjectQuery($user, $accountSetId)
                ->where(function($query) {
                    $query->where('status', BidProject::STATUS_WON)
                          ->orWhere('bid_result', BidProject::RESULT_WON);
                })
                ->count(),
            'lost' => $this->visibleBidProjectQuery($user, $accountSetId)
                ->where(function($query) {
                    $query->where('status', BidProject::STATUS_LOST)
                          ->orWhere('bid_result', BidProject::RESULT_LOST);
                })
                ->count(),
            'deadline_approaching' => 0,
            'overdue' => 0,
        ];

        // 计算即将到期和已过期
        $projects = $this->visibleBidProjectQuery($user, $accountSetId)
            ->whereNotNull('bid_deadline')
            ->get();

        \Log::info('投标项目统计 - 检查截止时间', [
            'projects_count' => $projects->count(),
        ]);

        foreach ($projects as $project) {
            $isApproaching = $project->isDeadlineApproaching();
            $isOverdue = $project->isOverdue();
            
            \Log::info('项目截止时间检查', [
                'project_id' => $project->id,
                'project_name' => $project->project_name,
                'bid_deadline' => $project->bid_deadline,
                'is_approaching' => $isApproaching,
                'is_overdue' => $isOverdue,
            ]);
            
            if ($isApproaching) {
                $stats['deadline_approaching']++;
            }
            if ($isOverdue) {
                $stats['overdue']++;
            }
        }

        \Log::info('投标项目统计结果', $stats);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * 获取项目类别列表
     */
    public function categories(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $categories = $this->visibleBidProjectQuery($user, $accountSetId)
            ->whereNotNull('project_category')
            ->distinct()
            ->pluck('project_category');

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    private function visibleBidProjectQuery($user, $accountSetId)
    {
        $query = BidProject::where('account_set_id', $accountSetId);

        if (!$this->canViewAllBidProjects($user)) {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    private function canViewAllBidProjects($user): bool
    {
        return $user && in_array($user->role, ['super_admin', 'admin', 'manager', 'senior_manager'], true);
    }

    private function storeBidDocumentFromUpload(
        Request $request,
        BidProject $project,
        string $documentType,
        string $documentName,
        ?string $remarks = null,
        string $version = '1.0'
    ): BidDocument {
        $file = $request->file('file') ?: $request->file('attachment');
        $filePath = $file->store('bid_documents/' . $project->id, 'public');

        return BidDocument::create([
            'bid_project_id' => $project->id,
            'document_type' => $documentType,
            'document_name' => $documentName,
            'file_path' => $filePath,
            'file_size' => $file->getSize(),
            'file_type' => $file->getClientOriginalExtension(),
            'upload_by' => $request->user()?->id ?? Auth::id(),
            'upload_at' => now(),
            'version' => $version,
            'remarks' => $remarks,
        ]);
    }
}

