<?php

namespace App\Http\Controllers;

use App\Models\TravelApplication;
use App\Models\TravelApplicationAttachment;
use App\Models\ApprovalInstance;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

class TravelApplicationController extends Controller
{
    use ChecksPermission;
    /**
     * 获取差旅申请列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('travel.view')) {
            return $response;
        }
        
        try {
            $accountSetId = $request->input('current_account_set_id');
            $user = Auth::user();

            $query = TravelApplication::with(['attachments', 'creator'])
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
            $applications = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // 添加附件数量
            foreach ($applications as $application) {
                $application->attachment_count = $application->attachments->count();
            }

            return response()->json([
                'success' => true,
                'data' => $applications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取差旅申请列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建差旅申请
     */
    public function store(Request $request)
    {
        if ($response = $this->checkPermission('travel.create')) {
            return $response;
        }
        
        try {
            $validator = Validator::make($request->all(), [
                'department' => 'required|string',
                'applicant' => 'required|string',
                'destination' => 'required|string',
                'reason' => 'required|string',
                'start_time' => 'required|date',
                'end_time' => 'required|date',
                'advance_amount' => 'required|numeric|min:0.01',
                'current_account_set_id' => 'required|exists:account_sets,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();

            $application = TravelApplication::create([
                'account_set_id' => $request->current_account_set_id,
                'department' => $request->department,
                'apply_date' => $request->apply_date,
                'applicant' => $request->applicant,
                'destination' => $request->destination,
                'reason' => $request->reason,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'days' => $request->days ?? 0,
                'advance_amount' => $request->advance_amount,
                'payment_date' => $request->payment_date,
                'remarks' => $request->remarks,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '差旅申请创建成功',
                'data' => $application
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建差旅申请失败: ' . $e->getMessage()
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
                'travel_application_id' => 'required|exists:travel_applications,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $travelApplicationId = $request->travel_application_id;

            // 生成文件名
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;

            // 存储文件
            $path = $file->storeAs('travel_applications/' . $travelApplicationId, $filename, 'public');

            // 保存附件记录
            $attachment = TravelApplicationAttachment::create([
                'travel_application_id' => $travelApplicationId,
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
     * 完成提交（创建审批流程）
     */
    public function completeSubmission(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'travel_application_id' => 'required|exists:travel_applications,id',
                'current_account_set_id' => 'required|exists:account_sets,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $application = TravelApplication::with('attachments')->findOrFail($request->travel_application_id);
            $user = Auth::user();

            // 准备附件数组
            $attachments = [];
            foreach ($application->attachments as $attachment) {
                $attachments[] = [
                    'path' => $attachment->file_path,
                    'name' => $attachment->file_name,
                    'size' => $attachment->file_size,
                    'type' => $attachment->file_type,
                ];
            }

            // 创建审批流程
            $approvalService = new ApprovalService();
            $stampMethod = $request->input('stamp_method', 'online'); // 盖章方式
            $approvalInstance = $approvalService->createApprovalInstance(
                $request->current_account_set_id,  // accountSetId
                'travel_application',               // businessType
                $application->id,                   // businessId
                $user->id,                         // createdBy
                $attachments,                       // attachments
                false,                             // skipInitiator
                $stampMethod                       // stampMethod - 盖章方式
            );

            // 更新申请的审批流程ID
            $application->update([
                'approval_flow_id' => $approvalInstance->id,
                'status' => 'in_approval'
            ]);

            return response()->json([
                'success' => true,
                'message' => '差旅申请已提交审批',
                'data' => [
                    'application' => $application,
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
     * 获取详情
     */
    public function show($id)
    {
        try {
            $application = TravelApplication::with(['attachments', 'creator'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $application
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取详情失败: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * 删除申请
     */
    public function destroy($id)
    {
        if ($response = $this->checkPermission('travel.delete')) {
            return $response;
        }
        
        try {
            $application = TravelApplication::findOrFail($id);

            // 只有待审批状态才能删除
            if ($application->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '只有待审批状态的申请才能删除'
                ], 403);
            }

            // 删除关联的附件文件
            foreach ($application->attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    Storage::disk('public')->delete($attachment->file_path);
                }
            }

            // 删除附件记录
            $application->attachments()->delete();

            // 删除申请
            $application->delete();

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

