<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 检查通知记录 ===" . PHP_EOL . PHP_EOL;

$notifications = DB::table('notifications')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "最近的 5 条通知：" . PHP_EOL;
foreach ($notifications as $notification) {
    echo "-------------------" . PHP_EOL;
    echo "通知 ID: {$notification->id}" . PHP_EOL;
    echo "用户ID: {$notification->user_id}" . PHP_EOL;
    echo "类型: {$notification->type}" . PHP_EOL;
    echo "标题: {$notification->title}" . PHP_EOL;
    echo "内容: {$notification->content}" . PHP_EOL;
    echo "已读: " . ($notification->is_read ? '是' : '否') . PHP_EOL;
    echo "创建时间: {$notification->created_at}" . PHP_EOL;
}

echo PHP_EOL . "=== 检查提醒日志 ===" . PHP_EOL . PHP_EOL;

$logs = DB::table('payment_reminder_logs')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "最近的 5 条提醒日志：" . PHP_EOL;
foreach ($logs as $log) {
    echo "-------------------" . PHP_EOL;
    echo "日志 ID: {$log->id}" . PHP_EOL;
    echo "账套ID: {$log->account_set_id}" . PHP_EOL;
    echo "付款申请ID: {$log->payment_request_id}" . PHP_EOL;
    echo "类型: {$log->payment_type}" . PHP_EOL;
    echo "年月: {$log->year}-{$log->month}" . PHP_EOL;
    echo "缴费日期: {$log->due_date}" . PHP_EOL;
    echo "提醒日期: {$log->reminder_date}" . PHP_EOL;
    echo "提醒时间: {$log->reminder_time}" . PHP_EOL;
    echo "通知用户ID: {$log->notified_user_id}" . PHP_EOL;
    echo "通知ID: {$log->notification_id}" . PHP_EOL;
}

echo PHP_EOL . "=== 检查完成 ===" . PHP_EOL;
