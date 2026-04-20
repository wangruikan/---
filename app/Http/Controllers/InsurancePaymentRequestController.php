<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\ProcessApproval;
use App\Services\ApprovalService;
use App\Services\AttachmentPlaceholderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InsurancePaymentRequestController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * 提交保险付款申请
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'process_approval_id' => 'required|exists:process_approvals,id',
            'amount' => 'required|numeric|min:0', // 添加金额验证
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
            $processApproval = ProcessApproval::find($request->process_approval_id);

            // 检查流程状态
            if ($processApproval->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => '该汇总流程尚未审批通过，无法发起付款申请'
                ], 422);
            }

            // 检查是否已经发起过付款申请
            $existingRequest = PaymentRequest::where('insurance_summary_id', $processApproval->id)->first();
            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => '该汇总流程已发起过付款申请'
                ], 422);
            }

            // 从请求中获取付款金额
            $amount = $request->input('amount');

            // 获取报销表单数据（如果前端传了的话）
            $formData = $request->input('reimbursement_form_data', []);
            
            // 创建付款申请记录
            $paymentRequest = PaymentRequest::create([
                'payment_type' => 'insurance',
                'account_set_id' => $currentAccountSetId,
                'insurance_summary_id' => $processApproval->id,
                'project_ids' => $processApproval->project_ids, // 继承汇总申请的项目ID
                'amount' => $amount, // 使用用户输入的金额
                'status' => 'pending',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'remarks' => $request->remarks ?? ('保险汇总付款申请 - ' . $processApproval->title),
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

            DB::commit();

            \Log::info('保险付款申请已创建', [
                'payment_request_id' => $paymentRequest->id,
                'process_approval_id' => $processApproval->id,
                'amount' => $amount,
                'project_ids' => $processApproval->project_ids
            ]);

            // 返回付款申请ID，前端会继续上传附件
            return response()->json([
                'success' => true,
                'message' => '保险付款申请已创建',
                'data' => $paymentRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建保险付款申请失败', ['error' => $e->getMessage()]);
            
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
            $paymentRequest = PaymentRequest::with(['attachments', 'insuranceSummary.attachments'])->find($request->payment_request_id);

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
            
            // 自动合并原保险汇总的附件，并复制到付款申请附件表
            if ($paymentRequest->insuranceSummary && $paymentRequest->insuranceSummary->attachments) {
                $summaryAttachments = [];
                
                foreach ($paymentRequest->insuranceSummary->attachments as $att) {
                    // 将原汇总附件复制到付款申请附件表
                    \App\Models\PaymentRequestAttachment::create([
                        'payment_request_id' => $paymentRequest->id,
                        'filename' => $att->filename,
                        'file_path' => $att->file_path,
                        'file_size' => $att->file_size,
                        'mime_type' => $att->mime_type,
                        'uploaded_by' => $paymentRequest->submitted_by,
                    ]);
                    
                    $summaryAttachments[] = [
                        'path' => $att->file_path,
                        'name' => $att->filename,
                        'size' => $att->file_size,
                        'type' => $att->mime_type,
                    ];
                }
                
                // 合并附件，原保险汇总附件放在前面
                $attachments = array_merge($summaryAttachments, $attachments);
                
                \Log::info('已合并原保险汇总附件到付款申请', [
                    'insurance_summary_id' => $paymentRequest->insurance_summary_id,
                    'summary_attachments_count' => count($summaryAttachments),
                    'total_attachments_count' => count($attachments)
                ]);
            }

            // 如果没有附件，生成占位附件
            if (empty($attachments)) {
                \Log::info('保险付款申请没有附件，生成占位附件', [
                    'payment_request_id' => $paymentRequest->id
                ]);
                
                $placeholder = AttachmentPlaceholderService::generatePlaceholder(
                    '保险汇总付款申请',
                    $paymentRequest->id,
                    "保险汇总付款申请 - {$paymentRequest->insuranceSummary->title}"
                );
                
                // 保存占位附件记录
                \App\Models\PaymentRequestAttachment::create([
                    'payment_request_id' => $paymentRequest->id,
                    'filename' => $placeholder['name'],
                    'file_path' => $placeholder['path'],
                    'file_size' => $placeholder['size'],
                    'mime_type' => $placeholder['type'],
                    'uploaded_by' => $paymentRequest->submitted_by,
                ]);
                
                $attachments[] = $placeholder;
                
                \Log::info('占位附件已生成', [
                    'path' => $placeholder['path'],
                    'name' => $placeholder['name']
                ]);
            }

            // 获取盖章方式，默认线上
            $stampMethod = $request->input('stamp_method', 'online');

            // 创建审批流程实例
            $instance = $this->approvalService->createApprovalInstance(
                $paymentRequest->account_set_id,
                '保险汇总付款申请',  // 业务类型
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

            \Log::info('保险付款审批流程创建成功', [
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
            \Log::error('创建保险付款审批流程失败', [
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
            
            \Log::info('【保险付款】上传附件', [
                'payment_request_id' => $paymentRequest->id,
                'filename' => $originalName,
                'attachment_type_from_request' => $request->input('attachment_type'),
                'attachment_type_final' => $attachmentType,
            ]);
            
            // 创建附件记录
            $attachment = \App\Models\PaymentRequestAttachment::create([
                'payment_request_id' => $paymentRequest->id,
                'filename' => $originalName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'attachment_type' => $attachmentType, // 保存附件类型：invoice=发票, attachment=普通附件
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

    /**
     * 替换附件（用于PDF合成后替换原文件）
     */
    public function replaceAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_request_id' => 'required|exists:payment_requests,id',
            'attachment_id' => 'required|exists:payment_request_attachments,id',
            'file' => 'required|file|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $attachment = \App\Models\PaymentRequestAttachment::find($request->attachment_id);
        
        // 验证附件属于该付款申请
        if ($attachment->payment_request_id != $request->payment_request_id) {
            return response()->json([
                'success' => false,
                'message' => '附件不属于该付款申请'
            ], 400);
        }

        try {
            $file = $request->file('file');
            $originalName = $attachment->filename; // 保持原文件名
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;
            
            // 获取文件信息
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            // 删除旧文件
            $oldFilePath = public_path($attachment->file_path);
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            // 保存新文件
            $directory = public_path('payment_requests/' . $request->payment_request_id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'payment_requests/' . $request->payment_request_id . '/' . $filename;

            // 更新附件记录
            $attachment->update([
                'file_path' => $path,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
            ]);

            \Log::info('附件替换成功', [
                'attachment_id' => $attachment->id,
                'old_path' => $oldFilePath,
                'new_path' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件替换成功',
                'data' => $attachment
            ]);
        } catch (\Exception $e) {
            \Log::error('替换附件失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '附件替换失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查是否可以上传发票
     */
    public function checkInvoiceUploadPermission(Request $request)
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

        $paymentRequest = PaymentRequest::with('insuranceSummary')->find($request->payment_request_id);
        $user = $request->user();

        $canUpload = $paymentRequest->canUploadInvoice($user);
        $insuranceType = $paymentRequest->getInsuranceType();

        return response()->json([
            'success' => true,
            'data' => [
                'can_upload' => $canUpload,
                'needs_invoice' => $paymentRequest->needsInvoiceUpload(),
                'insurance_type' => $insuranceType,
                'invoice_status' => $paymentRequest->invoice_status,
                'message' => $canUpload ? '可以上传发票' : '无权上传发票'
            ]
        ]);
    }

    /**
     * 上传发票附件
     */
    public function uploadInvoiceAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_request_id' => 'required|exists:payment_requests,id',
            'file' => 'required|file|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $paymentRequest = PaymentRequest::with('insuranceSummary')->find($request->payment_request_id);
        $user = $request->user();

        // 检查权限
        if (!$paymentRequest->canUploadInvoice($user)) {
            return response()->json([
                'success' => false,
                'message' => '您没有权限上传发票'
            ], 403);
        }

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;
            
            // 在移动文件之前获取文件信息
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            // 保存文件
            $directory = public_path('payment_requests/' . $paymentRequest->id . '/invoices');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'payment_requests/' . $paymentRequest->id . '/invoices/' . $filename;

            // 创建发票附件记录
            $attachment = \App\Models\PaymentRequestInvoiceAttachment::create([
                'payment_request_id' => $paymentRequest->id,
                'filename' => $originalName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => $user->id,
            ]);

            // 更新发票状态
            if ($paymentRequest->invoice_status === 'pending_invoice') {
                $paymentRequest->update([
                    'invoice_status' => 'invoice_uploaded',
                    'invoice_uploaded_at' => now(),
                    'invoice_uploaded_by' => $user->id,
                ]);
                
                // 检查并完成付款回执待办任务
                try {
                    \App\Services\PendingTaskService::checkAndCompletePaymentReceiptTask($paymentRequest);
                    \Log::info('已检查并完成付款回执待办任务', [
                        'payment_request_id' => $paymentRequest->id
                    ]);
                } catch (\Exception $e) {
                    \Log::error('检查并完成付款回执待办任务失败', [
                        'payment_request_id' => $paymentRequest->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => '发票上传成功',
                'data' => $attachment->load('uploader')
            ]);
        } catch (\Exception $e) {
            \Log::error('上传发票附件失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除发票附件
     */
    public function deleteInvoiceAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:payment_request_invoice_attachments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $attachment = \App\Models\PaymentRequestInvoiceAttachment::with('paymentRequest.insuranceSummary')->find($request->id);
        $paymentRequest = $attachment->paymentRequest;
        $user = $request->user();

        // 检查权限
        if (!$paymentRequest->canUploadInvoice($user) && $paymentRequest->invoice_status !== 'invoice_uploaded') {
            return response()->json([
                'success' => false,
                'message' => '您没有权限删除此附件'
            ], 403);
        }

        try {
            // 删除文件
            $filePath = public_path($attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '附件删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交发票审批流程（第二次审批）
     */
    public function submitInvoiceApproval(Request $request)
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

        $paymentRequest = PaymentRequest::with(['insuranceSummary', 'invoiceAttachments'])->find($request->payment_request_id);
        $user = $request->user();

        // 检查状态
        if ($paymentRequest->invoice_status !== 'invoice_uploaded') {
            return response()->json([
                'success' => false,
                'message' => '请先上传发票附件'
            ], 422);
        }

        // 检查是否有发票附件
        if ($paymentRequest->invoiceAttachments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '请先上传发票附件'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 准备发票附件
            $attachments = $paymentRequest->invoiceAttachments->map(function ($att) {
                return [
                    'path' => $att->file_path,
                    'name' => $att->filename,
                    'size' => $att->file_size,
                    'type' => $att->mime_type,
                ];
            })->toArray();

            // 获取保险类型用于审批流程标题
            $insuranceType = $paymentRequest->getInsuranceType();
            $typeName = $insuranceType === 'social_security' ? '社保' : '公积金';

            // 获取盖章方式，默认线上
            $stampMethod = $request->input('stamp_method', 'online');
            
            // 创建发票审批流程（跳过第一个节点）
            $instance = $this->approvalService->createApprovalInstance(
                $paymentRequest->account_set_id,
                $typeName . '付款发票审批',
                $paymentRequest->id,
                $user->id,
                $attachments,
                true, // 跳过发起人（第一个节点）
                $stampMethod // 盖章方式
            );

            // 更新付款申请
            $paymentRequest->update([
                'invoice_status' => 'invoice_in_approval',
                'invoice_approval_instance_id' => $instance->id,
            ]);

            DB::commit();

            \Log::info('发票审批流程创建成功', [
                'payment_request_id' => $paymentRequest->id,
                'instance_id' => $instance->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '发票审批流程已创建',
                'data' => [
                    'payment_request' => $paymentRequest,
                    'instance' => $instance
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建发票审批流程失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '创建审批流程失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 发票审批完成回调（将发票附件并入原付款申请附件）
     */
    public function onInvoiceApprovalCompleted($paymentRequestId)
    {
        $paymentRequest = PaymentRequest::with(['invoiceAttachments'])->find($paymentRequestId);

        if (!$paymentRequest) {
            return;
        }

        DB::beginTransaction();
        try {
            // 将发票附件复制到原付款申请附件表
            foreach ($paymentRequest->invoiceAttachments as $invoiceAtt) {
                \App\Models\PaymentRequestAttachment::create([
                    'payment_request_id' => $paymentRequest->id,
                    'filename' => $invoiceAtt->filename,
                    'file_path' => $invoiceAtt->file_path,
                    'file_size' => $invoiceAtt->file_size,
                    'mime_type' => $invoiceAtt->mime_type,
                    'uploaded_by' => $invoiceAtt->uploaded_by,
                    'attachment_type' => 'invoice', // 标记为发票类型
                ]);
            }
            
            // 删除发票凭证表中的记录（已并入原附件表）
            \App\Models\PaymentRequestInvoiceAttachment::where('payment_request_id', $paymentRequest->id)->delete();

            // 更新发票状态
            $paymentRequest->update([
                'invoice_status' => 'invoice_approved',
            ]);
            
            // 检查并完成付款回执待办任务
            try {
                \App\Services\PendingTaskService::checkAndCompletePaymentReceiptTask($paymentRequest);
                \Log::info('已检查并完成付款回执待办任务（发票审批完成）', [
                    'payment_request_id' => $paymentRequest->id
                ]);
            } catch (\Exception $e) {
                \Log::error('检查并完成付款回执待办任务失败（发票审批完成）', [
                    'payment_request_id' => $paymentRequest->id,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            \Log::info('发票审批完成，附件已并入原付款申请', [
                'payment_request_id' => $paymentRequest->id,
                'invoice_attachments_count' => $paymentRequest->invoiceAttachments->count(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('发票审批完成处理失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取发票附件列表
     */
    public function getInvoiceAttachments(Request $request)
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

        $attachments = \App\Models\PaymentRequestInvoiceAttachment::with('uploader')
            ->where('payment_request_id', $request->payment_request_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments
        ]);
    }
}
