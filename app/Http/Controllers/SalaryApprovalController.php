<?php

namespace App\Http\Controllers;

use App\Models\SalaryApproval;
use App\Models\Salary;
use App\Models\SalaryApprovalAttachment;
use App\Models\SalaryPaymentRecord;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalaryApprovalController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }
    /**
     * 获取工资表审批列表
     */
    public function index(Request $request)
    {
        $query = SalaryApproval::query();

        // 账套过滤
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }

        // 筛选条件
        if ($request->has('project_id') && $request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        // 状态筛选：默认不显示已驳回的记录
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        } else {
            // 如果没有指定状态，默认排除已驳回的记录
            $query->where('status', '!=', 'rejected');
        }

        if ($request->has('approval_type') && $request->approval_type) {
            $query->where('approval_type', $request->approval_type);
        }

        $approvals = $query->with(['project:id,name', 'submitter:id,name', 'approver:id,name', 'attachments.uploader'])
                          ->orderBy('submitted_at', 'desc')
                          ->paginate($request->input('per_page', 50));

        // 添加项目名称等信息
        $approvals->getCollection()->transform(function ($item) {
            $item->project_name = $item->project ? $item->project->name : '';
            $item->submitter_name = $item->submitter ? $item->submitter->name : '';
            $item->approver_name = $item->approver ? $item->approver->name : '';
            
            // 获取工资表汇总数据
            $salaries = Salary::where('project_id', $item->project_id)
                             ->where('month', $item->month)
                             ->get();
            
            $item->employee_count = $salaries->count();
            $item->total_gross_salary = $salaries->sum('gross_salary');
            $item->total_net_salary = $salaries->sum('net_salary');
            
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $approvals
        ]);
    }

    /**
     * 提交工资表审批
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'month' => 'required|date_format:Y-m',
            'approval_type' => 'required|in:online,offline',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $currentAccountSetId = $request->input('current_account_set_id');
        if (!$currentAccountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套'
            ], 422);
        }

        // 检查该项目该月份的工资表是否存在
        $salariesCount = Salary::where('project_id', $request->project_id)
                              ->where('month', $request->month)
                              ->where('account_set_id', $currentAccountSetId)
                              ->count();

        if ($salariesCount === 0) {
            return response()->json([
                'success' => false,
                'message' => '该项目该月份还没有生成工资表'
            ], 422);
        }

        // 检查是否已经提交过审批（排除已驳回的）
        \Log::info('=== 新代码已加载 2026-01-17 ===');
        
        $existingApproval = SalaryApproval::where('project_id', $request->project_id)
                                         ->where('month', $request->month)
                                         ->first(); // 先查所有的
        
        \Log::info('检查重复提交 - 所有审批记录', [
            'project_id' => $request->project_id,
            'month' => $request->month,
            'all_approval' => $existingApproval ? $existingApproval->toArray() : null,
        ]);
        
        // 再过滤掉已驳回的
        $existingApproval = SalaryApproval::where('project_id', $request->project_id)
                                         ->where('month', $request->month)
                                         ->where('status', '!=', 'rejected')
                                         ->first();

        \Log::info('检查重复提交 - 非驳回审批记录', [
            'project_id' => $request->project_id,
            'month' => $request->month,
            'non_rejected_approval' => $existingApproval ? $existingApproval->toArray() : null,
            'has_existing' => $existingApproval ? true : false
        ]);

        // 允许同项目同月份重复发起工资审批，不再拦截历史审批记录

        // 【提前检查】检查离职/退休员工是否上传了离职证明
        // 创建一个临时的审批对象用于检查
        $tempApproval = new SalaryApproval([
            'account_set_id' => $currentAccountSetId,
            'project_id' => $request->project_id,
            'month' => $request->month,
        ]);
        $missingCertificates = $this->checkResignationCertificates($tempApproval);
        if (!empty($missingCertificates)) {
            return response()->json([
                'success' => false,
                'message' => '以下离职/退休员工未上传离职证明，无法提交审批',
                'data' => [
                    'missing_employees' => $missingCertificates
                ]
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 创建工资表审批记录
            $approval = SalaryApproval::create([
                'account_set_id' => $currentAccountSetId,
                'project_id' => $request->project_id,
                'month' => $request->month,
                'approval_type' => $request->approval_type,
                'status' => 'pending',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'remarks' => $request->remarks,
            ]);

            // 更新工资记录的审批ID
            // 只更新那些还没有关联审批的工资记录（salary_approval_id = NULL）
            // 已经关联到其他审批的工资记录（包括被驳回的）保持不变
            
            \Log::info('🔄 准备更新工资记录的审批ID', [
                'new_approval_id' => $approval->id,
                'project_id' => $request->project_id,
                'month' => $request->month,
            ]);
            
            $affectedRows = Salary::where('project_id', $request->project_id)
                 ->where('month', $request->month)
                 ->where('account_set_id', $currentAccountSetId)
                 ->whereNull('salary_approval_id')  // 只更新没有审批ID的记录
                 ->update(['salary_approval_id' => $approval->id]);
            
            \Log::info('✅ 工资记录审批ID更新完成', [
                'affected_rows' => $affectedRows,
            ]);

            \Log::info('工资表审批记录创建成功', [
                'approval_id' => $approval->id,
                'project_id' => $request->project_id,
                'month' => $request->month,
            ]);

            DB::commit();

            // 返回审批ID，前端会继续上传附件
            return response()->json([
                'success' => true,
                'message' => '工资表审批已提交',
                'data' => $approval
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('提交工资表审批失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '提交失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 审批通过
     */
    public function approve(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:salary_approvals,id',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $approval = SalaryApproval::find($request->id);

        if ($approval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '该审批已经处理过了'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $approval->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'remarks' => $request->remarks ?? $approval->remarks,
            ]);

            // 审批通过后，自动生成发工资表
            $this->generateSalaryPaymentRecords($approval);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '审批通过，发工资表已生成',
                'data' => $approval
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('工资表审批通过失败', [
                'approval_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '审批失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 审批拒绝
     */
    public function reject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:salary_approvals,id',
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $approval = SalaryApproval::find($request->id);

        if ($approval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '该审批已经处理过了'
            ], 422);
        }

        $approval->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => '审批已拒绝',
            'data' => $approval
        ]);
    }

    /**
     * 删除审批（撤回）
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:salary_approvals,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $approval = SalaryApproval::find($request->id);

        // 只有待审批状态才能撤回
        if ($approval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待审批的记录才能撤回'
            ], 422);
        }

        // 只有提交人才能撤回
        if ($approval->submitted_by !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '只有提交人才能撤回审批'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 清除工资记录的审批ID
            Salary::where('salary_approval_id', $approval->id)
                 ->update(['salary_approval_id' => null]);

            // 删除审批记录
            $approval->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '审批已撤回'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('撤回工资表审批失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '撤回失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 完成提交（创建审批流程实例）
     */
    public function completeSubmission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'salary_approval_id' => 'required|exists:salary_approvals,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $approval = SalaryApproval::with(['attachments', 'project'])->find($request->salary_approval_id);

            // 检查是否已经有审批流程实例
            if ($approval->approval_instance_id) {
                return response()->json([
                    'success' => false,
                    'message' => '该审批已经创建了审批流程'
                ], 422);
            }

            // 【新增】检查离职/退休员工是否上传了离职证明
            $missingCertificates = $this->checkResignationCertificates($approval);
            if (!empty($missingCertificates)) {
                return response()->json([
                    'success' => false,
                    'message' => '以下离职/退休员工未上传离职证明，无法提交审批',
                    'data' => [
                        'missing_employees' => $missingCertificates
                    ]
                ], 422);
            }

            // 获取附件列表
            $attachments = $approval->attachments->map(function ($att) {
                return [
                    'path' => $att->file_path,
                    'name' => $att->filename,
                    'size' => $att->file_size,
                    'type' => $att->mime_type,
                ];
            })->toArray();

            // 获取盖章方式（从工资表审批的approval_type字段获取）
            $stampMethod = $approval->approval_type ?? 'online';

            // 创建审批流程实例
            $instance = $this->approvalService->createApprovalInstance(
                $approval->account_set_id,
                '工资表审批',  // 业务类型
                $approval->id, // 业务ID
                $approval->submitted_by,
                $attachments,
                true, // 跳过发起人，直接进入第二步审批
                $stampMethod // 盖章方式
            );

            // 更新审批记录，关联审批实例
            $approval->update([
                'approval_instance_id' => $instance->id
            ]);

            DB::commit();

            \Log::info('工资表审批流程创建成功', [
                'approval_id' => $approval->id,
                'instance_id' => $instance->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '审批流程已创建',
                'data' => [
                    'approval' => $approval,
                    'instance' => $instance
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建工资表审批流程失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '创建审批流程失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查离职/退休员工是否上传了离职证明
     */
    private function checkResignationCertificates($approval)
    {
        // 获取该工资表的所有员工
        $query = Salary::where('project_id', $approval->project_id)
                       ->where('month', $approval->month);
        
        // 如果有 account_set_id，添加查询条件
        if ($approval->account_set_id) {
            $query->where('account_set_id', $approval->account_set_id);
        }
        
        $salaries = $query->with('employee.resignationCertificates')->get();

        $missingCertificates = [];

        foreach ($salaries as $salary) {
            $employee = $salary->employee;
            if (!$employee) {
                continue;
            }

            // 检查是否是离职/退休/已终止状态
            $needsCertificate = in_array($employee->contract_status, ['terminated', 'retired']);

            if ($needsCertificate) {
                // 检查是否已上传离职证明
                if (!$employee->hasResignationCertificate()) {
                    $missingCertificates[] = [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'employee_number' => $employee->employee_number,
                        'contract_status' => $employee->contract_status,
                        'contract_status_text' => $this->getContractStatusText($employee->contract_status)
                    ];
                }
            }
        }

        return $missingCertificates;
    }

    /**
     * 获取合同状态文本
     */
    private function getContractStatusText($status)
    {
        $texts = [
            'active' => '在职',
            'expired' => '已过期',
            'terminated' => '已终止',
            'retired' => '退休',
        ];
        return $texts[$status] ?? $status;
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'salary_approval_id' => 'required|exists:salary_approvals,id',
            'file' => 'required|file|max:51200', // Max 50MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $approval = SalaryApproval::find($request->salary_approval_id);

        // 只有待审批状态才能上传附件（已经提交但未审批）
        if ($approval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待审批状态才能上传附件'
            ], 400);
        }

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;
            
            // 获取文件信息
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            // 保存文件到 public/salary_approvals/{id}/ 目录
            $directory = public_path('salary_approvals/' . $approval->id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'salary_approvals/' . $approval->id . '/' . $filename;

            // 创建附件记录
            $attachment = SalaryApprovalAttachment::create([
                'salary_approval_id' => $approval->id,
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
            \Log::error('上传工资表审批附件失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '附件上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除附件
     */
    public function deleteAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:salary_approval_attachments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $attachment = SalaryApprovalAttachment::with('salaryApproval')->find($request->id);

        // 只有待审批状态才能删除附件
        if ($attachment->salaryApproval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待审批状态才能删除附件'
            ], 400);
        }

        // 只有上传人或管理员才能删除
        if ($attachment->uploaded_by !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '只有上传人才能删除附件'
            ], 403);
        }

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
            \Log::error('删除工资表审批附件失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '附件删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取附件列表
     */
    public function getAttachments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'salary_approval_id' => 'required|exists:salary_approvals,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $attachments = SalaryApprovalAttachment::where('salary_approval_id', $request->salary_approval_id)
                                               ->with('uploader:id,name')
                                               ->orderBy('created_at', 'desc')
                                               ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments
        ]);
    }

    /**
     * 下载附件
     */
    public function downloadAttachment($id)
    {
        $attachment = SalaryApprovalAttachment::findOrFail($id);
        
        $filePath = public_path($attachment->file_path);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => '文件不存在'
            ], 404);
        }

        return response()->download($filePath, $attachment->filename);
    }

    /**
     * 生成发工资表（工资表审批通过时调用）
     */
    private function generateSalaryPaymentRecords($approval)
    {
        // 获取该工资表的所有工资记录
        $salaries = Salary::where('project_id', $approval->project_id)
                         ->where('month', $approval->month)
                         ->get();

        if ($salaries->isEmpty()) {
            throw new \Exception('工资表中没有员工记录');
        }

        // 为每个员工生成发工资记录
        foreach ($salaries as $salary) {
            $employee = $salary->employee;
            if (!$employee) {
                continue;
            }

            // 检查是否已经存在该记录（避免重复生成）
            $exists = SalaryPaymentRecord::where('salary_id', $salary->id)
                                        ->exists();
            if ($exists) {
                continue;
            }

            // 创建发工资记录
            SalaryPaymentRecord::create([
                'salary_id' => $salary->id,
                'employee_id' => $employee->id,
                'project_id' => $approval->project_id,
                'month' => $approval->month,
                'bank_account' => $employee->bank_account ?? '',
                'bank_account_holder' => $employee->bank_account_holder ?? '',
                'amount' => $salary->net_salary ?? 0, // 实发工资
                'bank_name' => $employee->bank_name ?? '',
                'bank_province' => $employee->bank_province ?? '',
                'remittance_remark' => $employee->remittance_remark ?? '',
                'account_set_id' => $approval->account_set_id,
            ]);
        }

        \Log::info('发工资表生成成功', [
            'approval_id' => $approval->id,
            'project_id' => $approval->project_id,
            'month' => $approval->month,
            'record_count' => $salaries->count(),
        ]);
    }
}
