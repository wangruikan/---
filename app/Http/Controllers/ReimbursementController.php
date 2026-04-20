<?php

namespace App\Http\Controllers;

use App\Models\Reimbursement;
use App\Models\ReimbursementAttachment;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

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
            $accountSetId = $request->input('current_account_set_id');
            $user = Auth::user();

            $query = Reimbursement::with(['attachments', 'creator', 'paymentRequest'])
                ->where('account_set_id', $accountSetId);

            // 筛选条件
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

            // 分页
            $perPage = $request->input('per_page', 20);
            $reimbursements = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // 添加附件数量和付款申请状态
            foreach ($reimbursements as $reimbursement) {
                $reimbursement->attachment_count = $reimbursement->attachments->count();
                $reimbursement->payment_request_created = $reimbursement->paymentRequest ? true : false;
                $reimbursement->payment_request_status = $reimbursement->paymentRequest ? $reimbursement->paymentRequest->status : null;
            }

            return response()->json([
                'success' => true,
                'data' => $reimbursements
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取报销列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建报销申请
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
                'invoice_number' => 'required|string', // 发票号码必填
                'current_account_set_id' => 'required|exists:account_sets,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 校验发票号码唯一性（同一账套内）
            // 检查发票号码是否已存在（排除已驳回的记录）
            $existingInvoice = Reimbursement::where('account_set_id', $request->current_account_set_id)
                ->where('invoice_number', $request->invoice_number)
                ->where('status', '!=', 'rejected') // 排除已驳回的记录
                ->first();
            
            if ($existingInvoice) {
                return response()->json([
                    'success' => false,
                    'message' => '发票号码已存在，请检查是否重复提交'
                ], 422);
            }

            $user = Auth::user();

            DB::beginTransaction();
            
            try {
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

                // 自动发起审批流程
                $approvalService = new ApprovalService();
                $stampMethod = $request->input('stamp_method', 'online'); // 盖章方式
                $approvalInstance = $approvalService->createApprovalInstance(
                    $request->current_account_set_id,
                    '报销申请',                // 业务类型（中文）
                    $reimbursement->id,        // 业务ID
                    $user->id,                 // 发起人
                    [],                        // 附件（后续上传）
                    true,                      // 跳过发起人审批
                    $stampMethod               // 盖章方式
                );
                
                // 更新报销记录的审批实例ID
                $reimbursement->update([
                    'approval_flow_id' => $approvalInstance->id
                ]);
                
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => '报销申请创建成功，已自动发起审批',
                    'data' => $reimbursement->load('approvalInstance')
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建报销申请失败: ' . $e->getMessage()
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
                'file' => 'required|file|max:51200', // 最大50MB
                'reimbursement_id' => 'required|exists:reimbursements,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reimbursement = Reimbursement::findOrFail($request->reimbursement_id);
            $file = $request->file('file');

            // 生成文件路径
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('reimbursements/' . $reimbursement->id, $fileName, 'public');

            // 创建附件记录
            $attachment = ReimbursementAttachment::create([
                'reimbursement_id' => $reimbursement->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => $attachment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上传附件失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 完成提交，创建审批流程
     */
    public function completeSubmission(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reimbursement_id' => 'required|exists:reimbursements,id',
                'current_account_set_id' => 'required|exists:account_sets,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reimbursement = Reimbursement::with('attachments')->findOrFail($request->reimbursement_id);
            $user = Auth::user();

            // 准备附件数据（将报销附件转换为审批附件格式）
            $attachments = [];
            foreach ($reimbursement->attachments as $attachment) {
                $attachments[] = [
                    'path' => $attachment->file_path,
                    'name' => $attachment->file_name,
                    'size' => $attachment->file_size,
                    'type' => $attachment->file_type,
                ];
            }

            // 使用统一的审批服务创建审批流程
            $approvalService = new ApprovalService();
            $stampMethod = $request->input('stamp_method', 'online'); // 盖章方式
            $approvalInstance = $approvalService->createApprovalInstance(
                $request->current_account_set_id,
                '报销申请',
                $reimbursement->id,
                $user->id,
                $attachments,
                true, // 跳过发起人审批，直接从第二个审批节点开始
                $stampMethod // 盖章方式
            );

            // 更新报销申请的审批流程ID
            $reimbursement->update([
                'approval_flow_id' => $approvalInstance->id,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => '审批流程创建成功',
                'data' => [
                    'reimbursement' => $reimbursement,
                    'approval_instance' => $approvalInstance
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建审批流程失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除报销申请
     */
    public function destroy($id)
    {
        if ($response = $this->checkPermission('reimbursement.delete')) {
            return $response;
        }
        
        try {
            $reimbursement = Reimbursement::findOrFail($id);

            // 只有待审批状态才能删除
            if ($reimbursement->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '只有待审批状态的申请才能删除'
                ], 400);
            }

            // 删除相关附件文件
            foreach ($reimbursement->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // 软删除
            $reimbursement->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取详情
     */
    public function show($id)
    {
        if ($response = $this->checkPermission('reimbursement.view')) {
            return $response;
        }
        
        try {
            $reimbursement = Reimbursement::with(['attachments', 'creator'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $reimbursement
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取详情失败: ' . $e->getMessage()
            ], 500);
        }
    }
}

