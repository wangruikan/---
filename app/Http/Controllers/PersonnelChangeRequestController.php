<?php

namespace App\Http\Controllers;

use App\Models\PersonnelChangeRequest;
use App\Models\PersonnelChangeRequestAttachment;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

class PersonnelChangeRequestController extends Controller
{
    use ChecksPermission;
    
    /**
     * 获取人员变动申请列表
     */
    public function index(Request $request)
    {
        // 权限检查：人员汇总申�?查看
        if ($response = $this->checkPermission('personnel_change.view')) {
            return $response;
        }
        
        try {
            $accountSetId = $request->input('current_account_set_id');
            $user = Auth::user();

            $query = PersonnelChangeRequest::with(['project', 'creator', 'attachments'])
                ->where('account_set_id', $accountSetId);

            // 筛选条�?
            if ($request->has('month') && $request->month) {
                $query->where('month', $request->month);
            }

            if ($request->has('project_id') && $request->project_id) {
                $query->where('project_id', $request->project_id);
            }

            if ($request->has('change_type') && $request->change_type) {
                $query->where('change_type', $request->change_type);
            }

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // 分页
            $perPage = $request->input('per_page', 20);
            $requests = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // 添加附件数量和人员数�?
            foreach ($requests as $req) {
                $req->attachment_count = $req->attachments->count();
                $req->personnel_count = is_array($req->personnel_list) ? count($req->personnel_list) : 0;
            }

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取人员变动申请列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取详情
     */
    public function show($id)
    {
        // 权限检查：人员汇总申�?查看
        if ($response = $this->checkPermission('personnel_change.view')) {
            return $response;
        }
        
        try {
            $request = PersonnelChangeRequest::with(['project', 'creator', 'attachments'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $request
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取详情失败: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request)
    {
        // 权限检查：人员汇总申�?新增
        if ($response = $this->checkPermission('personnel_change.create')) {
            return $response;
        }
        
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:51200', // 最�?0MB
                'personnel_change_request_id' => 'required|exists:personnel_change_requests,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $requestId = $request->personnel_change_request_id;

            // 生成文件�?
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;

            // 存储文件
            $path = $file->storeAs('personnel_change_requests/' . $requestId, $filename, 'public');

            // 保存附件记录
            $attachment = PersonnelChangeRequestAttachment::create([
                'personnel_change_request_id' => $requestId,
                'file_name' => $originalName,
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
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
     * 完成提交（创建审批流程）- 跳过第一节点
     */
    public function completeSubmission(Request $request)
    {
        // 权限检查：人员汇总申�?新增
        if ($response = $this->checkPermission('personnel_change.create')) {
            return $response;
        }
        
        try {
            $validator = Validator::make($request->all(), [
                'personnel_change_request_id' => 'required|exists:personnel_change_requests,id',
                'current_account_set_id' => 'required|exists:account_sets,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $changeRequest = PersonnelChangeRequest::with('attachments')->findOrFail($request->personnel_change_request_id);
            $user = Auth::user();

            // 准备附件数组
            $attachments = [];
            foreach ($changeRequest->attachments as $attachment) {
                $attachments[] = [
                    'path' => $attachment->file_path,
                    'name' => $attachment->file_name,
                    'size' => $attachment->file_size,
                    'type' => $attachment->file_type,
                ];
            }

            // 获取盖章方式，默认线上
            $stampMethod = $request->input('stamp_method', 'online');

            // 创建审批流程 - 跳过第一节点（经办）
            $approvalService = new ApprovalService();
            $approvalInstance = $approvalService->createApprovalInstance(
                $request->current_account_set_id,  // accountSetId
                'personnel_change',                 // businessType
                $changeRequest->id,                 // businessId
                $user->id,                         // createdBy
                $attachments,                       // attachments
                true,                              // skipInitiator - 跳过发起�?
                $stampMethod                       // stampMethod - 盖章方式
            );

            // 更新申请的审批流程ID
            $changeRequest->update([
                'approval_flow_id' => $approvalInstance->id,
                'status' => 'in_approval'
            ]);

            return response()->json([
                'success' => true,
                'message' => '人员变动申请已提交审批',
                'data' => [
                    'request' => $changeRequest,
                    'approval_instance' => $approvalInstance
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '提交审批失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除申请
     */
    public function destroy($id)
    {
        // 权限检查：人员汇总申�?删除
        if ($response = $this->checkPermission('personnel_change.delete')) {
            return $response;
        }
        
        try {
            $changeRequest = PersonnelChangeRequest::findOrFail($id);

            // 只有待审批状态才能删�?
            if ($changeRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '只有待审批状态的申请才能删除'
                ], 403);
            }

            // 删除关联的附件文�?
            foreach ($changeRequest->attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    Storage::disk('public')->delete($attachment->file_path);
                }
            }

            // 删除附件记录
            $changeRequest->attachments()->delete();

            // 删除申请
            $changeRequest->delete();

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
}

