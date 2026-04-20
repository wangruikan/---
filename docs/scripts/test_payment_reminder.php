<?php

/**
 * 测试社保公积金缴费提醒功能
 * 
 * 使用方法：
 * php docs/scripts/test_payment_reminder.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PaymentDueDateConfig;
use App\Models\PaymentDueDateOverride;
use App\Models\PaymentReminderConfig;
use App\Models\PaymentRequest;
use Carbon\Carbon;

echo "=== 社保公积金缴费提醒功能测试 ===\n\n";

// 测试1: 检查数据库表是否存在
echo "1. 检查数据库表...\n";
$tables = [
    'payment_due_date_configs',
    'payment_due_date_overrides',
    'payment_reminder_configs',
    'payment_reminder_logs'
];

foreach ($tables as $table) {
    $exists = Schema::hasTable($table);
    echo "   - {$table}: " . ($exists ? "✓ 存在" : "✗ 不存在") . "\n";
}

echo "\n";

// 测试2: 检查配置数据
echo "2. 检查缴费日期配置...\n";
$dueDateConfigs = PaymentDueDateConfig::all();
if ($dueDateConfigs->isEmpty()) {
    echo "   ⚠ 未找到缴费日期配置，请先执行 SQL 插入默认配置\n";
} else {
    foreach ($dueDateConfigs as $config) {
        echo "   - 账套 {$config->account_set_id} - {$config->payment_type_text}: 每月{$config->default_due_day}号缴费\n";
    }
}

echo "\n";

// 测试3: 检查提醒时间配置
echo "3. 检查提醒时间配置...\n";
$reminderConfigs = PaymentReminderConfig::all();
if ($reminderConfigs->isEmpty()) {
    echo "   ⚠ 未找到提醒时间配置，请先执行 SQL 插入默认配置\n";
} else {
    foreach ($reminderConfigs as $config) {
        $daysText = $config->days_before == 0 ? '当天' : "提前{$config->days_before}天";
        echo "   - 账套 {$config->account_set_id} - {$config->payment_type_text}: {$daysText} {$config->reminder_time}\n";
    }
}

echo "\n";

// 测试4: 检查未完成回执的付款申请
echo "4. 检查未完成回执的付款申请...\n";
$pendingPayments = PaymentRequest::where('payment_type', 'insurance')
    ->where('status', 'approved')
    ->whereIn('invoice_status', ['pending_invoice', 'invoice_uploaded'])
    ->with('insuranceSummary')
    ->get();

if ($pendingPayments->isEmpty()) {
    echo "   ✓ 当前没有未完成回执的付款申请\n";
} else {
    echo "   找到 {$pendingPayments->count()} 条未完成回执的付款申请:\n";
    foreach ($pendingPayments as $payment) {
        $summary = $payment->insuranceSummary;
        $title = $summary ? $summary->title : '未知';
        $type = $payment->getInsuranceType();
        $typeText = $type === 'social_security' ? '社保' : ($type === 'housing_fund' ? '公积金' : '未知');
        echo "   - ID: {$payment->id} | 类型: {$typeText} | 标题: {$title} | 状态: {$payment->invoice_status}\n";
    }
}

echo "\n";

// 测试5: 模拟计算提醒日期
echo "5. 模拟计算提醒日期...\n";
$now = Carbon::now();
echo "   当前时间: {$now->format('Y-m-d H:i:s')}\n";

if (!$dueDateConfigs->isEmpty() && !$reminderConfigs->isEmpty()) {
    $config = $dueDateConfigs->first();
    $reminderConfig = $reminderConfigs->first();
    
    $targetDate = $now->copy()->addDays($reminderConfig->days_before);
    $dueDay = $config->getDueDateForMonth($targetDate->year, $targetDate->month);
    $dueDate = Carbon::create($targetDate->year, $targetDate->month, $dueDay);
    $reminderDate = $dueDate->copy()->subDays($reminderConfig->days_before);
    
    echo "   目标月份: {$targetDate->year}年{$targetDate->month}月\n";
    echo "   缴费日期: {$dueDate->format('Y-m-d')}\n";
    echo "   提醒日期: {$reminderDate->format('Y-m-d')} {$reminderConfig->reminder_time}\n";
    
    if ($reminderDate->format('Y-m-d') === $now->format('Y-m-d')) {
        echo "   ✓ 今天需要发送提醒\n";
    } else {
        echo "   - 今天不需要发送提醒\n";
    }
}

echo "\n";

// 测试6: 检查定时任务是否注册
echo "6. 检查定时任务...\n";
$kernelFile = file_get_contents(__DIR__ . '/../../app/Console/Kernel.php');
if (strpos($kernelFile, 'payment:check-reminders') !== false) {
    echo "   ✓ 定时任务已注册\n";
} else {
    echo "   ✗ 定时任务未注册\n";
}

echo "\n";

echo "=== 测试完成 ===\n";
echo "\n";
echo "下一步操作:\n";
echo "1. 执行 SQL: docs/sql/create_payment_reminder_tables.sql\n";
echo "2. 配置缴费日期和提醒时间\n";
echo "3. 手动运行定时任务测试: php artisan payment:check-reminders\n";
echo "4. 查看提醒是否正常生成\n";
