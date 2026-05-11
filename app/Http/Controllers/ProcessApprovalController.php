<?php

namespace App\Http\Controllers;

use App\Models\ProcessApproval;
use App\Models\ProcessAttachment;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Traits\ChecksPermission;

class ProcessApprovalController extends Controller
{
    use ChecksPermission;
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }
    /**
     * 获取流程列表
     */
    public function index(Request $request)
    {
        // 汇总申请查看权限
        if ($response = $this->checkPermission('process_approval.view')) {
            return $response;
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
            // 仅当已创建审批流时，才视为“已发起付款”。
            // 避免“付款申请记录已创建但审批流创建失败”导致流程卡住。
            $process->has_payment_request = \App\Models\PaymentRequest::where('insurance_summary_id', $process->id)
                ->whereNotNull('approval_instance_id')
                ->exists();
            return $process;
        });

        return response()->json([
            'success' => true,
            'data' => $processes
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
        // 汇总申请创建权限
        if ($response = $this->checkPermission('process_approval.create')) {
            return $response;
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:projects,id',
            'description' => 'nullable|string',
        ]);

        // 从请求参数中获取账套ID
        $accountSetId = $request->input('current_account_set_id');
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
                'month' => now()->format('Y-m'), // 自动使用当前年月
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
        // 汇总申请编辑权限
        if ($response = $this->checkPermission('process_approval.edit')) {
            return $response;
        }

        $process = ProcessApproval::findOrFail($id);

        if ($process->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只有草稿状态才能上传附件'
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

        if ($process->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只有草稿状态才能删除附件'
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

            $attachment = ProcessAttachment::where('process_approval_id', $id)
                ->where('id', $attachmentId)
                ->firstOrFail();

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
        // 汇总申请提交权限
        if ($response = $this->checkPermission('process_approval.submit')) {
            return $response;
        }
        $process = ProcessApproval::with('attachments')->findOrFail($id);

        if ($process->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只有草稿状态才能提交'
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

            // 使用审批服务创建审批实例（跳过发起人审批）
            $instance = $this->approvalService->createApprovalInstance(
                $process->account_set_id,
                '保险汇总', // 业务类型：保险汇总表审批
                $process->id,        // 业务ID：流程ID
                $request->user()->id,
                $attachments,
                true, // 跳过发起人审批
                $stampMethod // 盖章方式
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
        // 汇总申请删除权限
        if ($response = $this->checkPermission('process_approval.delete')) {
            return $response;
        }
        $process = ProcessApproval::findOrFail($id);

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
