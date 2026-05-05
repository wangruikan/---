<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\PaymentRequestAttachment;
use App\Models\Reimbursement;
use App\Services\ApprovalService;
use App\Services\PendingTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReimbursementPaymentRequestController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * 获取报销付款申请列表
     */
    public function index(Request $request)
    {
        $query = PaymentRequest::query();

        $currentAccountSetId = $this->resolveCurrentAccountSetId($request);
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif (!$this->isAdmin($request)) {
            $query->whereRaw('1 = 0');
        }

        $reimbursementTypes = ['reimbursement', '报销', '差旅', '采购', '项目', '其他'];
        $query->whereIn('payment_type', $reimbursementTypes);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $requests = $query->with([
            'reimbursement',
            'submitter:id,name',
            'approver:id,name',
            'approvalInstance.records',
        ])
            ->orderBy('submitted_at', 'desc')
            ->paginate($request->input('per_page', 50));

        $requests->getCollection()->transform(function ($item) {
            if ($item->reimbursement) {
                $item->applicant = $item->reimbursement->applicant;
                $item->reason = $item->reimbursement->reason;
            }
            $item->submitter_name = $item->submitter ? $item->submitter->name : '';
            $item->approver_name = $item->approver ? $item->approver->name : '';
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * 提交报销付款申请（创建付款申请草稿）
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reimbursement_id' => 'required|exists:reimbursements,id',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentAccountSetId = $this->resolveCurrentAccountSetId($request);
        if (!$currentAccountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套',
            ], 422);
        }

        $reimbursement = Reimbursement::where('id', $request->reimbursement_id)
            ->where('account_set_id', $currentAccountSetId)
            ->first();
        if (!$reimbursement) {
            return response()->json([
                'success' => false,
                'message' => '报销申请不存在或不属于当前账套',
            ], 404);
        }

        if ($reimbursement->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => '该报销申请尚未审批通过，无法发起付款申请',
            ], 422);
        }

        $existingRequest = PaymentRequest::where('reimbursement_id', $reimbursement->id)
            ->where('account_set_id', $currentAccountSetId)
            ->first();
        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => '该报销申请已发起过付款申请',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $paymentType = $reimbursement->category ?: 'reimbursement';
            $formData = $request->input('reimbursement_form_data', []);
            $uploadLater = $request->input('upload_later', false);

            $paymentRequest = PaymentRequest::create([
                'payment_type' => $paymentType,
                'category' => $formData['category'] ?? $reimbursement->category ?? null,
                'account_set_id' => $currentAccountSetId,
                'reimbursement_id' => $reimbursement->id,
                'amount' => $reimbursement->amount,
                'status' => 'pending',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'remarks' => $request->remarks ?? ($paymentType . '付款申请 - ' . $reimbursement->applicant . ($reimbursement->reason ? ' - ' . $reimbursement->reason : '')),
                'upload_later' => $uploadLater,
                // 报销表单字段
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

            PendingTaskService::createPaymentSupplementTask($paymentRequest);

            DB::commit();

            \Log::info('报销付款申请已创建', [
                'payment_request_id' => $paymentRequest->id,
                'reimbursement_id' => $reimbursement->id,
                'amount' => $reimbursement->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => '报销付款申请已创建',
                'data' => $paymentRequest,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建报销付款申请失败', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '创建付款申请失败: ' . $e->getMessage(),
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentAccountSetId = $this->resolveCurrentAccountSetId($request);
        if (!$currentAccountSetId && !$this->isAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套',
            ], 422);
        }

        try {
            $paymentRequestQuery = PaymentRequest::with(['reimbursement.attachments', 'attachments'])
                ->where('id', $request->payment_request_id);
            if ($currentAccountSetId) {
                $paymentRequestQuery->where('account_set_id', $currentAccountSetId);
            }
            $paymentRequest = $paymentRequestQuery->first();
            if (!$paymentRequest) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在或不属于当前账套',
                ], 404);
            }

            if ($paymentRequest->approval_instance_id) {
                return response()->json([
                    'success' => false,
                    'message' => '该付款申请已经创建了审批流程',
                ], 422);
            }

            $attachments = $paymentRequest->attachments->map(function ($att) {
                return [
                    'path' => $att->file_path,
                    'name' => $att->filename,
                    'size' => $att->file_size,
                    'type' => $att->mime_type,
                ];
            })->toArray();

            if ($paymentRequest->reimbursement && $paymentRequest->reimbursement->attachments) {
                $reimbursementAttachments = $paymentRequest->reimbursement->attachments->map(function ($att) {
                    return [
                        'path' => $att->file_path,
                        'name' => $att->file_name,
                        'size' => $att->file_size,
                        'type' => $att->file_type,
                    ];
                })->toArray();

                $attachments = array_merge($reimbursementAttachments, $attachments);
            }

            DB::beginTransaction();

            $stampMethod = $request->input('stamp_method', 'online');
            $instance = $this->approvalService->createApprovalInstance(
                $paymentRequest->account_set_id,
                '报销付款申请',
                $paymentRequest->id,
                $paymentRequest->submitted_by,
                $attachments,
                true,
                $stampMethod
            );

            $paymentRequest->update([
                'approval_instance_id' => $instance->id,
            ]);

            DB::commit();

            \Log::info('报销付款审批流程创建成功', [
                'payment_request_id' => $paymentRequest->id,
                'instance_id' => $instance->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '付款审批流程已创建',
                'data' => [
                    'payment_request' => $paymentRequest,
                    'instance' => $instance,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建报销付款审批流程失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '创建审批流程失败: ' . $e->getMessage(),
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
            'file' => 'required|file|max:51200',
        ], [
            'payment_request_id.required' => '付款申请ID不能为空',
            'payment_request_id.exists' => '付款申请不存在',
            'file.required' => '请选择要上传的文件',
            'file.file' => '上传的必须是文件',
            'file.max' => '文件大小不能超过50MB',
        ]);

        if ($validator->fails()) {
            \Log::error('上传报销付款附件验证失败', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->except('file'),
                'has_file' => $request->hasFile('file'),
            ]);

            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentAccountSetId = $this->resolveCurrentAccountSetId($request);
        if (!$currentAccountSetId && !$this->isAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套',
            ], 422);
        }

        $paymentRequestQuery = PaymentRequest::query()->where('id', $request->payment_request_id);
        if ($currentAccountSetId) {
            $paymentRequestQuery->where('account_set_id', $currentAccountSetId);
        }
        $paymentRequest = $paymentRequestQuery->first();
        if (!$paymentRequest) {
            return response()->json([
                'success' => false,
                'message' => '付款申请不存在或不属于当前账套',
            ], 404);
        }

        if ($paymentRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待审批状态才能上传附件',
            ], 400);
        }

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;

            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $directory = public_path('payment_requests/' . $paymentRequest->id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'payment_requests/' . $paymentRequest->id . '/' . $filename;

            $attachmentType = $request->input('attachment_type', 'attachment');
            $attachment = PaymentRequestAttachment::create([
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
                'data' => $attachment->load('uploader'),
            ]);
        } catch (\Exception $e) {
            \Log::error('上传付款申请附件失败', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '附件上传失败: ' . $e->getMessage(),
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentAccountSetId = $this->resolveCurrentAccountSetId($request);
        if (!$currentAccountSetId && !$this->isAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套',
            ], 422);
        }

        $attachment = PaymentRequestAttachment::with('paymentRequest')->find($request->id);
        if (!$attachment || !$attachment->paymentRequest) {
            return response()->json([
                'success' => false,
                'message' => '附件不存在',
            ], 404);
        }

        if ($currentAccountSetId && (int) $attachment->paymentRequest->account_set_id !== (int) $currentAccountSetId) {
            return response()->json([
                'success' => false,
                'message' => '附件不属于当前账套',
            ], 404);
        }

        if ($attachment->paymentRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待审批状态才能删除附件',
            ], 400);
        }

        try {
            $filePath = public_path($attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '附件删除成功',
            ]);
        } catch (\Exception $e) {
            \Log::error('删除付款申请附件失败', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '附件删除失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolveCurrentAccountSetId(Request $request): ?int
    {
        $accountSetId = $request->input('current_account_set_id');
        if (!$accountSetId) {
            $accountSetId = $request->header('X-Account-Set-Id');
        }

        return $accountSetId ? (int) $accountSetId : null;
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->role === 'admin';
    }
}
