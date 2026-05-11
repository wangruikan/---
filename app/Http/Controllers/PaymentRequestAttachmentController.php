<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\PaymentRequestAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentRequestAttachmentController extends Controller
{
    /**
     * 上传附件（用于补传）
     */
    public function upload(Request $request)
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
        if (!$paymentRequest || !$paymentRequest->canSupplementAttachment($request->user())) {
            return response()->json([
                'success' => false,
                'message' => '当前申请不允许候补附件上传或您无权限操作'
            ], 403);
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

            // 创建附件记录（补传附件类型为 supplement）
            $attachment = PaymentRequestAttachment::create([
                'payment_request_id' => $paymentRequest->id,
                'filename' => $originalName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'attachment_type' => 'supplement',
                'uploaded_by' => $request->user()->id,
            ]);

            Log::info('补传附件上传成功', [
                'payment_request_id' => $paymentRequest->id,
                'filename' => $originalName,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => $attachment->load('uploader')
            ]);
        } catch (\Exception $e) {
            Log::error('上传补传附件失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '附件上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取附件列表
     */
    public function index(Request $request)
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

        try {
            $paymentRequest = PaymentRequest::find($request->payment_request_id);
            if (!$paymentRequest || !$paymentRequest->canSupplementAttachment($request->user())) {
                return response()->json([
                    'success' => false,
                    'message' => '当前申请不允许查看候补附件或您无权限操作'
                ], 403);
            }

            $attachments = PaymentRequestAttachment::with('uploader')
                ->where('payment_request_id', $request->payment_request_id)
                ->where('attachment_type', 'supplement')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $attachments
            ]);
        } catch (\Exception $e) {
            Log::error('获取附件列表失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '获取附件列表失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除附件
     */
    public function delete(Request $request)
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

        $attachment = PaymentRequestAttachment::with('paymentRequest')->find($request->id);
        if (!$attachment || !$attachment->paymentRequest || !$attachment->paymentRequest->canSupplementAttachment($request->user())) {
            return response()->json([
                'success' => false,
                'message' => '当前申请不允许删除候补附件或您无权限操作'
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

            Log::info('补传附件删除成功', [
                'attachment_id' => $attachment->id,
                'payment_request_id' => $attachment->payment_request_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除补传附件失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '附件删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 下载附件
     */
    public function download(Request $request, $id)
    {
        try {
            \Log::info('开始下载付款申请附件', ['attachment_id' => $id]);

            $attachment = PaymentRequestAttachment::findOrFail($id);
            \Log::info('附件信息', ['file_path' => $attachment->file_path, 'filename' => $attachment->filename]);

            if (!$attachment->file_path) {
                \Log::error('附件文件路径为空');
                return response()->json([
                    'success' => false,
                    'message' => '文件不存在'
                ], 404);
            }

            $filePath = public_path($attachment->file_path);

            if (!file_exists($filePath)) {
                \Log::error('文件不存在', [
                    'attachment_id' => $id,
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
                $downloadName = 'attachment_' . $id . '.pdf';
            }

            \Log::info('开始发送文件', [
                'attachment_id' => $id,
                'download_name' => $downloadName,
                'file_path' => $filePath,
            ]);

            return response()->download($filePath, $downloadName);
        } catch (\Exception $e) {
            \Log::error('下载附件失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attachment_id' => $id,
            ]);
            return response()->json([
                'success' => false,
                'message' => '下载失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
