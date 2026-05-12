<?php

namespace App\Http\Controllers;

use App\Models\DocumentDelivery;
use App\Models\DocumentDeliveryAttachment;
use App\Services\PendingTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DocumentDeliveryController extends Controller
{
    /**
     * 获取交付记录列表
     */
    public function index(Request $request)
    {
        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $query = DocumentDelivery::with(['project', 'submitter', 'attachments'])
                ->where('account_set_id', $accountSetId);

            // 筛选条件
            if ($request->filled('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            if ($request->filled('delivery_cycle')) {
                $query->where('delivery_cycle', $request->input('delivery_cycle'));
            }

            if ($request->filled('delivery_method')) {
                $query->where('delivery_method', $request->input('delivery_method'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('delivery_period')) {
                $query->where('delivery_period', $request->input('delivery_period'));
            }

            // 按交付期间倒序，最新的在前
            $deliveries = $query->orderBy('delivery_period', 'desc')
                               ->orderBy('created_at', 'desc')
                               ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $deliveries
            ]);

        } catch (\Exception $e) {
            Log::error('获取交付记录列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取单个交付记录详情
     */
    public function show($id)
    {
        try {
            $delivery = DocumentDelivery::with([
                'project', 
                'submitter', 
                'completer',
                'attachments.uploader'
            ])->find($id);

            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => '交付记录不存在'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $delivery
            ]);

        } catch (\Exception $e) {
            Log::error('获取交付记录详情失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 下载附件
     */
    public function downloadAttachment($deliveryId, $attachmentId)
    {
        try {
            $attachment = DocumentDeliveryAttachment::where('delivery_id', $deliveryId)
                ->where('id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => '附件不存在'
                ], 404);
            }

            $filePath = public_path($attachment->file_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => '文件不存在: ' . $filePath
                ], 404);
            }

            $downloadName = $attachment->filename ?: basename($filePath);
            $downloadName = trim(str_replace(['/', '\\'], '-', $downloadName));
            $downloadName = preg_replace('/[\x00-\x1F\x7F]/u', '', $downloadName);
            if ($downloadName === '') {
                $downloadName = 'attachment_' . $attachmentId;
            }

            $fileSize = @filesize($filePath) ?: null;
            $mimeType = $attachment->mime_type ?: (mime_content_type($filePath) ?: 'application/octet-stream');

            // 清空已有输出缓冲，避免下载内容前出现额外字节导致文件损坏
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
            }

            return response()->streamDownload(function () use ($filePath) {
                $handle = fopen($filePath, 'rb');
                if ($handle) {
                    while (!feof($handle)) {
                        echo fread($handle, 8192);
                    }
                    fclose($handle);
                }
            }, $downloadName, array_filter([
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize,
                'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Accept-Ranges' => 'bytes',
            ]));
        } catch (\Exception $e) {
            Log::error('下载附件失败', [
                'delivery_id' => $deliveryId,
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '下载失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交交付（快递方式）
     */
    public function submitExpress(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'express_number' => 'required|string|max:100',
                'express_date' => 'required|date',
                'submitted_documents' => 'required|string',
                'remarks' => 'nullable|string',
            ], [
                'express_number.required' => '请输入快递单号',
                'express_date.required' => '请选择寄出日期',
                'submitted_documents.required' => '请填写资料说明',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $delivery = DocumentDelivery::find($id);

            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => '交付记录不存在'
                ], 404);
            }

            if ($delivery->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录已提交，无法重复提交'
                ], 422);
            }

            if ($delivery->delivery_method !== 'express') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录不是快递交付方式'
                ], 422);
            }

            $delivery->update([
                'express_number' => $request->express_number,
                'express_date' => $request->express_date,
                'submitted_documents' => $request->submitted_documents,
                'remarks' => $request->input('remarks'),
                'status' => 'submitted',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
            ]);

            // 检查并完成待办任务
            PendingTaskService::checkAndCompleteDocumentDeliveryTask($delivery);

            return response()->json([
                'success' => true,
                'message' => '快递交付提交成功',
                'data' => $delivery->load(['project', 'submitter'])
            ]);

        } catch (\Exception $e) {
            Log::error('提交快递交付失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交交付（电子方式）
     */
    public function submitElectronic(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'submitted_documents' => 'nullable|string',
                'remarks' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $delivery = DocumentDelivery::find($id);

            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => '交付记录不存在'
                ], 404);
            }

            if ($delivery->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录已提交，无法重复提交'
                ], 422);
            }

            if ($delivery->delivery_method !== 'electronic') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录不是电子推送交付方式'
                ], 422);
            }

            // 检查是否有附件
            $attachmentCount = $delivery->attachments()->count();
            if ($attachmentCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '电子交付至少需要上传一个附件'
                ], 422);
            }

            $delivery->update([
                'submitted_documents' => $request->input('submitted_documents'),
                'remarks' => $request->input('remarks'),
                'status' => 'submitted',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
            ]);

            // 检查并完成待办任务
            PendingTaskService::checkAndCompleteDocumentDeliveryTask($delivery);

            return response()->json([
                'success' => true,
                'message' => '电子交付提交成功',
                'data' => $delivery->load(['project', 'submitter', 'attachments'])
            ]);

        } catch (\Exception $e) {
            Log::error('提交电子交付失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request, $id)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:51200', // 50MB
            ]);

            $delivery = DocumentDelivery::find($id);
            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => '交付记录不存在'
                ], 404);
            }

            if ($delivery->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录已提交，无法上传附件'
                ], 422);
            }

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            
            // 保存文件到 public 目录
            $directory = public_path('document_deliveries/' . $id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'document_deliveries/' . $id . '/' . $filename;

            // 保存附件记录
            $attachment = DocumentDeliveryAttachment::create([
                'delivery_id' => $id,
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => $attachment
            ]);

        } catch (\Exception $e) {
            Log::error('附件上传失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '附件上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除附件
     */
    public function deleteAttachment($deliveryId, $attachmentId)
    {
        try {
            $attachment = DocumentDeliveryAttachment::where('delivery_id', $deliveryId)
                ->where('id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => '附件不存在'
                ], 404);
            }

            // 检查交付记录状态
            $delivery = $attachment->delivery;
            if ($delivery && $delivery->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录已提交，无法删除附件'
                ], 422);
            }

            // 删除物理文件
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
            Log::error('附件删除失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '附件删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 标记为完成
     */
    public function markAsCompleted(Request $request, $id)
    {
        try {
            $delivery = DocumentDelivery::find($id);

            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => '交付记录不存在'
                ], 404);
            }

            if ($delivery->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => '只有已提交的记录才能标记为完成'
                ], 422);
            }

            $delivery->update([
                'status' => 'completed',
                'completed_by' => $request->user()->id,
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '已标记为完成',
                'data' => $delivery
            ]);

        } catch (\Exception $e) {
            Log::error('标记完成失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '操作失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取我的待办交付
     */
    public function getMyPending(Request $request)
    {
        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            $userId = $request->user()->id;
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 获取用户负责的项目的待办交付
            $deliveries = DocumentDelivery::with(['project', 'attachments'])
                ->where('account_set_id', $accountSetId)
                ->where('status', 'pending')
                ->whereHas('project', function($query) use ($userId) {
                    // 这里可以根据实际业务逻辑筛选用户负责的项目
                    // 暂时返回所有待办
                })
                ->orderBy('delivery_period', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $deliveries
            ]);

        } catch (\Exception $e) {
            Log::error('获取待办交付失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
