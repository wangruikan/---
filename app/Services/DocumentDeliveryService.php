<?php

namespace App\Services;

use App\Models\ProjectDeliveryConfig;
use App\Models\DocumentDelivery;
use App\Models\DocumentDeliveryItem;
use App\Models\DocumentDeliveryReminder;
use App\Models\AssessmentRecord;
use App\Services\PendingTaskService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class DocumentDeliveryService
{
    public function normalizeDeliveryReleaseMonth(?string $releaseMonth): string
    {
        return $releaseMonth === 'next' ? 'next' : 'current';
    }

    /**
     * 生成交付期间标识
     * @param string $cycle monthly/quarterly
     * @param Carbon $date
     * @return string
     */
    public function generateDeliveryPeriod($cycle, Carbon $date)
    {
        if ($cycle === 'monthly') {
            return $date->format('Y-m');
        } else {
            // 季度：使用季度第一个月，格式 YYYY-MM
            // Q1 -> 01, Q2 -> 04, Q3 -> 07, Q4 -> 10
            $quarter = ceil($date->month / 3);
            $quarterFirstMonth = ($quarter - 1) * 3 + 1;
            return $date->format('Y') . '-' . str_pad($quarterFirstMonth, 2, '0', STR_PAD_LEFT);
        }
    }

    public function resolveDeliveryPeriodForDisplayMonth(ProjectDeliveryConfig $config, Carbon $displayMonthDate): ?string
    {
        $releaseMonth = $this->normalizeDeliveryReleaseMonth($config->delivery_release_month ?? null);

        if ($config->delivery_cycle === 'monthly') {
            $periodDate = $releaseMonth === 'next'
                ? $displayMonthDate->copy()->subMonth()
                : $displayMonthDate->copy();

            $period = $this->generateDeliveryPeriod('monthly', $periodDate);

            return $this->isProjectPeriodAvailable($config, $period) ? $period : null;
        }

        if ($releaseMonth === 'current') {
            if (!$this->isQuarterFirstMonth($displayMonthDate)) {
                return null;
            }

            $period = $this->generateDeliveryPeriod('quarterly', $displayMonthDate->copy());
            return $this->isProjectPeriodAvailable($config, $period) ? $period : null;
        }

        if (!$this->isQuarterSecondMonth($displayMonthDate)) {
            return null;
        }

        $period = $this->generateDeliveryPeriod('quarterly', $displayMonthDate->copy()->subMonth());

        return $this->isProjectPeriodAvailable($config, $period) ? $period : null;
    }

    public function resolveDisplayMonthForPeriod(ProjectDeliveryConfig $config, string $period): string
    {
        $periodDate = Carbon::createFromFormat('Y-m-d', $period . '-01');

        if ($this->normalizeDeliveryReleaseMonth($config->delivery_release_month ?? null) === 'next') {
            return $periodDate->addMonth()->format('Y-m');
        }

        return $periodDate->format('Y-m');
    }

    /**
     * 为项目生成交付记录
     * @param ProjectDeliveryConfig $config
     * @param string $period
     * @return DocumentDelivery
     */
    public function createDeliveryRecord(ProjectDeliveryConfig $config, $period, ?string $displayMonth = null)
    {
        $displayMonth = $displayMonth ?: $this->resolveDisplayMonthForPeriod($config, $period);

        // 检查是否已存在
        $existing = DocumentDelivery::where('project_id', $config->project_id)
            ->where('delivery_period', $period)
            ->first();

        if ($existing) {
            if ($this->syncPendingDeliveryFromConfig($config, $existing, $displayMonth)) {
                return $existing->fresh();
            }

            return $existing->fresh();
        }

        // 获取经办人ID（第一个审批节点账号）
        $handlerId = $this->getProjectOperatorId($config->project_id);

        $delivery = DocumentDelivery::create([
            'config_id' => $config->id,
            'account_set_id' => $config->account_set_id,
            'project_id' => $config->project_id,
            'delivery_cycle' => $config->delivery_cycle,
            'delivery_method' => $config->delivery_method,
            'delivery_release_month' => $this->normalizeDeliveryReleaseMonth($config->delivery_release_month ?? null),
            'delivery_period' => $period,
            'display_month' => $displayMonth,
            'status' => 'pending',
            'handler_id' => $handlerId,
            'required_documents' => $config->required_documents,
        ]);

        $this->syncDeliveryItems($delivery);

        // 创建待办任务
        PendingTaskService::createDocumentDeliveryTask($delivery);

        return $delivery;
    }

    public function syncPendingDeliveriesForConfig(ProjectDeliveryConfig $config): int
    {
        $config->loadMissing('project');

        $syncedCount = 0;

        DocumentDelivery::where('account_set_id', $config->account_set_id)
            ->where('project_id', $config->project_id)
            ->where('status', 'pending')
            ->orderBy('delivery_period')
            ->get()
            ->each(function (DocumentDelivery $delivery) use ($config, &$syncedCount) {
                if ($this->syncPendingDeliveryFromConfig($config, $delivery)) {
                    $syncedCount++;
                }
            });

        return $syncedCount;
    }

    public function syncDeliveryItems(DocumentDelivery $delivery): void
    {
        $requiredDocuments = $this->normalizeRequiredDocuments(
            $delivery->required_documents,
            $delivery->status !== 'pending' ? $delivery->submitted_documents : null
        );

        if (empty($requiredDocuments)) {
            return;
        }

        $delivery->loadMissing('items');
        $existingItems = $delivery->items->keyBy(function (DocumentDeliveryItem $item) {
            return trim((string) $item->document_name);
        });

        foreach ($requiredDocuments as $index => $documentName) {
            $sortOrder = $index + 1;
            $existingItem = $existingItems->get($documentName);

            if ($existingItem) {
                if ((int) $existingItem->sort_order !== $sortOrder) {
                    $existingItem->update(['sort_order' => $sortOrder]);
                }
                continue;
            }

            DocumentDeliveryItem::create(array_merge(
                $this->buildLegacyItemAttributes($delivery),
                [
                    'delivery_id' => $delivery->id,
                    'document_name' => $documentName,
                    'sort_order' => $sortOrder,
                ]
            ));
        }

        $delivery->unsetRelation('items');
    }

    public function refreshDeliverySummary(DocumentDelivery $delivery): DocumentDelivery
    {
        $delivery->loadMissing('items');
        $items = $delivery->items;

        if ($items->isEmpty()) {
            return $delivery;
        }

        $submittedItems = $items->filter(function (DocumentDeliveryItem $item) {
            return in_array($item->status, ['submitted', 'completed'], true);
        })->values();

        $completedItems = $items->where('status', 'completed')->values();
        $pendingCount = $items->where('status', 'pending')->count();

        if ($pendingCount > 0) {
            $status = 'pending';
        } elseif ($completedItems->count() === $items->count()) {
            $status = 'completed';
        } else {
            $status = 'submitted';
        }

        $submittedNames = $submittedItems->pluck('document_name')
            ->filter()
            ->values()
            ->all();

        $expressNumbers = $submittedItems->pluck('express_number')
            ->filter(function ($value) {
                return $value !== null && $value !== '';
            })
            ->unique()
            ->values();

        $latestSubmittedItem = $submittedItems->sortByDesc(function (DocumentDeliveryItem $item) {
            return $item->submitted_at ? $item->submitted_at->timestamp : 0;
        })->first();

        $latestCompletedItem = $completedItems->sortByDesc(function (DocumentDeliveryItem $item) {
            return $item->completed_at ? $item->completed_at->timestamp : 0;
        })->first();

        $latestExpressDateItem = $submittedItems->filter(function (DocumentDeliveryItem $item) {
            return !empty($item->express_date);
        })->sortByDesc(function (DocumentDeliveryItem $item) {
            return $item->express_date ? $item->express_date->timestamp : 0;
        })->first();

        $latestRemark = $submittedItems->pluck('remarks')
            ->filter(function ($value) {
                return $value !== null && $value !== '';
            })
            ->last();

        $updateData = [];
        $this->setSummaryValueIfChanged($delivery, $updateData, 'status', $status);
        $this->setSummaryValueIfChanged($delivery, $updateData, 'submitted_documents', empty($submittedNames) ? null : implode('、', $submittedNames));
        $this->setSummaryValueIfChanged($delivery, $updateData, 'express_number', $expressNumbers->count() > 1 ? '多条' : ($expressNumbers->first() ?: null));
        $this->setSummaryValueIfChanged($delivery, $updateData, 'express_date', $latestExpressDateItem?->express_date);
        $this->setSummaryValueIfChanged($delivery, $updateData, 'submitted_by', $latestSubmittedItem?->submitted_by);
        $this->setSummaryValueIfChanged($delivery, $updateData, 'submitted_at', $latestSubmittedItem?->submitted_at);
        $this->setSummaryValueIfChanged($delivery, $updateData, 'remarks', $latestRemark);

        if ($status === 'completed') {
            $this->setSummaryValueIfChanged($delivery, $updateData, 'completed_by', $latestCompletedItem?->completed_by);
            $this->setSummaryValueIfChanged($delivery, $updateData, 'completed_at', $latestCompletedItem?->completed_at);
        } else {
            $this->setSummaryValueIfChanged($delivery, $updateData, 'completed_by', null);
            $this->setSummaryValueIfChanged($delivery, $updateData, 'completed_at', null);
        }

        if (!empty($updateData)) {
            $delivery->update($updateData);
        }

        return $delivery->fresh();
    }

    public function syncPendingDeliveryFromConfig(ProjectDeliveryConfig $config, DocumentDelivery $delivery, ?string $displayMonth = null): bool
    {
        if (!$this->canAutoSyncPendingDelivery($delivery)) {
            return false;
        }

        if (($delivery->delivery_cycle ?? null) !== ($config->delivery_cycle ?? null)) {
            return false;
        }

        $displayMonth = $displayMonth ?: $this->resolveDisplayMonthForPeriod($config, $delivery->delivery_period);
        $handlerId = $this->getProjectOperatorId($config->project_id);

        $updateData = [
            'config_id' => $config->id,
            'delivery_method' => $config->delivery_method,
            'delivery_release_month' => $this->normalizeDeliveryReleaseMonth($config->delivery_release_month ?? null),
            'display_month' => $displayMonth,
            'handler_id' => $handlerId,
            'required_documents' => $config->required_documents,
            'document_period' => null,
            'submitted_documents' => null,
            'express_number' => null,
            'express_date' => null,
            'submitted_by' => null,
            'submitted_at' => null,
            'completed_by' => null,
            'completed_at' => null,
            'remarks' => null,
        ];

        $delivery->update($updateData);

        $delivery->items()->delete();
        $delivery->unsetRelation('items');
        $this->syncDeliveryItems($delivery->fresh());

        PendingTaskService::syncDocumentDeliveryTask($delivery->fresh(['project']));

        return true;
    }

    /**
     * 发送新周期提醒
     * @param DocumentDelivery $delivery
     * @param int $recipientId
     */
    public function sendNewPeriodReminder(DocumentDelivery $delivery, $recipientId)
    {
        $existingReminder = DocumentDeliveryReminder::where('delivery_id', $delivery->id)
            ->where('reminder_type', 'new_period')
            ->where('recipient_id', $recipientId)
            ->first();

        if ($existingReminder) {
            return $existingReminder;
        }

        DocumentDeliveryReminder::create([
            'account_set_id' => $delivery->account_set_id,
            'delivery_id' => $delivery->id,
            'reminder_type' => 'new_period',
            'recipient_id' => $recipientId,
            'is_read' => false,
        ]);

        Log::info('新周期提醒已发送', [
            'delivery_id' => $delivery->id,
            'recipient_id' => $recipientId,
            'period' => $delivery->delivery_period
        ]);
    }

    /**
     * 发送未交付提醒并生成考核记录
     * @param DocumentDelivery $delivery
     * @param int $recipientId
     */
    public function sendNotSubmittedReminder(DocumentDelivery $delivery, $recipientId)
    {
        // 检查今天是否已发送过提醒（避免重复）
        $today = Carbon::today();
        $existingReminder = DocumentDeliveryReminder::where('delivery_id', $delivery->id)
            ->where('reminder_type', 'not_submitted')
            ->where('recipient_id', $recipientId)
            ->whereDate('created_at', $today)
            ->first();

        if (!$existingReminder) {
            // 创建提醒记录
            DocumentDeliveryReminder::create([
                'account_set_id' => $delivery->account_set_id,
                'delivery_id' => $delivery->id,
                'reminder_type' => 'not_submitted',
                'recipient_id' => $recipientId,
                'is_read' => false,
            ]);

            Log::info('未交付提醒已发送', [
                'delivery_id' => $delivery->id,
                'recipient_id' => $recipientId,
                'period' => $delivery->delivery_period
            ]);
        }
        
        // 生成考核记录（即使今天已发送过提醒，也要检查并生成考核）
        $this->createAssessmentForPendingDelivery($delivery, $recipientId);
    }
    
    /**
     * 为未交付记录创建考核
     * @param DocumentDelivery $delivery
     * @param int $handlerId 经办人ID
     */
    private function createAssessmentForPendingDelivery(DocumentDelivery $delivery, $handlerId)
    {
        // 检查是否已存在相同的考核记录（避免重复）
        $existingAssessment = AssessmentRecord::where('account_set_id', $delivery->account_set_id)
            ->where('business_type', 'document_delivery')
            ->where('business_id', $delivery->id)
            ->where('handler_id', $handlerId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->first();
            
        if ($existingAssessment) {
            Log::info('考核记录已存在，跳过创建', ['delivery_id' => $delivery->id]);
            return;
        }
        
        try {
            // 次月任务按实际出现的月份计算截止日期
            $deadlineDate = Carbon::parse(($delivery->display_month ?? $delivery->delivery_period) . '-01')->endOfMonth();
            
            // 获取项目名称
            $projectName = $delivery->project ? $delivery->project->name : '未知项目';
            
            // 获取经办人名称
            $handler = \DB::table('users')->where('id', $handlerId)->first();
            $handlerName = $handler ? $handler->name : '经办人';
            
            // 创建考核记录
            AssessmentRecord::create([
                'account_set_id' => $delivery->account_set_id,
                'business_type' => 'document_delivery',
                'business_id' => $delivery->id,
                'business_name' => "资料交付超期 - {$projectName} ({$delivery->delivery_period})",
                'handler_id' => $handlerId,
                'handler_name' => $handlerName,
                'deadline_date' => $deadlineDate,
                'status' => 'pending',
                'remark' => "交付期间 {$delivery->delivery_period} 的资料未按时提交，请及时处理。"
            ]);
            
            Log::info('未交付考核记录已创建', [
                'delivery_id' => $delivery->id,
                'project_name' => $projectName,
                'period' => $delivery->delivery_period,
                'handler_id' => $handlerId,
                'handler_name' => $handlerName
            ]);
            
        } catch (\Exception $e) {
            Log::error('创建未交付考核记录失败', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取项目的经办人ID（第一个审批节点账号）
     * @param int $projectId
     * @return int|null
     */
    public function getProjectOperatorId($projectId)
    {
        $operatorIds = $this->getProjectOperatorIds($projectId);

        return empty($operatorIds) ? null : (int) $operatorIds[0];
    }

    private function getProjectOperatorIds($projectId): array
    {
        // 获取项目信息
        $project = \App\Models\Project::find($projectId);
        if (!$project || !$project->account_set_id) {
            return [];
        }

        $roleUserIds = app(ProjectRoleUserService::class)->getProjectRoleUserIds(
            (int) $project->account_set_id,
            (int) $project->id,
            ProjectRoleUserService::ROLE_DELIVERY
        );

        if (!empty($roleUserIds)) {
            return \App\Models\User::whereIn('id', $roleUserIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($userId) => (int) $userId)
                ->values()
                ->all();
        }

        return [];
    }

    private function sendNewPeriodReminders(DocumentDelivery $delivery, array $recipientIds): void
    {
        foreach (array_values(array_unique($recipientIds)) as $recipientId) {
            if ((int) $recipientId <= 0) {
                continue;
            }

            $this->sendNewPeriodReminder($delivery, (int) $recipientId);
        }
    }

    private function sendNotSubmittedReminders(DocumentDelivery $delivery, array $recipientIds): void
    {
        foreach (array_values(array_unique($recipientIds)) as $recipientId) {
            if ((int) $recipientId <= 0) {
                continue;
            }

            $this->sendNotSubmittedReminder($delivery, (int) $recipientId);
        }
    }

    /**
     * 每月1日执行：生成新交付记录
     */
    public function generateMonthlyDeliveries()
    {
        $now = Carbon::now();
        $displayMonth = $now->format('Y-m');

        Log::info('开始生成月度交付记录', ['display_month' => $displayMonth]);

        // 获取所有启用的按月交付配置
        $monthlyConfigs = ProjectDeliveryConfig::where('delivery_cycle', 'monthly')
            ->where('is_active', true)
            ->get();

        foreach ($monthlyConfigs as $config) {
            try {
                $period = $this->resolveDeliveryPeriodForDisplayMonth($config, $now->copy()->startOfMonth());
                if (!$period) {
                    continue;
                }

                $delivery = $this->createDeliveryRecord($config, $period, $displayMonth);
                $operatorIds = $this->getProjectOperatorIds($config->project_id);

                if (!empty($operatorIds)) {
                    $this->sendNewPeriodReminders($delivery, $operatorIds);
                }

                Log::info('月度交付记录已生成', [
                    'project_id' => $config->project_id,
                    'delivery_id' => $delivery->id
                ]);
            } catch (\Exception $e) {
                Log::error('生成月度交付记录失败', [
                    'project_id' => $config->project_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * 每季度第一天执行：生成季度交付记录
     * 仅在 1月1日、4月1日、7月1日、10月1日 执行
     */
    public function generateQuarterlyDeliveries()
    {
        $now = Carbon::now();

        Log::info('开始生成季度交付记录', ['display_month' => $now->format('Y-m'), 'date' => $now->toDateString()]);

        // 获取所有启用的按季度交付配置
        $quarterlyConfigs = ProjectDeliveryConfig::where('delivery_cycle', 'quarterly')
            ->where('is_active', true)
            ->get();

        foreach ($quarterlyConfigs as $config) {
            try {
                $period = $this->resolveDeliveryPeriodForDisplayMonth($config, $now->copy()->startOfMonth());
                if (!$period) {
                    continue;
                }

                $delivery = $this->createDeliveryRecord($config, $period, $now->format('Y-m'));
                $operatorIds = $this->getProjectOperatorIds($config->project_id);

                if (!empty($operatorIds)) {
                    $this->sendNewPeriodReminders($delivery, $operatorIds);
                }

                Log::info('季度交付记录已生成', [
                    'project_id' => $config->project_id,
                    'delivery_id' => $delivery->id,
                    'period' => $period
                ]);
            } catch (\Exception $e) {
                Log::error('生成季度交付记录失败', [
                    'project_id' => $config->project_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * 每月月底执行：检查并提醒未交付
     */
    public function checkAndRemindPending()
    {
        $now = Carbon::now();

        Log::info('开始检查未交付记录');

        // 1. 检查按月交付的项目
        $this->checkMonthlyPending($now);

        // 2. 检查按季度交付的项目
        $this->checkQuarterlyPending($now);
    }

    /**
     * 检查月度未交付记录
     */
    private function checkMonthlyPending(Carbon $now)
    {
        $currentDisplayMonth = $now->format('Y-m');

        $pendingDeliveries = DocumentDelivery::where('delivery_cycle', 'monthly')
            ->where('display_month', $currentDisplayMonth)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingDeliveries as $delivery) {
            $operatorIds = $this->getProjectOperatorIds($delivery->project_id);
            if (!empty($operatorIds)) {
                $this->sendNotSubmittedReminders($delivery, $operatorIds);
            }
        }

        Log::info('月度未交付检查完成', ['count' => $pendingDeliveries->count()]);
    }

    /**
     * 检查季度未交付记录
     * 每月底检查所有按季交付的未提交记录，不限制期间
     */
    private function checkQuarterlyPending(Carbon $now)
    {
        Log::info('开始检查季度未交付记录');
        
        // 查找所有按季交付且状态为pending的记录（不限制period）
        $pendingDeliveries = DocumentDelivery::where('delivery_cycle', 'quarterly')
            ->where('status', 'pending')
            ->get();

        Log::info('找到季度未交付记录', ['count' => $pendingDeliveries->count()]);

        foreach ($pendingDeliveries as $delivery) {
            Log::info('处理季度未交付记录', [
                'delivery_id' => $delivery->id,
                'period' => $delivery->delivery_period
            ]);
            
            $operatorIds = $this->getProjectOperatorIds($delivery->project_id);
            if (!empty($operatorIds)) {
                $this->sendNotSubmittedReminders($delivery, $operatorIds);
            } else {
                Log::warning('未找到经办人', ['delivery_id' => $delivery->id]);
            }
        }

        Log::info('季度未交付检查完成', ['count' => $pendingDeliveries->count()]);
    }

    private function isProjectPeriodAvailable(ProjectDeliveryConfig $config, string $period): bool
    {
        if (!$config->relationLoaded('project')) {
            $config->loadMissing('project');
        }

        $project = $config->project;
        if (!$project || !$project->start_date) {
            return true;
        }

        $projectStartDate = Carbon::parse($project->start_date);
        $projectStartPeriod = $this->generateDeliveryPeriod($config->delivery_cycle, $projectStartDate->copy());

        if ($period < $projectStartPeriod) {
            return false;
        }

        if (!empty($project->end_date)) {
            $projectEndDate = Carbon::parse($project->end_date);
            $projectEndPeriod = $this->generateDeliveryPeriod($config->delivery_cycle, $projectEndDate->copy());

            if ($period > $projectEndPeriod) {
                return false;
            }
        }

        return true;
    }

    private function isQuarterFirstMonth(Carbon $date): bool
    {
        return in_array($date->month, [1, 4, 7, 10], true);
    }

    private function isQuarterSecondMonth(Carbon $date): bool
    {
        return in_array($date->month, [2, 5, 8, 11], true);
    }

    private function canAutoSyncPendingDelivery(DocumentDelivery $delivery): bool
    {
        if (($delivery->status ?? null) !== 'pending') {
            return false;
        }

        $this->syncDeliveryItems($delivery);
        $delivery->loadMissing(['items.attachments', 'attachments']);

        if (
            $this->hasFilledValue($delivery->document_period)
            || $this->hasFilledValue($delivery->submitted_documents)
            || $this->hasFilledValue($delivery->express_number)
            || !empty($delivery->express_date)
            || !empty($delivery->submitted_by)
            || !empty($delivery->submitted_at)
            || !empty($delivery->completed_by)
            || !empty($delivery->completed_at)
            || $this->hasFilledValue($delivery->remarks)
        ) {
            return false;
        }

        if (($delivery->attachments ?? collect())->whereNull('delivery_item_id')->isNotEmpty()) {
            return false;
        }

        foreach ($delivery->items as $item) {
            if (
                ($item->status ?? null) !== 'pending'
                || $this->hasFilledValue($item->submitted_documents)
                || $this->hasFilledValue($item->express_number)
                || !empty($item->express_date)
                || !empty($item->submitted_by)
                || !empty($item->submitted_at)
                || !empty($item->completed_by)
                || !empty($item->completed_at)
                || $this->hasFilledValue($item->remarks)
                || ($item->attachments ?? collect())->isNotEmpty()
            ) {
                return false;
            }
        }

        return true;
    }

    private function normalizeRequiredDocuments($documents, ?string $fallbackDocument = null): array
    {
        if (is_string($documents)) {
            $decoded = json_decode($documents, true);
            $documents = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($documents)) {
            $documents = [];
        }

        $normalized = [];

        foreach ($documents as $document) {
            $documentName = trim((string) $document);
            if ($documentName === '' || in_array($documentName, $normalized, true)) {
                continue;
            }

            $normalized[] = $documentName;
        }

        if (empty($normalized) && !empty($fallbackDocument)) {
            $normalized[] = trim($fallbackDocument);
        }

        if (empty($normalized)) {
            $normalized[] = '资料交付';
        }

        return array_values(array_filter($normalized, function ($value) {
            return $value !== '';
        }));
    }

    private function buildLegacyItemAttributes(DocumentDelivery $delivery): array
    {
        if ($delivery->status === 'completed') {
            return [
                'status' => 'completed',
                'submitted_documents' => $delivery->submitted_documents,
                'express_number' => $delivery->express_number,
                'express_date' => $delivery->express_date,
                'submitted_by' => $delivery->submitted_by,
                'submitted_at' => $delivery->submitted_at,
                'completed_by' => $delivery->completed_by,
                'completed_at' => $delivery->completed_at,
                'remarks' => $delivery->remarks,
            ];
        }

        if ($delivery->status === 'submitted') {
            return [
                'status' => 'submitted',
                'submitted_documents' => $delivery->submitted_documents,
                'express_number' => $delivery->express_number,
                'express_date' => $delivery->express_date,
                'submitted_by' => $delivery->submitted_by,
                'submitted_at' => $delivery->submitted_at,
                'remarks' => $delivery->remarks,
            ];
        }

        return [
            'status' => 'pending',
        ];
    }

    private function setSummaryValueIfChanged(DocumentDelivery $delivery, array &$updateData, string $field, $newValue): void
    {
        $currentValue = $this->normalizeSummaryValue($delivery->{$field});
        $nextValue = $this->normalizeSummaryValue($newValue);

        if ($currentValue !== $nextValue) {
            $updateData[$field] = $newValue;
        }
    }

    private function normalizeSummaryValue($value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format($value->isStartOfDay() && $value->format('H:i:s') === '00:00:00'
                ? 'Y-m-d'
                : 'Y-m-d H:i:s');
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function hasFilledValue($value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
