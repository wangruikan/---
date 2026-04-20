<?php

/**
 * 测试 LIKE 查询匹配
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Notification;

echo "=== 测试 LIKE 查询匹配 ===\n\n";

$projectId = 4;
$year = 2025;
$month = 3;

echo "查询条件：\n";
echo "- project_id: {$projectId}\n";
echo "- year: {$year}\n";
echo "- month: {$month}\n\n";

// 测试当前的 LIKE 查询
$notifications = Notification::where('type', 'invoice_reason_submitted')
    ->where('data', 'LIKE', '%"project_id":' . $projectId . '%')
    ->where('data', 'LIKE', '%"year":"' . $year . '"%')
    ->where('data', 'LIKE', '%"month":"' . $month . '"%')
    ->get();

echo "找到 {$notifications->count()} 条匹配的通知\n\n";

foreach ($notifications as $notification) {
    $data = $notification->data;
    echo "通知 ID: {$notification->id}\n";
    echo "项目ID: " . ($data['project_id'] ?? 'null') . "\n";
    echo "年份: " . ($data['year'] ?? 'null') . "\n";
    echo "月份: " . ($data['month'] ?? 'null') . "\n";
    echo "原因: " . ($data['reason'] ?? 'null') . "\n";
    echo "\n";
}

echo "完成！\n";
