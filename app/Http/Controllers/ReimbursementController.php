<?php

namespace App\Http\Controllers;

use App\Models\ApprovalInstance;
use App\Models\ApprovalAttachment;
use App\Models\Reimbursement;
use App\Models\ReimbursementAttachment;
use App\Services\ApprovalService;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ReimbursementController extends Controller
{
    use ChecksPermission;

    /**
     * 获取报销列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('reimbursement.view')) {
            return $response;
        }

        try {
            $accountSetId = $this->resolveCurrentAccountSetId($request);
            $user = Auth::user();

            $query = Reimbursement::with(['attachments', 'creator', 'paymentRequest']);
            if ($accountSetId) {
                $query->where('account_set_id', $accountSetId);
            } elseif (!$user || $user->role !== 'admin') {
                $query->whereRaw('1 = 0');
            }

            if ($request->has('applicant') && $request->applicant) {
                $query->where('applicant', 'like', '%' . $request->applicant . '%');
            }

            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            $perPage = $request->input('per_page', 20);
            $reimbursements = $query->orderBy('created_at', 'desc')->paginate($perPage);

            foreach ($reimbursements as $reimbursement) {
                $reimbursement->attachment_count = $reimbursement->attachments->count();
                $reimbursement->payment_request_created = (bool) $reimbursement->paymentRequest;
                $reimbursement->payment_request_status = $reimbursement->paymentRequest
                    ? $reimbursement->paymentRequest->status
                    : null;
            }

            return response()->json([
                'success' => true,
                'data' => $reimbursements,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取报销列表失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 创建报销申请（自动发起审批）
     */
    public function store(Request $request)
    {
        if ($response = $this->checkPermission('reimbursement.create')) {
            return $response;
        }

        try {
            $validator = Validator::make($request->all(), [
                'company_name' => 'required|string',
                'applicant' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'project' => 'required|string',
                'reason' => 'required|string',
                'invoice_number' => 'required|string',
                'current_account_set_id' => 'required|exists:account_sets,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $existingInvoice = Reimbursement::where('account_set_id', $request->current_account_set_id)
                ->where('invoice_number', $request->invoice_number)
                ->where('status', '!=', 'rejected')
                ->first();

            if ($existingInvoice) {
                return response()->json([
                    'success' => false,
                    'message' => '发票号码已存在，请检查是否重复提交',
                ], 422);
            }

            $user = Auth::user();
            DB::beginTransaction();

            $reimbursement = Reimbursement::create([
                'account_set_id' => $request->current_account_set_id,
                'company_name' => $request->company_name,
                'invoice_number' => $request->invoice_number,
                'payment_date' => $request->payment_date,
                'applicant' => $request->applicant,
                'amount' => $request->amount,
                'category' => $request->category,
                'project' => $request->project,
                'received_invoice' => $request->received_invoice,
                'invoice_type' => $request->invoice_type,
                'reason' => $request->reason,
                'invoice_amount' => $request->invoice_amount,
                'tax_rate' => $request->tax_rate,
                'tax_deduction' => $request->tax_deduction,
                'amount_excluding_tax' => $request->amount_excluding_tax,
                'tax_amount' => $request->tax_amount,
                'invoice_date' => $request->invoice_date,
                'record_status' => $request->record_status,
                'accounting_status' => $request->accounting_status,
                'remarks' => $request->remarks,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            $approvalService = new ApprovalService();
            $stampMethod = $request->input('stamp_method', 'online');
            $approvalInstance = $approvalService->createApprovalInstance(
                $request->current_account_set_id,
                '报销申请',
                $reimbursement->id,
                $user->id,
                [],
                true,
                $stampMethod
            );

            $reimbursement->update([
                'approval_flow_id' => $approvalInstance->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '报销申请创建成功，已自动发起审批',
                'data' => $reimbursement->load('approvalInstance'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '创建报销申请失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:51200',
                'reimbursement_id' => 'required|exists:reimbursements,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if (!$this->canAccessCurrentAccountSet($request)) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套',
                ], 422);
            }

            $reimbursementQuery = Reimbursement::query()->where('id', $request->reimbursement_id);
            $accountSetId = $this->resolveCurrentAccountSetId($request);
            if ($accountSetId) {
                $reimbursementQuery->where('account_set_id', $accountSetId);
            }

            $reimbursement = $reimbursementQuery->first();
            if (!$reimbursement) {
                return response()->json([
                    'success' => false,
                    'message' => '报销申请不存在或不属于当前账套',
                ], 404);
            }

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('reimbursements/' . $reimbursement->id, $fileName, 'public');

            $attachment = ReimbursementAttachment::create([
                'reimbursement_id' => $reimbursement->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            // 报销在创建时已生成审批实例，上传附件后需要同步到审批附件表，
            // 否则流程中心审批详情看不到这些附件。
            if (!empty($reimbursement->approval_flow_id)) {
                $instance = ApprovalInstance::find($reimbursement->approval_flow_id);
                if ($instance) {
                    ApprovalAttachment::firstOrCreate(
                        [
                            'instance_id' => $instance->id,
                            'file_path' => $filePath,
                            'file_name' => $file->getClientOriginalName(),
                        ],
                        [
                            'file_size' => $file->getSize(),
                            'file_type' => $file->getClientMimeType(),
                            'uploaded_by' => Auth::id(),
                        ]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => $attachment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上传附件失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 完成提交，创建审批流程（兼容旧入口，幂等处理）
     */
    public function completeSubmission(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reimbursement_id' => 'required|exists:reimbursements,id',
                'current_account_set_id' => 'required|exists:account_sets,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if (!$this->canAccessCurrentAccountSet($request)) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套',
                ], 422);
            }

            $accountSetId = $this->resolveCurrentAccountSetId($request);
            $reimbursementQuery = Reimbursement::with('attachments')
                ->where('id', $request->reimbursement_id);
            if ($accountSetId) {
                $reimbursementQuery->where('account_set_id', $accountSetId);
            }

            $reimbursement = $reimbursementQuery->first();
            if (!$reimbursement) {
                return response()->json([
                    'success' => false,
                    'message' => '报销申请不存在或不属于当前账套',
                ], 404);
            }

            $user = Auth::user();

            // 已有进行中/已完成审批实例时直接返回，避免重复创建。
            if ($reimbursement->approval_flow_id) {
                $existingInstance = ApprovalInstance::find($reimbursement->approval_flow_id);
                if (
                    $existingInstance &&
                    (int) $existingInstance->business_id === (int) $reimbursement->id &&
                    in_array($existingInstance->business_type, ['报销申请', 'reimbursement'], true) &&
                    in_array($existingInstance->status, ['pending', 'approved'], true)
                ) {
                    return response()->json([
                        'success' => true,
                        'message' => '审批流程已存在',
                        'data' => [
                            'reimbursement' => $reimbursement,
                            'approval_instance' => $existingInstance,
                        ],
                    ]);
                }
            }

            $attachments = [];
            foreach ($reimbursement->attachments as $attachment) {
                $attachments[] = [
                    'path' => $attachment->file_path,
                    'name' => $attachment->file_name,
                    'size' => $attachment->file_size,
                    'type' => $attachment->file_type,
                ];
            }

            $approvalService = new ApprovalService();
            $stampMethod = $request->input('stamp_method', 'online');
            $approvalInstance = $approvalService->createApprovalInstance(
                $reimbursement->account_set_id,
                '报销申请',
                $reimbursement->id,
                $user->id,
                $attachments,
                true,
                $stampMethod
            );

            $reimbursement->update([
                'approval_flow_id' => $approvalInstance->id,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => '审批流程创建成功',
                'data' => [
                    'reimbursement' => $reimbursement,
                    'approval_instance' => $approvalInstance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建审批流程失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 删除报销申请
     */
    public function destroy(Request $request, $id)
    {
        if ($response = $this->checkPermission('reimbursement.delete')) {
            return $response;
        }

        try {
            if (!$this->canAccessCurrentAccountSet($request)) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套',
                ], 422);
            }

            $accountSetId = $this->resolveCurrentAccountSetId($request);
            $reimbursementQuery = Reimbursement::with('attachments')->where('id', $id);
            if ($accountSetId) {
                $reimbursementQuery->where('account_set_id', $accountSetId);
            }

            $reimbursement = $reimbursementQuery->first();
            if (!$reimbursement) {
                return response()->json([
                    'success' => false,
                    'message' => '报销申请不存在或不属于当前账套',
                ], 404);
            }

            if ($reimbursement->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '只有待审批状态的申请才能删除',
                ], 400);
            }

            if ($reimbursement->approval_flow_id) {
                $instance = ApprovalInstance::find($reimbursement->approval_flow_id);
                if ($instance && !in_array($instance->status, ['rejected', 'withdrawn'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => '已进入审批流程的报销申请不能删除',
                    ], 400);
                }
            }

            foreach ($reimbursement->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $reimbursement->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取详情
     */
    public function show(Request $request, $id)
    {
        if ($response = $this->checkPermission('reimbursement.view')) {
            return $response;
        }

        try {
            if (!$this->canAccessCurrentAccountSet($request)) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套',
                ], 422);
            }

            $accountSetId = $this->resolveCurrentAccountSetId($request);
            $reimbursementQuery = Reimbursement::with(['attachments', 'creator'])
                ->where('id', $id);
            if ($accountSetId) {
                $reimbursementQuery->where('account_set_id', $accountSetId);
            }

            $reimbursement = $reimbursementQuery->first();
            if (!$reimbursement) {
                return response()->json([
                    'success' => false,
                    'message' => '报销申请不存在或不属于当前账套',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $reimbursement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取详情失败: ' . $e->getMessage(),
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

    private function canAccessCurrentAccountSet(Request $request): bool
    {
        $accountSetId = $this->resolveCurrentAccountSetId($request);
        $user = $request->user();

        return (bool) $accountSetId || ($user && $user->role === 'admin');
    }
}
