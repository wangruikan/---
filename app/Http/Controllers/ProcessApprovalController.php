<?php

namespace App\Http\Controllers;

use App\Models\ProcessApproval;
use App\Models\ProcessAttachment;
use App\Models\Project;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use App\Traits\ChecksPermission;

class ProcessApprovalController extends Controller
{
    use ChecksPermission;
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    private function getAccountSetId(Request $request)
    {
        return $request->header('X-Account-Set-Id')
            ?: $request->input('current_account_set_id')
            ?: $request->user()?->account_set_id;
    }

    private function projectCanCreateSummaryForMonth(Project $project, string $month): bool
    {
        $startMonth = $project->start_date ? Carbon::parse($project->start_date)->format('Y-m') : null;
        $endMonth = $project->end_date ? Carbon::parse($project->end_date)->format('Y-m') : null;

        if ($startMonth && $month < $startMonth) {
            return false;
        }

        if ($endMonth && $month > $endMonth) {
            return false;
        }

        return true;
    }

    private function buildSummaryTitle(string $projectName, string $month, string $categoryLabel): string
    {
        return "{$projectName} {$month} {$categoryLabel}";
    }
    /**
     * 获取流程列表
     */
    public function index(Request $request)
    {
        $isFileStampList = $request->input('category') === 'file_stamp';

        if (!$isFileStampList) {
            // 汇总申请查看权限
            if ($response = $this->checkPermission('process_approval.view')) {
                return $response;
            }
        }
        // 从请求参数中获取账套ID
        $accountSetId = $request->input('current_account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套'
            ], 400);
        }
        
        $query = ProcessApproval::with(['initiator', 'approvalInstance.records', 'attachments'])
            ->where('account_set_id', $accountSetId);

        // 按类型筛选
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        } else {
            $query->where('category', '!=', 'file_stamp');
        }

        // 按月份筛选
        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        // 按状态筛选
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $processes = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // 添加 has_payment_request 字段
        $processes->getCollection()->transform(function ($process) {
            // 已驳回的付款申请不占用“发起付款”入口，允许从汇总申请重新发起。
            $paymentRequest = \App\Models\PaymentRequest::where('insurance_summary_id', $process->id)
                ->where('payment_type', 'insurance')
                ->orderByDesc('id')
                ->first();
            $process->has_payment_request = $paymentRequest && $paymentRequest->status !== 'rejected';
            $process->payment_request_status = $paymentRequest?->status;

            $pendingRecord = $process->approvalInstance?->records?->firstWhere('status', 'pending');
            $process->current_approver_name = $pendingRecord?->approver_name
                ?? $process->approvalInstance?->records?->first()?->approver_name
                ?? '-';

            return $process;
        });

        return response()->json([
            'success' => true,
            'data' => $processes
        ]);
    }

    /**
     * 获取待发起汇总任务列表
     */
    public function getPendingProjects(Request $request)
    {
        if ($response = $this->checkPermission('process_approval.view')) {
            return $response;
        }

        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $accountSetId = $this->getAccountSetId($request);

        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套'
            ], 400);
        }

        $month = $request->input('month');

        $existingKeys = [];
        $existingProcesses = ProcessApproval::where('account_set_id', $accountSetId)
            ->whereIn('category', ['social_insurance', 'housing_fund'])
            ->where('month', $month)
            ->get(['category', 'project_ids']);

        foreach ($existingProcesses as $process) {
            $projectIds = is_array($process->project_ids) ? $process->project_ids : [];
            foreach ($projectIds as $projectId) {
                $existingKeys[$projectId . ':' . $process->category] = true;
            }
        }

        $categoryMap = [
            'social_insurance' => '社保汇总',
            'housing_fund' => '公积金汇总',
        ];

        $projects = Project::where('account_set_id', $accountSetId)
            ->where('status', 'active')
            ->select('id', 'name', 'code', 'start_date', 'end_date')
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $project) => $this->projectCanCreateSummaryForMonth($project, $month));

        $tasks = $projects->flatMap(function (Project $project) use ($categoryMap, $existingKeys, $month) {
            return collect($categoryMap)
                ->reject(function ($label, $category) use ($project, $existingKeys) {
                    return isset($existingKeys[$project->id . ':' . $category]);
                })
                ->map(function ($label, $category) use ($project, $month) {
                    return [
                        'task_key' => "{$project->id}_{$category}_{$month}",
                        'project_id' => $project->id,
                        'name' => $project->name,
                        'code' => $project->code,
                        'month' => $month,
                        'category' => $category,
                        'category_label' => $label,
                        'title' => $this->buildSummaryTitle($project->name, $month, $label),
                        'can_create' => true,
                        'disabled_reason' => null,
                    ];
                })
                ->values();
        })->values();

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'count' => $tasks->count(),
        ]);
    }

    /**
     * 获取流程详情
     */
    public function show(Request $request, $id)
    {
        // 汇总申请查看权限
        if ($response = $this->checkPermission('process_approval.view_details')) {
            return $response;
        }
        $process = ProcessApproval::with(['initiator', 'approvalInstance.records.approver', 'attachments.uploader'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $process
        ]);
    }

    /**
     * 创建流程
     */
    public function store(Request $request)
    {
        if ($request->input('category') !== 'file_stamp') {
            // 汇总申请创建权限
            if ($response = $this->checkPermission('process_approval.create')) {
                return $response;
            }
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'month' => 'nullable|date_format:Y-m',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:projects,id',
            'description' => 'nullable|string',
        ]);

        // 从请求参数中获取账套ID
        $accountSetId = $this->getAccountSetId($request);
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $process = ProcessApproval::create([
                'account_set_id' => $accountSetId,
                'initiator_id' => $request->user()->id,
                'title' => $request->title,
                'category' => $request->category ?? 'social_insurance', // 汇总类型：social_insurance=社保, housing_fund=公积金
                'month' => $request->input('month') ?: now()->format('Y-m'),
                'project_ids' => $request->project_ids ?? [],
                'description' => $request->description,
                'status' => 'draft',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '流程创建成功',
                'data' => $process->load(['initiator', 'attachments'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '流程创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request, $id)
    {
        $process = ProcessApproval::findOrFail($id);

        if ($process->category === 'file_stamp') {
            if ((int) $process->initiator_id !== (int) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => '只能上传本人发起的盖章文件'
                ], 403);
            }
        } else {
            // 汇总申请编辑权限
            if ($response = $this->checkPermission('process_approval.edit')) {
                return $response;
            }
        }

        if (!in_array($process->status, ['draft', 'rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => '只有草稿或已驳回状态才能上传附件'
            ], 400);
        }

        $request->validate([
            'file' => 'required|file|max:51200', // Max 50MB
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;
            
            // 在移动文件之前获取文件信息
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            // 保存文件到 public 目录
            $directory = public_path('process_approvals/' . $id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'process_approvals/' . $id . '/' . $filename; // Relative path for database

            // 创建附件记录
            $attachment = ProcessAttachment::create([
                'process_approval_id' => $id,
                'filename' => $originalName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => $attachment->load('uploader')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '附件上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除附件
     */
    public function deleteAttachment(Request $request, $id, $attachmentId)
    {
        // 汇总申请编辑权限
        if ($response = $this->checkPermission('process_approval.edit')) {
            return $response;
        }
        $process = ProcessApproval::findOrFail($id);

        if (!in_array($process->status, ['draft', 'rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => '只有草稿或已驳回状态才能删除附件'
            ], 400);
        }

        $attachment = ProcessAttachment::where('process_approval_id', $id)
            ->where('id', $attachmentId)
            ->firstOrFail();

        try {
            // 删除文件
            $filePath = public_path($attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // 删除记录
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '附件删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '附件删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 下载附件
     */
    public function downloadAttachment(Request $request, $id, $attachmentId)
    {
        try {
            // 兼容公开下载路由：
            // 若请求已带登录态则继续校验权限；未带登录态时允许按附件ID下载
            if ($request->user()) {
                if ($response = $this->checkPermission('process_approval.view_details')) {
                    return $response;
                }
            }

            // 验证流程ID有效性
            if (!$id || $id === 'undefined' || $id === 'null' || !is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => '流程ID无效: ' . var_export($id, true)
                ], 400);
            }

            $attachment = ProcessAttachment::where('process_approval_id', $id)
                ->where('id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => '附件不存在或已被删除'
                ], 404);
            }

            $filePath = public_path($attachment->file_path);

            if (!file_exists($filePath)) {
                \Log::error('下载附件失败：文件不存在', [
                    'attachment_id' => $attachmentId,
                    'process_id' => $id,
                    'file_path' => $filePath,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => '文件不存在'
                ], 404);
            }

            $downloadName = $attachment->filename ?: basename($filePath);
            $downloadName = trim(str_replace(['/', '\\'], '-', $downloadName));
            $downloadName = preg_replace('/[\x00-\x1F\x7F]/u', '', $downloadName);
            if ($downloadName === '') {
                $downloadName = 'attachment_' . $attachmentId . '.pdf';
            }

            \Log::info('开始下载附件', [
                'attachment_id' => $attachmentId,
                'process_id' => $id,
                'download_name' => $downloadName,
            ]);

            return response()->download($filePath, $downloadName);
        } catch (\Exception $e) {
            \Log::error('下载附件失败', [
                'error' => $e->getMessage(),
                'attachment_id' => $attachmentId,
                'process_id' => $id,
            ]);
            return response()->json([
                'success' => false,
                'message' => '下载失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交流程（发起审批）
     */
    public function submit(Request $request, $id)
    {
        $request->validate([
            'stamp_method' => 'nullable|in:online,offline',
            'stamp_selection_mode' => 'nullable|in:stamp,none',
            'stamp_company' => 'nullable|string|max:100',
            'stamp_type' => 'nullable|in:bank,cash,official,finance,contract,legal_person,business,hr',
            'stamp_id' => 'nullable|integer',
        ]);

        $process = ProcessApproval::with('attachments')->findOrFail($id);

        if ($process->category === 'file_stamp') {
            if ((int) $process->initiator_id !== (int) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => '只能提交本人发起的盖章申请'
                ], 403);
            }
        } else {
            // 汇总申请提交权限
            if ($response = $this->checkPermission('process_approval.submit')) {
                return $response;
            }
        }

        if (!in_array($process->status, ['draft', 'rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => '只有草稿或已驳回状态才能提交'
            ], 400);
        }

        // 验证是否有附件
        if ($process->attachments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '请至少上传一个附件'
            ], 400);
        }

        try {
            // 准备附件数组
            $attachments = $process->attachments->map(function ($attachment) {
                return [
                    'path' => $attachment->file_path,
                    'name' => $attachment->filename,
                    'size' => $attachment->file_size,
                    'type' => $attachment->mime_type,
                ];
            })->toArray();

            // 获取盖章方式，默认线上
            $stampMethod = $request->input('stamp_method', 'online');
            $stampOptions = [
                'stamp_selection_mode' => $request->input('stamp_selection_mode', 'none'),
                'stamp_company' => $request->input('stamp_company'),
                'stamp_type' => $request->input('stamp_type'),
                'stamp_id' => $request->input('stamp_id'),
            ];
            if ($stampOptions['stamp_selection_mode'] === 'stamp') {
                $stamp = \App\Models\UserBankStamp::where('id', $stampOptions['stamp_id'])
                    ->where('account_set_id', $process->account_set_id)
                    ->where('company', $stampOptions['stamp_company'])
                    ->where('type', $stampOptions['stamp_type'])
                    ->first();

                if (!$stamp) {
                    return response()->json([
                        'success' => false,
                        'message' => '所选公司印章不存在，请重新选择'
                    ], 422);
                }
            }

            $businessType = $process->category === 'file_stamp' ? '文件盖章' : '保险汇总';

            // 使用审批服务创建审批实例（跳过发起人审批）
            $instance = $this->approvalService->createApprovalInstance(
                $process->account_set_id,
                $businessType,
                $process->id,        // 业务ID：流程ID
                $request->user()->id,
                $attachments,
                true, // 跳过发起人审批
                $stampMethod, // 盖章方式
                $stampOptions
            );

            // 更新流程状态
            $process->update([
                'status' => 'pending',
                'approval_instance_id' => $instance->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '流程提交成功，已进入审批流程',
                'data' => $process->load(['initiator', 'attachments', 'approvalInstance'])
            ]);
        } catch (\Exception $e) {
            Log::error('流程提交失败', [
                'process_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '流程提交失败: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * 删除流程
     */
    public function destroy(Request $request, $id)
    {
        $process = ProcessApproval::findOrFail($id);

        if ($process->category === 'file_stamp') {
            if ((int) $process->initiator_id !== (int) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => '只能删除本人发起的盖章申请'
                ], 403);
            }
        } else {
            // 汇总申请删除权限
            if ($response = $this->checkPermission('process_approval.delete')) {
                return $response;
            }
        }

        // 只有草稿或已驳回的流程才能删除
        if (!in_array($process->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => '只有草稿或已驳回的流程才能删除'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // 删除所有附件文件
            foreach ($process->attachments as $attachment) {
                $filePath = public_path($attachment->file_path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // 删除流程（软删除）
            $process->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '流程删除成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '流程删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 撤回审批（仅第一步审批前可撤回）
     */
    public function withdraw(Request $request, $id)
    {
        // 汇总申请撤回权限
        if ($response = $this->checkPermission('process_approval.withdraw')) {
            return $response;
        }

        $process = ProcessApproval::with('approvalInstance')->findOrFail($id);

        // 验证是否是发起人
        if ($process->initiator_id != $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => '只有发起人才能撤回审批'
            ], 403);
        }

        // 验证状态
        if ($process->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待审批状态才能撤回'
            ], 400);
        }

        if (!$process->approval_instance_id) {
            return response()->json([
                'success' => false,
                'message' => '未找到审批实例'
            ], 400);
        }

        try {
            // 调用审批服务撤回
            $this->approvalService->withdraw(
                $process->approval_instance_id,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => '审批已撤回'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
