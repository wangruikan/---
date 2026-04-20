<?php

/**
 * 检查 data 字段的实际存储格式
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== 检查 data 字段的实际存储格式 ===\n\n";

// 直接查询数据库，不通过模型
$notifications = DB::table('notifications')
    ->where('type', 'invoice_reason_submitted')
    ->where('id', 21)
    ->first();

if ($notifications) {
    echo "通知 ID: {$notifications->id}\n";
    echo "类型: {$notifications->type}\n";
    echo "data 字段原始内容:\n";
    echo $notifications->data . "\n\n";
    
    echo "data 字段类型: " . gettype($notifications->data) . "\n\n";
    
    // 尝试 LIKE 查询
    $projectId = 4;
    $year = 2025;
    $month = 2;
    
    $pattern1 = '%"project_id":' . $projectId . '%';
    $pattern2 = '%"year":' . $year . '%';
    $pattern3 = '%"month":' . $month . '%';
    
    echo "LIKE 查询模式:\n";
    echo "1. {$pattern1}\n";
    echo "2. {$pattern2}\n";
    echo "3. {$pattern3}\n\n";
    
    $match1 = strpos($notifications->data, '"project_id":' . $projectId) !== false;
    $match2 = strpos($notifications->data, '"year":' . $year) !== false;
    $match3 = strpos($notifications->data, '"month":' . $month) !== false;
    
    echo "匹配结果:\n";
    echo "1. project_id 匹配: " . ($match1 ? '是' : '否') . "\n";
    echo "2. year 匹配: " . ($match2 ? '是' : '否') . "\n";
    echo "3. month 匹配: " . ($match3 ? '是' : '否') . "\n";
}

echo "\n完成！\n";
