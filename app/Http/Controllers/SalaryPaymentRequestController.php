<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\SalaryApproval;
use App\Models\Salary;
use App\Services\ApprovalService;
use App\Services\PendingTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalaryPaymentRequestController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * 获取工资付款申请列表
     */
    public function index(Request $request)
    {
        $query = PaymentRequest::query();

        // 账套过滤
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }

        // 只查询工资付款类型
        $query->where('payment_type', 'salary');

        // 筛选条件
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $requests = $query->with([
                'salaryApproval.project:id,name',
                'submitter:id,name',
                'approver:id,name',
                'approvalInstance.records'
            ])
            ->orderBy('submitted_at', 'desc')
            ->paginate($request->input('per_page', 50));

        // 添加业务信息
        $requests->getCollection()->transform(function ($item) {
            if ($item->salaryApproval) {
                $item->project_name = $item->salaryApproval->project ? $item->salaryApproval->project->name : '';
                $item->month = $item->salaryApproval->month;
            }
            $item->submitter_name = $item->submitter ? $item->submitter->name : '';
            $item->approver_name = $item->approver ? $item->approver->name : '';
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * 提交工资付款申请（含附件上传）
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'salary_approval_id' => 'required|exists:salary_approvals,id',
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

        DB::beginTransaction();
        try {
            $salaryApproval = SalaryApproval::with('project')->find($request->salary_approval_id);

            // 检查审批状态
            if ($salaryApproval->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => '该工资表尚未审批通过，无法发起付款申请'
                ], 422);
            }

            // 检查是否已经发起过付款申请
            $existingRequest = PaymentRequest::where('salary_approval_id', $salaryApproval->id)->first();
            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => '该工资表已发起过付款申请'
                ], 422);
            }

            // 计算付款金额（所有员工的实发工资总和）
            $totalAmount = Salary::where('project_id', $salaryApproval->project_id)
                                ->where('month', $salaryApproval->month)
                                ->where('account_set_id', $currentAccountSetId)
                                ->sum('net_salary');

            // 获取报销表单数据（如果前端传了的话）
            $formData = $request->input('reimbursement_form_data', []);
            
            // 记录接收到的表单数据（用于调试）
            \Illuminate\Support\Facades\Log::info('工资付款申请 - 接收到的报销表单数据', [
                'form_data' => $formData,
                'project' => $formData['project'] ?? '未设置',
                'project_name' => $formData['projectName'] ?? '未设置'
            ]);
            
            // 获取稍后上传状态
            $uploadLater = $request->input('upload_later', false);
            
            // 创建付款申请记录
            $paymentRequest = PaymentRequest::create([
                'payment_type' => 'salary',
                'account_set_id' => $currentAccountSetId,
                'salary_approval_id' => $salaryApproval->id,
                'amount' => $totalAmount,
                'status' => 'pending',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'remarks' => $request->remarks ?? ('工资付款申请 - ' . $salaryApproval->project->name . ' ' . $salaryApproval->month),
                'upload_later' => $uploadLater, // 保存稍后上传状态
                // 报销表单字段
                'category' => $formData['category'] ?? null, // 保存类目
                'project' => $formData['project'] ?? null,
                'apply_date' => $formData['applyDate'] ?? null,
                'unit_name' => $formData['unitName'] ?? null,
                'invoice_number' => $formData['invoiceNumber'] ?? null,
                'verified' => $formData['verified'] ?? true,
                'payment_date' => $formData['paymentDate'] ?? null,
                'expenditure_amount' => $formData['expenditureAmount'] ?? null,
                'project_name' => $formData['projectName'] ?? null,
                'summary' => $formData['summary'] ?? null,
                'invoice_received' => $formData['invoiceReceived'] ?? false,
                'invoice_type' => $formData['invoiceType'] ?? null,
                'invoice_amount' => $formData['invoiceAmount'] ?? null,
                'tax_rate' => $formData['taxRate'] ?? null,
                'deduction_amount' => $formData['deductionAmount'] ?? null,
                'amount_excluding_tax' => $formData['amountExcludingTax'] ?? null,
                'tax_amount' => $formData['taxAmount'] ?? null,
                'is_consistent' => $formData['isConsistent'] ?? false,
                'status_checked' => $formData['status'] ?? true,
                'selected_month' => $formData['selectedMonth'] ?? null,
                'reimburser' => $formData['reimburser'] ?? null,
                'invoice_date' => $formData['invoiceDate'] ?? null,
                'accounted' => $formData['accounted'] ?? true,
                'company' => $formData['company'] ?? null,
            ]);

            // 开启候补资料时，给发起人生成待办
            PendingTaskService::createPaymentSupplementTask($paymentRequest);

            DB::commit();

            \Log::info('工资付款申请已创建', [
                'payment_request_id' => $paymentRequest->id,
                'salary_approval_id' => $salaryApproval->id,
                'amount' => $totalAmount
            ]);

            // 返回付款申请ID，前端会继续上传附件
            return response()->json([
                'success' => true,
                'message' => '工资付款申请已创建',
                'data' => $paymentRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建工资付款申请失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '创建付款申请失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 完成提交（上传完附件后，创建审批流程）
     */
    public function completeSubmission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_request_id' => 'required|exists:payment_requests,id',
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
            $paymentRequest = PaymentRequest::with(['salaryApproval.project', 'salaryApproval.attachments', 'attachments'])->find($request->payment_request_id);

            // 检查是否已经创建过审批流程
            if ($paymentRequest->approval_instance_id) {
                return response()->json([
                    'success' => false,
                    'message' => '该付款申请已经创建了审批流程'
                ], 422);
            }

            // 获取付款申请新上传的附件
            $attachments = $paymentRequest->attachments->map(function ($att) {
                return [
                    'path' => $att->file_path,
                    'name' => $att->filename,
                    'size' => $att->file_size,
                    'type' => $att->mime_type,
                ];
            })->toArray();
            
            // 自动合并原工资表审批的附件
            if ($paymentRequest->salaryApproval && $paymentRequest->salaryApproval->attachments) {
                $salaryAttachments = $paymentRequest->salaryApproval->attachments->map(function ($att) {
                    return [
                        'path' => $att->file_path,
                        'name' => $att->filename,
                        'size' => $att->file_size,
                        'type' => $att->mime_type,
                    ];
                })->toArray();
                
                // 合并附件，原工资表附件放在前面
                $attachments = array_merge($salaryAttachments, $attachments);
                
                \Log::info('已合并原工资表审批附件', [
                    'salary_approval_id' => $paymentRequest->salary_approval_id,
                    'salary_attachments_count' => count($salaryAttachments),
                    'total_attachments_count' => count($attachments)
                ]);
            }

            // 获取盖章方式，默认线上
            $stampMethod = $request->input('stamp_method', 'online');
            
            // 创建审批流程实例
            $instance = $this->approvalService->createApprovalInstance(
                $paymentRequest->account_set_id,
                '工资付款申请',  // 业务类型
                $paymentRequest->id, // 业务ID
                $paymentRequest->submitted_by,
                $attachments,
                true, // 跳过发起人
                $stampMethod // 盖章方式
            );

            // 更新付款申请，关联审批实例
            $paymentRequest->update([
                'approval_instance_id' => $instance->id
            ]);

            DB::commit();

            \Log::info('工资付款审批流程创建成功', [
                'payment_request_id' => $paymentRequest->id,
                'instance_id' => $instance->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '付款审批流程已创建',
                'data' => [
                    'payment_request' => $paymentRequest,
                    'instance' => $instance
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建工资付款审批流程失败', [
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
     * 上传附件
     */
    public function uploadAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_request_id' => 'required|exists:payment_requests,id',
            'file' => 'required|file|max:51200', // Max 50MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $paymentRequest = PaymentRequest::find($request->payment_request_id);

        // 只有待审批状态才能上传附件
        if ($paymentRequest->status !== 'pending') {
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

            // 保存文件到 public/payment_requests/{id}/ 目录
            $directory = public_path('payment_requests/' . $paymentRequest->id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'payment_requests/' . $paymentRequest->id . '/' . $filename;

            // 获取附件类型
            $attachmentType = $request->input('attachment_type', 'attachment');
            
            \Log::info('【工资付款】上传附件', [
                'payment_request_id' => $paymentRequest->id,
                'filename' => $originalName,
                'attachment_type_from_request' => $request->input('attachment_type'),
                'attachment_type_final' => $attachmentType,
                'all_request_data' => $request->all(),
            ]);
            
            // 创建附件记录
            $attachment = \App\Models\PaymentRequestAttachment::create([
                'payment_request_id' => $paymentRequest->id,
                'filename' => $originalName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'attachment_type' => $attachmentType,
                'uploaded_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => $attachment->load('uploader')
            ]);
        } catch (\Exception $e) {
            \Log::error('上传付款申请附件失败', ['error' => $e->getMessage()]);
            
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
            'id' => 'required|exists:payment_request_attachments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $attachment = \App\Models\PaymentRequestAttachment::with('paymentRequest')->find($request->id);

        // 只有待审批状态才能删除附件
        if ($attachment->paymentRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待审批状态才能删除附件'
            ], 400);
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
            \Log::error('删除付款申请附件失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '附件删除失败：' . $e->getMessage()
            ], 500);
        }
    }
}


