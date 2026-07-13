<?php

namespace App\Http\Controllers;

use App\Models\BasisRecord;
use App\Models\BasisAttachment;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Traits\ChecksPermission;
use App\Services\DynamicScheduledTaskService;

class BasisRecordController extends Controller
{
    use ChecksPermission;
    /**
     * 获取依据列表
     */
    public function index(Request $request)
    {
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');
        $type = $request->input('type'); // 'attendance' or 'salary'
        app(DynamicScheduledTaskService::class)->syncBasisTasksForReferenceMonth(
            $accountSetId,
            $request->input('month')
        );
        
        $query = BasisRecord::with(['project', 'creator', 'attachments'])
            ->where('account_set_id', $accountSetId);
        
        if ($type) {
            $query->where('type', $type);
        }
        
        // 筛选条件
        if ($request->has('project_id') && $request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->has('month') && $request->month) {
            $this->applyProcessingMonthFilter(
                $query,
                (int) $accountSetId,
                $request->month,
                $request->input('project_id')
            );
        }
        
        $records = $query->orderBy('month', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'total' => $records->total(),
            'current_page' => $records->currentPage(),
            'per_page' => $records->perPage(),
        ]);
    }

    /**
     * 获取可选择的项目列表（根据依据类型过滤）
     */
    public function getAvailableProjects(Request $request)
    {
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');
        $type = $request->input('type'); // 'attendance' or 'salary'
        
        $query = Project::where('account_set_id', $accountSetId)
            ->where('status', 'active');
        
        // 根据依据类型筛选项目
        if ($type === 'attendance') {
            $query->where('requires_attendance_basis', true);
        } elseif ($type === 'salary') {
            $query->where('requires_salary_basis', true);
        }
        
        $projects = $query->get(['id', 'name', 'requires_attendance_basis', 'requires_salary_basis']);
        
        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    /**
     * 创建依据
     */
    public function store(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'type' => 'required|in:attendance,salary',
            'month' => 'required|date_format:Y-m',
            'description' => 'nullable|string',
        ]);
        
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');
        
        // 验证项目是否设置了需要上传对应类型的依据
        $project = Project::findOrFail($request->project_id);
        if ($request->type === 'attendance' && !$project->requires_attendance_basis) {
            return response()->json([
                'success' => false,
                'message' => '该项目未设置需要上传考勤依据'
            ], 400);
        }
        
        if ($request->type === 'salary' && !$project->requires_salary_basis) {
            return response()->json([
                'success' => false,
                'message' => '该项目未设置需要上传工资依据'
            ], 400);
        }

        $basisMonth = $project->resolveBasisMonth($request->month);
        if (!$project->isPayrollBusinessMonthAvailable($basisMonth, $request->month)) {
            return response()->json([
                'success' => false,
                'message' => '该处理月份没有可创建的依据任务'
            ], 422);
        }
        
        // 检查是否已存在相同项目、月份、类型的依据
        $existing = BasisRecord::where('project_id', $request->project_id)
            ->where('month', $basisMonth)
            ->where('type', $request->type)
            ->first();
        
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => '该项目该月份的' . ($request->type === 'attendance' ? '考勤' : '工资') . '依据已存在'
            ], 400);
        }
        
        $record = BasisRecord::create([
            'account_set_id' => $accountSetId,
            'project_id' => $request->project_id,
            'type' => $request->type,
            'month' => $basisMonth,
            'description' => $request->description,
            'created_by' => Auth::id(),
        ]);
        
        // 检查并完成待办任务
        if ($request->type === 'salary') {
            \App\Services\PendingTaskService::checkAndCompleteSalaryBasisTask($record);
        } elseif ($request->type === 'attendance') {
            \App\Services\PendingTaskService::checkAndCompleteAttendanceBasisTask($record);
        }
        
        return response()->json([
            'success' => true,
            'message' => '创建成功',
            'data' => $record->load(['project', 'creator', 'attachments'])
        ]);
    }

    /**
     * 上传附件
     */
    public function copyLastMonth(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'type' => 'required|in:attendance,salary',
            'month' => 'required|date_format:Y-m',
            'description' => 'nullable|string',
            'month_is_basis' => 'nullable|boolean',
        ]);

        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');
        $project = Project::findOrFail($request->project_id);

        if ($request->type === 'attendance' && !$project->requires_attendance_basis) {
            return response()->json([
                'success' => false,
                'message' => '该项目未设置需要上传考勤依据'
            ], 400);
        }

        if ($request->type === 'salary' && !$project->requires_salary_basis) {
            return response()->json([
                'success' => false,
                'message' => '该项目未设置需要上传工资依据'
            ], 400);
        }

        $basisMonth = $request->boolean('month_is_basis')
            ? $request->month
            : $project->resolveBasisMonth($request->month);
        $processingMonth = $request->boolean('month_is_basis')
            ? $project->resolveBasisProcessingMonth($basisMonth)
            : $request->month;

        if (!$project->isPayrollBusinessMonthAvailable($basisMonth, $processingMonth)) {
            return response()->json([
                'success' => false,
                'message' => '该月份没有可复制的依据任务'
            ], 422);
        }

        $existing = BasisRecord::where('account_set_id', $accountSetId)
            ->where('project_id', $request->project_id)
            ->where('month', $basisMonth)
            ->where('type', $request->type)
            ->first();

        $previousMonth = Carbon::createFromFormat('Y-m', $basisMonth)->subMonth()->format('Y-m');

        $sourceRecord = BasisRecord::with('attachments')
            ->where('account_set_id', $accountSetId)
            ->where('project_id', $request->project_id)
            ->where('month', $previousMonth)
            ->where('type', $request->type)
            ->first();

        if (!$sourceRecord) {
            return response()->json([
                'success' => false,
                'message' => "未找到{$previousMonth}的依据，无法复制上月"
            ], 400);
        }

        if ($sourceRecord->attachments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "上月（{$previousMonth}）依据没有附件，无法复制"
            ], 400);
        }

        $record = $existing ?: BasisRecord::create([
            'account_set_id' => $accountSetId,
            'project_id' => $request->project_id,
            'type' => $request->type,
            'month' => $basisMonth,
            'description' => $request->description ?: "复制上月({$previousMonth})",
            'created_by' => Auth::id(),
        ]);

        if ($existing && $request->filled('description')) {
            $record->description = $request->description;
            $record->save();
        }

        // 仅复制附件路径引用，不做文件物理拷贝，也不删除当前已上传附件
        foreach ($sourceRecord->attachments as $attachment) {
            $duplicate = BasisAttachment::where('basis_record_id', $record->id)
                ->where('file_name', $attachment->file_name)
                ->where('file_path', $attachment->file_path)
                ->exists();

            if ($duplicate) {
                continue;
            }

            BasisAttachment::create([
                'basis_record_id' => $record->id,
                'file_name' => $attachment->file_name,
                'file_path' => $attachment->file_path,
                'file_type' => $attachment->file_type,
                'file_size' => $attachment->file_size,
            ]);
        }

        if ($request->type === 'salary') {
            \App\Services\PendingTaskService::checkAndCompleteSalaryBasisTask($record);
        } elseif ($request->type === 'attendance') {
            \App\Services\PendingTaskService::checkAndCompleteAttendanceBasisTask($record);
        }

        $messagePrefix = $existing
            ? '复制上月成功（已追加上月附件路径）'
            : '复制上月成功';

        return response()->json([
            'success' => true,
            'message' => "{$messagePrefix}（{$previousMonth} -> {$basisMonth}）",
            'data' => $record->load(['project', 'creator', 'attachments'])
        ]);
    }

    public function uploadAttachment(Request $request)
    {
        $request->validate([
            'basis_record_id' => 'required|exists:basis_records,id',
            'file' => 'required|file|max:51200', // 最大50MB
        ]);
        
        $record = BasisRecord::findOrFail($request->basis_record_id);
        $file = $request->file('file');
        
        // 存储文件
        $path = $file->store('basis_attachments/' . $record->type . '/' . $record->month, 'public');
        
        // 判断文件类型
        $mimeType = $file->getMimeType();
        $fileType = 'other';
        if (str_starts_with($mimeType, 'image/')) {
            $fileType = 'image';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ])) {
            $fileType = 'document';
        }
        
        $attachment = BasisAttachment::create([
            'basis_record_id' => $record->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $fileType,
            'file_size' => $file->getSize(),
        ]);

        // 上传附件后再关闭对应待办任务
        if ($record->type === 'salary') {
            \App\Services\PendingTaskService::checkAndCompleteSalaryBasisTask($record);
        } elseif ($record->type === 'attendance') {
            \App\Services\PendingTaskService::checkAndCompleteAttendanceBasisTask($record);
        }
        
        return response()->json([
            'success' => true,
            'message' => '上传成功',
            'data' => $attachment
        ]);
    }

    /**
     * 删除附件
     */
    public function deleteAttachment($id)
    {
        $attachment = BasisAttachment::findOrFail($id);
        
        // 删除文件
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }
        
        $attachment->delete();
        
        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    /**
     * 查看依据详情
     */
    public function show($id)
    {
        $record = BasisRecord::with(['project', 'creator', 'attachments'])
            ->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $record
        ]);
    }

    /**
     * 更新依据
     */
    public function update(Request $request, $id)
    {
        $record = BasisRecord::findOrFail($id);
        
        $request->validate([
            'description' => 'nullable|string',
        ]);
        
        $record->update([
            'description' => $request->description,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $record->load(['project', 'creator', 'attachments'])
        ]);
    }

    /**
     * 删除依据
     */
    public function destroy($id)
    {
        $record = BasisRecord::with('attachments')->findOrFail($id);
        
        // 删除所有附件
        foreach ($record->attachments as $attachment) {
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
        }
        
        $record->delete();
        
        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    /**
     * 检查依据是否存在
     */
    public function checkBasisExists(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'month' => 'required|date_format:Y-m',
            'type' => 'required|in:attendance,salary',
        ]);

        $project = Project::findOrFail($request->project_id);
        $basisMonth = $project->resolveBasisMonth($request->month);
        
        $exists = BasisRecord::where('project_id', $request->project_id)
            ->where('month', $basisMonth)
            ->where('type', $request->type)
            ->exists();
        
        return response()->json([
            'success' => true,
            'exists' => $exists
        ]);
    }

    private function applyProcessingMonthFilter($query, int $accountSetId, string $processingMonth, $projectId = null): void
    {
        if ($projectId) {
            $project = Project::where('account_set_id', $accountSetId)->find($projectId);
            $query->where('month', $project ? $project->resolveBasisMonth($processingMonth) : $processingMonth);
            return;
        }

        $previousMonth = Carbon::createFromFormat('Y-m', $processingMonth)->subMonth()->format('Y-m');

        $query->where(function ($monthQuery) use ($processingMonth, $previousMonth) {
            $monthQuery->where(function ($currentMonthQuery) use ($processingMonth) {
                $currentMonthQuery->where('month', $processingMonth)
                    ->whereHas('project', function ($projectQuery) {
                        $projectQuery->where(function ($settingQuery) {
                            $settingQuery->whereNull('salary_payment_month')
                                ->orWhere('salary_payment_month', '!=', 'next');
                        });
                    });
            })->orWhere(function ($nextMonthQuery) use ($previousMonth) {
                $nextMonthQuery->where('month', $previousMonth)
                    ->whereHas('project', function ($projectQuery) {
                        $projectQuery->where('salary_payment_month', 'next');
                    });
            });
        });
    }
}

