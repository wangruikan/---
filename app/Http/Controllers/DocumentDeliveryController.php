<?php

namespace App\Http\Controllers;

use App\Models\DocumentDelivery;
use App\Models\DocumentDeliveryAttachment;
use App\Models\DocumentDeliveryItem;
use App\Services\DocumentDeliveryService;
use App\Services\DynamicScheduledTaskService;
use App\Services\PendingTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DocumentDeliveryController extends Controller
{
    /**
     * 获取交付记录列表
     */
    public function index(Request $request)
    {
        try {
            $accountSetId = (int) $request->input('current_account_set_id');

            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            app(DynamicScheduledTaskService::class)->syncDocumentDeliveries(
                $accountSetId,
                $request->input('delivery_period')
            );

            $query = DocumentDelivery::where('account_set_id', $accountSetId);

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
                $query->where('display_month', $request->input('delivery_period'));
            }

            $deliveries = $query->orderBy('display_month', 'desc')
                ->orderBy('delivery_period', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            $deliveries->setCollection(
                $deliveries->getCollection()
                    ->map(function (DocumentDelivery $delivery) {
                        return $this->buildDeliveryResponse($delivery->id, ['project', 'submitter']);
                    })
                    ->filter()
                    ->values()
            );

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
            $delivery = $this->buildDeliveryResponse((int) $id);

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
                'delivery_item_id' => 'required|integer',
                'document_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
                'express_number' => 'required|string|max:100',
                'express_date' => 'required|date',
                'submitted_documents' => 'nullable|string',
                'remarks' => 'nullable|string',
            ], [
                'delivery_item_id.required' => '请选择交付资料',
                'document_period.required' => '请选择所属期',
                'document_period.regex' => '所属期格式不正确',
                'express_number.required' => '请输入快递单号',
                'express_date.required' => '请选择寄出日期',
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

            if ($delivery->delivery_method !== 'express') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录不是快递交付方式'
                ], 422);
            }

            $item = $this->findDeliveryItem($delivery, (int) $request->input('delivery_item_id'));
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => '交付资料不存在'
                ], 404);
            }

            if ($item->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该资料已提交，无法重复提交'
                ], 422);
            }

            $item->update([
                'express_number' => $request->input('express_number'),
                'express_date' => $request->input('express_date'),
                'submitted_documents' => $request->input('submitted_documents') ?: $item->document_name,
                'remarks' => $request->input('remarks'),
                'status' => 'submitted',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
            ]);

            $delivery->update([
                'document_period' => $request->input('document_period'),
            ]);

            $delivery = app(DocumentDeliveryService::class)->refreshDeliverySummary($delivery->fresh());
            PendingTaskService::checkAndCompleteDocumentDeliveryTask($delivery);

            return response()->json([
                'success' => true,
                'message' => '资料提交成功',
                'data' => $this->buildDeliveryResponse($delivery->id)
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
                'delivery_item_id' => 'required|integer',
                'document_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
                'submitted_documents' => 'nullable|string',
                'remarks' => 'nullable|string',
            ], [
                'delivery_item_id.required' => '请选择交付资料',
                'document_period.required' => '请选择所属期',
                'document_period.regex' => '所属期格式不正确',
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

            if ($delivery->delivery_method !== 'electronic') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录不是电子推送交付方式'
                ], 422);
            }

            $item = $this->findDeliveryItem($delivery, (int) $request->input('delivery_item_id'));
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => '交付资料不存在'
                ], 404);
            }

            if ($item->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该资料已提交，无法重复提交'
                ], 422);
            }

            $attachmentCount = DocumentDeliveryAttachment::where('delivery_id', $delivery->id)
                ->where('delivery_item_id', $item->id)
                ->count();

            if ($attachmentCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '该资料至少需要上传一个附件'
                ], 422);
            }

            $item->update([
                'submitted_documents' => $request->input('submitted_documents') ?: $item->document_name,
                'remarks' => $request->input('remarks'),
                'status' => 'submitted',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
            ]);

            $delivery->update([
                'document_period' => $request->input('document_period'),
            ]);

            $delivery = app(DocumentDeliveryService::class)->refreshDeliverySummary($delivery->fresh());
            PendingTaskService::checkAndCompleteDocumentDeliveryTask($delivery);

            return response()->json([
                'success' => true,
                'message' => '资料提交成功',
                'data' => $this->buildDeliveryResponse($delivery->id)
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
                'file' => 'required|file|max:51200',
                'delivery_item_id' => 'required|integer',
            ], [
                'delivery_item_id.required' => '请选择交付资料',
            ]);

            $delivery = DocumentDelivery::find($id);
            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => '交付记录不存在'
                ], 404);
            }

            if ($delivery->delivery_method !== 'electronic') {
                return response()->json([
                    'success' => false,
                    'message' => '只有电子交付支持上传附件'
                ], 422);
            }

            $item = $this->findDeliveryItem($delivery, (int) $request->input('delivery_item_id'));
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => '交付资料不存在'
                ], 404);
            }

            if ($item->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该资料已提交，无法继续上传附件'
                ], 422);
            }

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();

            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $directory = public_path('document_deliveries/' . $id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'document_deliveries/' . $id . '/' . $filename;

            $attachment = DocumentDeliveryAttachment::create([
                'delivery_id' => $id,
                'delivery_item_id' => $item->id,
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

            $attachment->loadMissing(['delivery', 'item']);

            if ($attachment->item && $attachment->item->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该资料已提交，无法删除附件'
                ], 422);
            }

            if (!$attachment->item && $attachment->delivery && $attachment->delivery->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => '该记录已提交，无法删除附件'
                ], 422);
            }

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

            $service = app(DocumentDeliveryService::class);
            $service->syncDeliveryItems($delivery);
            $delivery->load('items');

            if ($delivery->items->where('status', 'pending')->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => '还有未交付资料，不能标记完成'
                ], 422);
            }

            if ($delivery->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => '只有全部已提交的记录才能标记为完成'
                ], 422);
            }

            DB::transaction(function () use ($delivery, $request, $service) {
                foreach ($delivery->items as $item) {
                    if ($item->status === 'completed') {
                        continue;
                    }

                    $item->update([
                        'status' => 'completed',
                        'completed_by' => $request->user()->id,
                        'completed_at' => now(),
                    ]);
                }

                $service->refreshDeliverySummary($delivery->fresh());
            });

            return response()->json([
                'success' => true,
                'message' => '已标记为完成',
                'data' => $this->buildDeliveryResponse((int) $id)
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
            $accountSetId = (int) $request->input('current_account_set_id');
            $userId = $request->user()->id;

            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            app(DynamicScheduledTaskService::class)->syncDocumentDeliveries(
                $accountSetId,
                $request->input('delivery_period')
            );

            $deliveries = DocumentDelivery::where('account_set_id', $accountSetId)
                ->where('status', 'pending')
                ->whereHas('project', function ($query) use ($userId) {
                })
                ->orderBy('display_month', 'desc')
                ->orderBy('delivery_period', 'desc')
                ->get()
                ->map(function (DocumentDelivery $delivery) {
                    return $this->buildDeliveryResponse($delivery->id, ['project', 'submitter']);
                })
                ->filter()
                ->values();

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

    private function findDeliveryItem(DocumentDelivery $delivery, int $itemId): ?DocumentDeliveryItem
    {
        app(DocumentDeliveryService::class)->syncDeliveryItems($delivery);

        return DocumentDeliveryItem::where('delivery_id', $delivery->id)
            ->where('id', $itemId)
            ->first();
    }

    private function buildDeliveryResponse(int $deliveryId, array $relations = []): ?DocumentDelivery
    {
        $delivery = DocumentDelivery::find($deliveryId);
        if (!$delivery) {
            return null;
        }

        app(DocumentDeliveryService::class)->syncDeliveryItems($delivery);

        $baseRelations = [
            'project',
            'submitter',
            'completer',
            'attachments.uploader',
            'items.submitter',
            'items.completer',
            'items.attachments.uploader',
        ];

        $delivery = DocumentDelivery::with(array_values(array_unique(array_merge($baseRelations, $relations))))
            ->find($deliveryId);

        return $delivery ? $this->appendComputedFields($delivery) : null;
    }

    private function appendComputedFields(DocumentDelivery $delivery): DocumentDelivery
    {
        $items = $delivery->items ?? collect();

        foreach ($items as $item) {
            $item->attachment_count = $item->attachments ? $item->attachments->count() : 0;
        }

        $pendingItems = $items->where('status', 'pending')->values();
        $submittedItemCount = $items->filter(function (DocumentDeliveryItem $item) {
            return in_array($item->status, ['submitted', 'completed'], true);
        })->count();

        $legacyAttachments = ($delivery->attachments ?? collect())
            ->whereNull('delivery_item_id')
            ->values();

        $delivery->total_item_count = $items->count();
        $delivery->submitted_item_count = $submittedItemCount;
        $delivery->completed_item_count = $items->where('status', 'completed')->count();
        $delivery->pending_documents = $pendingItems->pluck('document_name')->filter()->values();
        $delivery->pending_documents_text = $delivery->pending_documents->isEmpty()
            ? ''
            : $delivery->pending_documents->implode('、');
        $delivery->attachment_count = $legacyAttachments->count() + $items->sum(function (DocumentDeliveryItem $item) {
            return $item->attachment_count ?? 0;
        });
        $delivery->legacy_attachments = $legacyAttachments;

        return $delivery;
    }
}
