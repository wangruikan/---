<?php

namespace App\Services;

use App\Models\ProjectDeliveryConfig;
use App\Models\DocumentDelivery;
use App\Models\DocumentDeliveryReminder;
use App\Models\AssessmentRecord;
use App\Services\PendingTaskService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DocumentDeliveryService
{
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


    /**
     * 为项目生成交付记录
     * @param ProjectDeliveryConfig $config
     * @param string $period
     * @return DocumentDelivery
     */
    public function createDeliveryRecord(ProjectDeliveryConfig $config, $period)
    {
        // 检查是否已存在
        $existing = DocumentDelivery::where('project_id', $config->project_id)
            ->where('delivery_period', $period)
            ->first();

        if ($existing) {
            return $existing;
        }

        // 获取经办人ID（第一个审批节点账号）
        $handlerId = $this->getProjectOperatorId($config->project_id) ?? $config->created_by;

        $delivery = DocumentDelivery::create([
            'config_id' => $config->id,
            'account_set_id' => $config->account_set_id,
            'project_id' => $config->project_id,
            'delivery_cycle' => $config->delivery_cycle,
            'delivery_method' => $config->delivery_method,
            'delivery_period' => $period,
            'status' => 'pending',
            'handler_id' => $handlerId,
            'required_documents' => $config->required_documents,
        ]);

        // 创建待办任务
        PendingTaskService::createDocumentDeliveryTask($delivery);

        return $delivery;
    }

    /**
     * 发送新周期提醒
     * @param DocumentDelivery $delivery
     * @param int $recipientId
     */
    public function sendNewPeriodReminder(DocumentDelivery $delivery, $recipientId)
    {
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
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->first();
            
        if ($existingAssessment) {
            Log::info('考核记录已存在，跳过创建', ['delivery_id' => $delivery->id]);
            return;
        }
        
        try {
            // 计算截止日期（交付期间的月末）
            $deadlineDate = Carbon::parse($delivery->delivery_period)->endOfMonth();
            
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
        // 获取项目信息
        $project = \App\Models\Project::find($projectId);
        if (!$project || !$project->account_set_id) {
            return null;
        }

        // 从项目所属账套中获取第一个审批人（approval_level 最小的）
        $firstApprover = \DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $project->account_set_id)
            ->whereNotNull('account_set_users.approval_level')
            ->orderBy('account_set_users.approval_level')
            ->select('users.id as user_id')
            ->first();

        return $firstApprover ? $firstApprover->user_id : null;
    }

    /**
     * 每月1日执行：生成新交付记录
     */
    public function generateMonthlyDeliveries()
    {
        $now = Carbon::now();
        $currentPeriod = $this->generateDeliveryPeriod('monthly', $now);

        Log::info('开始生成月度交付记录', ['period' => $currentPeriod]);

        // 获取所有启用的按月交付配置
        $monthlyConfigs = ProjectDeliveryConfig::where('delivery_cycle', 'monthly')
            ->where('is_active', true)
            ->get();

        foreach ($monthlyConfigs as $config) {
            try {
                $delivery = $this->createDeliveryRecord($config, $currentPeriod);
                $operatorId = $this->getProjectOperatorId($config->project_id);
                
                if ($operatorId) {
                    $this->sendNewPeriodReminder($delivery, $operatorId);
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
        
        // 检查是否是季度第一天（1/1、4/1、7/1、10/1）
        if (!$this->isQuarterFirstDay($now)) {
            Log::info('今天不是季度第一天，跳过季度交付记录生成');
            return;
        }
        
        $currentPeriod = $this->generateDeliveryPeriod('quarterly', $now);

        Log::info('开始生成季度交付记录', ['period' => $currentPeriod, 'date' => $now->toDateString()]);

        // 获取所有启用的按季度交付配置
        $quarterlyConfigs = ProjectDeliveryConfig::where('delivery_cycle', 'quarterly')
            ->where('is_active', true)
            ->get();

        foreach ($quarterlyConfigs as $config) {
            try {
                $delivery = $this->createDeliveryRecord($config, $currentPeriod);
                $operatorId = $this->getProjectOperatorId($config->project_id);
                
                if ($operatorId) {
                    $this->sendNewPeriodReminder($delivery, $operatorId);
                }

                Log::info('季度交付记录已生成', [
                    'project_id' => $config->project_id,
                    'delivery_id' => $delivery->id,
                    'period' => $currentPeriod
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
     * 检查是否是季度第一天
     * @param Carbon $date
     * @return bool
     */
    private function isQuarterFirstDay(Carbon $date)
    {
        // 只在每个季度的第一天（1月1日、4月1日、7月1日、10月1日）返回true
        $month = $date->month;
        $day = $date->day;
        
        return $day === 1 && in_array($month, [1, 4, 7, 10]);
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
        $currentPeriod = $this->generateDeliveryPeriod('monthly', $now);

        $pendingDeliveries = DocumentDelivery::where('delivery_cycle', 'monthly')
            ->where('delivery_period', $currentPeriod)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingDeliveries as $delivery) {
            $operatorId = $this->getProjectOperatorId($delivery->project_id);
            if ($operatorId) {
                $this->sendNotSubmittedReminder($delivery, $operatorId);
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
            
            $operatorId = $this->getProjectOperatorId($delivery->project_id);
            if ($operatorId) {
                $this->sendNotSubmittedReminder($delivery, $operatorId);
            } else {
                Log::warning('未找到经办人', ['delivery_id' => $delivery->id]);
            }
        }

        Log::info('季度未交付检查完成', ['count' => $pendingDeliveries->count()]);
    }
}

