<?php

/**
 * 测试社保公积金缴费提醒定时任务
 * 手动触发定时任务,检查是否会生成提醒
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PaymentRequest;
use App\Models\PaymentDueDateConfig;
use App\Models\PaymentReminderConfig;
use App\Models\PaymentReminderLog;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

echo "=== 社保公积金缴费提醒测试 ===\n\n";

// 1. 检查配置
echo "1. 检查配置\n";
echo "-------------------\n";

$dueDateConfigs = PaymentDueDateConfig::all();
echo "缴费日期配置数量: " . $dueDateConfigs->count() . "\n";
foreach ($dueDateConfigs as $config) {
    echo "  账套ID: {$config->account_set_id}, 月份: {$config->month}, 缴费日期: {$config->due_date}\n";
}

$reminderConfigs = PaymentReminderConfig::all();
echo "\n提醒时间配置数量: " . $reminderConfigs->count() . "\n";
foreach ($reminderConfigs as $config) {
    echo "  账套ID: {$config->account_set_id}, 提前天数: {$config->days_before}\n";
}

// 2. 查找未回执的付款申请
echo "\n2. 查找未回执的付款申请\n";
echo "-------------------\n";

$pendingRequests = PaymentRequest::where('payment_type', 'insurance')
    ->whereIn('invoice_status', ['pending_invoice', 'invoice_uploaded'])
    ->with(['submittedBy', 'insuranceSummary'])
    ->get();

echo "找到 {$pendingRequests->count()} 条未回执的保险付款申请\n\n";

foreach ($pendingRequests as $request) {
    $type = $request->getInsuranceType();
    $typeName = $type === 'social_security' ? '社保' : '公积金';
    
    echo "付款申请 ID: {$request->id}\n";
    echo "  类型: {$typeName}\n";
    echo "  账套ID: {$request->account_set_id}\n";
    echo "  月份: {$request->month}\n";
    echo "  发票状态: {$request->invoice_status}\n";
    echo "  提交人: " . ($request->submittedBy->name ?? '未知') . " (ID: {$request->submitted_by})\n";
    
    // 获取应该提醒的人
    if ($type === 'social_security') {
        // 社保提醒财务
        $financeUsers = User::whereHas('accountSets', function($query) use ($request) {
            $query->where('account_sets.id', $request->account_set_id);
        })->where('role', 'finance')->get();
        
        echo "  应提醒: 财务人员 (" . $financeUsers->count() . "人)\n";
        foreach ($financeUsers as $user) {
            echo "    - {$user->name} (ID: {$user->id})\n";
        }
    } else {
        // 公积金提醒发起人
        echo "  应提醒: 发起人 " . ($request->submittedBy->name ?? '未知') . "\n";
    }
    
    echo "\n";
}

// 3. 手动执行定时任务逻辑
echo "3. 执行定时任务逻辑\n";
echo "-------------------\n";

$today = Carbon::today();
$currentMonth = $today->format('Y-m');
$currentDay = $today->day;

echo "当前日期: {$today->toDateString()}\n";
echo "当前月份: {$currentMonth}\n";
echo "当前日期(天): {$currentDay}\n\n";

// 按账套分组处理
$accountSetIds = $dueDateConfigs->pluck('account_set_id')->unique();

foreach ($accountSetIds as $accountSetId) {
    echo "处理账套 ID: {$accountSetId}\n";
    
    // 获取该账套的配置
    $dueDateConfig = PaymentDueDateConfig::where('account_set_id', $accountSetId)
        ->where('month', $today->month)
        ->first();
    
    $reminderConfig = PaymentReminderConfig::where('account_set_id', $accountSetId)->first();
    
    if (!$dueDateConfig || !$reminderConfig) {
        echo "  ⚠️ 缺少配置\n\n";
        continue;
    }
    
    echo "  缴费日期: {$dueDateConfig->due_date}号\n";
    echo "  提前提醒: {$reminderConfig->days_before}天\n";
    
    // 计算提醒日期
    $reminderDay = $dueDateConfig->due_date - $reminderConfig->days_before;
    echo "  提醒日期: {$reminderDay}号\n";
    
    // 判断今天是否应该提醒
    if ($currentDay == $reminderDay) {
        echo "  ✅ 今天应该发送提醒!\n";
        
        // 查找该账套未回执的付款申请
        $requests = PaymentRequest::where('account_set_id', $accountSetId)
            ->where('payment_type', 'insurance')
            ->whereIn('invoice_status', ['pending_invoice', 'invoice_uploaded'])
            ->where('month', $currentMonth)
            ->get();
        
        echo "  找到 {$requests->count()} 条未回执记录\n";
        
        foreach ($requests as $request) {
            $type = $request->getInsuranceType();
            $typeName = $type === 'social_security' ? '社保' : '公积金';
            
            echo "    - 付款申请 #{$request->id} ({$typeName})\n";
            
            // 检查是否已经提醒过
            $existingLog = PaymentReminderLog::where('payment_request_id', $request->id)
                ->where('reminder_date', $today->toDateString())
                ->first();
            
            if ($existingLog) {
                echo "      ⚠️ 今天已经提醒过了\n";
                continue;
            }
            
            // 确定提醒对象
            if ($type === 'social_security') {
                // 社保提醒财务
                $users = User::whereHas('accountSets', function($query) use ($accountSetId) {
                    $query->where('account_sets.id', $accountSetId);
                })->where('role', 'finance')->get();
                
                echo "      提醒财务人员: {$users->count()}人\n";
            } else {
                // 公积金提醒发起人
                $users = collect([$request->submittedBy]);
                echo "      提醒发起人: {$request->submittedBy->name}\n";
            }
            
            // 这里可以选择是否真的创建提醒
            echo "      (测试模式,不实际创建提醒)\n";
        }
    } else {
        echo "  ℹ️ 今天不是提醒日期(当前{$currentDay}号,提醒日期{$reminderDay}号)\n";
    }
    
    echo "\n";
}

// 4. 查看已有的提醒记录
echo "4. 查看已有的提醒记录\n";
echo "-------------------\n";

$logs = PaymentReminderLog::with('paymentRequest')->latest()->take(10)->get();
echo "最近10条提醒记录:\n";
foreach ($logs as $log) {
    echo "  ID: {$log->id}, 付款申请: #{$log->payment_request_id}, 日期: {$log->reminder_date}\n";
}

// 5. 查看相关通知
echo "\n5. 查看相关通知\n";
echo "-------------------\n";

$notifications = Notification::where('type', 'payment_reminder')
    ->latest()
    ->take(10)
    ->get();

echo "最近10条缴费提醒通知:\n";
foreach ($notifications as $notif) {
    echo "  ID: {$notif->id}, 用户: {$notif->user_id}, 标题: {$notif->title}, 已读: " . ($notif->is_read ? '是' : '否') . "\n";
}

echo "\n=== 测试完成 ===\n";
