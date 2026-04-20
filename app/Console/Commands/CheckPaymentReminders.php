<?php

namespace App\Console\Commands;

use App\Models\PaymentDueDateConfig;
use App\Models\PaymentReminderConfig;
use App\Models\PaymentReminderLog;
use App\Models\PaymentRequest;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:check-reminders 
                            {--date= : 指定检查日期(格式:Y-m-d),不指定则使用今天}
                            {--time= : 指定检查时间(格式:H:i),不指定则使用当前时间}
                            {--force : 强制执行,跳过时间检查}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查社保公积金缴费提醒';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 获取指定日期或使用今天
        $dateOption = $this->option('date');
        $timeOption = $this->option('time');
        $forceOption = $this->option('force');
        
        if ($dateOption) {
            try {
                $now = Carbon::parse($dateOption);
                $this->info("使用指定日期: {$now->toDateString()}");
            } catch (\Exception $e) {
                $this->error("日期格式错误: {$dateOption}");
                $this->error("请使用格式: Y-m-d (例如: 2026-03-15)");
                return 1;
            }
        } else {
            $now = Carbon::now();
            $this->info("使用当前日期: {$now->toDateString()}");
        }
        
        // 设置时间
        if ($timeOption) {
            try {
                $timeParts = explode(':', $timeOption);
                $now->setTime((int)$timeParts[0], (int)$timeParts[1], 0);
                $this->info("使用指定时间: {$now->format('H:i:s')}");
            } catch (\Exception $e) {
                $this->error("时间格式错误: {$timeOption}");
                $this->error("请使用格式: H:i (例如: 09:00)");
                return 1;
            }
        } else {
            $this->info("使用当前时间: {$now->format('H:i:s')}");
        }
        
        if ($forceOption) {
            $this->warn("强制模式: 将跳过时间检查");
        }
        
        $this->info('开始检查社保公积金缴费提醒...');
        
        $currentYear = $now->year;
        $currentMonth = $now->month;
        
        // 获取所有启用的提醒时间配置（按账套）
        $reminderConfigs = PaymentReminderConfig::where('is_active', true)->get();
        
        if ($reminderConfigs->isEmpty()) {
            $this->info('没有启用的提醒配置');
            return 0;
        }
        
        // 按账套分组
        $configsByAccountSet = $reminderConfigs->groupBy('account_set_id');
        
        foreach ($configsByAccountSet as $accountSetId => $configs) {
            $this->info("检查账套 #{$accountSetId} 的缴费提醒");
            
            // 对社保和公积金分别检查
            $paymentTypes = ['social_security', 'housing_fund'];
            
            foreach ($paymentTypes as $paymentType) {
                $paymentTypeText = $paymentType === 'social_security' ? '社保' : '公积金';
                
                foreach ($configs as $reminderConfig) {
                    $this->checkAndSendReminders($accountSetId, $paymentType, $reminderConfig, $now, $currentYear, $currentMonth, $forceOption);
                }
            }
        }
        
        $this->info('缴费提醒检查完成');
        return 0;
    }

    /**
     * 检查并发送提醒
     */
    private function checkAndSendReminders($accountSetId, $paymentType, $reminderConfig, $now, $currentYear, $currentMonth, $forceOption = false)
    {
        $currentDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');
        
        // 计算应该提醒的缴费月份
        $targetDate = $now->copy()->addDays($reminderConfig->days_before);
        $targetYear = $targetDate->year;
        $targetMonth = $targetDate->month;
        
        // 获取目标月份的缴费日期
        $dueDay = PaymentDueDateConfig::getDueDayForMonth($accountSetId, $paymentType, $targetMonth);
        
        // 构建缴费日期
        $dueDate = Carbon::create($targetYear, $targetMonth, $dueDay);
        
        // 计算提醒日期
        $reminderDate = $dueDate->copy()->subDays($reminderConfig->days_before);
        
        // 检查今天是否是提醒日期
        if ($reminderDate->format('Y-m-d') !== $currentDate) {
            return;
        }
        
        // 如果不是强制模式,检查当前时间是否已到提醒时间
        if (!$forceOption) {
            $reminderTime = Carbon::parse($reminderConfig->reminder_time);
            $currentTimeCarbon = Carbon::parse($currentTime);
            
            // 如果当前时间小于提醒时间，或者已经超过提醒时间1小时，则跳过
            if ($currentTimeCarbon->lt($reminderTime) || $currentTimeCarbon->diffInHours($reminderTime, false) > 1) {
                return;
            }
        }
        
        $paymentTypeText = $paymentType === 'social_security' ? '社保' : '公积金';
        $this->info("触发提醒：账套#{$accountSetId} - {$paymentTypeText} - {$targetYear}年{$targetMonth}月 - 缴费日期：{$dueDate->format('Y-m-d')}");
        
        // 查找该账套的未完成回执的付款申请
        $pendingPayments = $this->findPendingPayments(
            $accountSetId,
            $paymentType,
            $targetYear,
            $targetMonth
        );
        
        if ($pendingPayments->isEmpty()) {
            $this->info("没有未完成回执的付款申请");
            return;
        }
        
        $this->info("找到 {$pendingPayments->count()} 条未完成回执的付款申请");
        
        // 为每条未完成的付款申请发送提醒
        foreach ($pendingPayments as $payment) {
            $this->sendReminderForPayment(
                $payment,
                $paymentType,
                $reminderConfig,
                $dueDate,
                $reminderDate,
                $targetYear,
                $targetMonth
            );
        }
    }

    /**
     * 查找未完成回执的付款申请（指定账套）
     */
    private function findPendingPayments($accountSetId, $paymentType, $year, $month)
    {
        // 查找该类型、该月份的付款申请
        // 状态为approved（已审批通过），且发票状态为pending_invoice或invoice_uploaded（未完成回执）
        $query = PaymentRequest::where('account_set_id', $accountSetId)
            ->where('payment_type', 'insurance')
            ->where('status', 'approved')
            ->whereIn('invoice_status', ['pending_invoice', 'invoice_uploaded'])
            ->with(['insuranceSummary', 'submitter']);
        
        // 根据保险汇总的月份筛选
        $query->whereHas('insuranceSummary', function($q) use ($year, $month, $paymentType) {
            // 使用 month 字段匹配（格式：Y-m）
            $monthValue = sprintf('%04d-%02d', $year, $month);
            $q->where('month', $monthValue);
            
            // 根据类型筛选（只使用 category 和 title 字段）
            if ($paymentType === 'social_security') {
                $q->where(function($query) {
                    $query->where('category', 'social_insurance')
                          ->orWhere('category', 'social_security')
                          ->orWhere('title', 'LIKE', '%社保%')
                          ->orWhere('title', 'LIKE', '%社会保险%');
                });
            } elseif ($paymentType === 'housing_fund') {
                $q->where(function($query) {
                    $query->where('category', 'housing_fund')
                          ->orWhere('title', 'LIKE', '%公积金%')
                          ->orWhere('title', 'LIKE', '%住房公积金%');
                });
            }
        });
        
        return $query->get();
    }

    /**
     * 为单个付款申请发送提醒
     */
    private function sendReminderForPayment($payment, $paymentType, $reminderConfig, $dueDate, $reminderDate, $year, $month)
    {
        // 检查是否已经发送过相同的提醒（防止重复）
        $existingLog = PaymentReminderLog::where('payment_request_id', $payment->id)
            ->where('reminder_date', $reminderDate->format('Y-m-d'))
            ->where('reminder_time', $reminderConfig->reminder_time)
            ->first();
        
        if ($existingLog) {
            $this->info("付款申请 #{$payment->id} 已发送过提醒，跳过");
            return;
        }
        
        // 确定提醒对象
        $notifyUserId = $this->getNotifyUserId($payment, $paymentType);
        
        if (!$notifyUserId) {
            $this->warn("付款申请 #{$payment->id} 无法确定提醒对象");
            return;
        }
        
        // 创建通知
        $notification = $this->createNotification($payment, $paymentType, $dueDate, $notifyUserId);
        
        // 记录提醒日志
        PaymentReminderLog::create([
            'account_set_id' => $payment->account_set_id,
            'payment_request_id' => $payment->id,
            'payment_type' => $paymentType,
            'year' => $year,
            'month' => $month,
            'due_date' => $dueDate->format('Y-m-d'),
            'reminder_date' => $reminderDate->format('Y-m-d'),
            'reminder_time' => $reminderConfig->reminder_time,
            'notified_user_id' => $notifyUserId,
            'notification_id' => $notification->id,
        ]);
        
        $this->info("已发送提醒给用户 #{$notifyUserId}");
    }

    /**
     * 确定提醒对象
     * 社保：提醒财务人员（该账套下的财务角色用户）
     * 公积金：提醒发起人
     */
    private function getNotifyUserId($payment, $paymentType)
    {
        if ($paymentType === 'social_security') {
            // 社保：查找该账套下的财务角色用户
            // 通过 account_set_users 中间表查找该账套的用户
            $financeUser = User::whereHas('accountSets', function($query) use ($payment) {
                    $query->where('account_sets.id', $payment->account_set_id);
                })
                ->where(function($query) {
                    $query->where('role', 'finance')
                          ->orWhere('role', 'LIKE', '%财务%')
                          ->orWhere('role', 'LIKE', '%finance%');
                })
                ->first();
            
            return $financeUser ? $financeUser->id : null;
        } elseif ($paymentType === 'housing_fund') {
            // 公积金：提醒发起人
            return $payment->submitted_by;
        }
        
        return null;
    }

    /**
     * 创建通知
     */
    private function createNotification($payment, $paymentType, $dueDate, $userId)
    {
        $paymentTypeText = $paymentType === 'social_security' ? '社保' : '公积金';
        $dueDateText = $dueDate->format('Y年m月d日');
        
        $insuranceSummary = $payment->insuranceSummary;
        $summaryTitle = $insuranceSummary ? $insuranceSummary->title : '保险汇总';
        
        $title = "{$paymentTypeText}缴费提醒";
        $content = "【{$summaryTitle}】需要上传回执，缴费日期：{$dueDateText}，请及时处理。";
        
        return Notification::create([
            'user_id' => $userId,
            'type' => 'payment_reminder',
            'title' => $title,
            'content' => $content,
            'is_read' => false,
        ]);
    }
}
