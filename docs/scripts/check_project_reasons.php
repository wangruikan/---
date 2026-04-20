<?php

/**
 * 检查项目的提交原因记录
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Notification;

echo "=== 检查公园项目的提交原因记录 ===\n\n";

$projectId = 4;

$notifications = Notification::where('type', 'invoice_reason_submitted')
    ->where('data', 'LIKE', '%"project_id":' . $projectId . '%')
    ->orderBy('created_at', 'desc')
    ->get();

echo "找到 {$notifications->count()} 条提交原因记录\n\n";

foreach ($notifications as $notification) {
    $data = $notification->data;
    echo "通知 ID: {$notification->id}\n";
    echo "年月: {$data['year']}-{$data['month']}\n";
    echo "原因: {$data['reason']}\n";
    echo "创建时间: {$notification->created_at}\n";
    echo "\n";
}

echo "完成！\n";
