<?php

/**
 * 检查开票提醒通知
 * 
 * 使用方法：
 * php docs/scripts/check_invoice_notifications.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Notification;
use App\Models\Project;

echo "=== 检查开票提醒通知 ===\n\n";

$currentYear = 2025;
$currentMonth = 2;

// 获取所有开票相关的通知
$notifications = Notification::whereIn('type', ['invoice_reminder', 'invoice_reason_submitted'])
    ->orderBy('created_at', 'desc')
    ->get();

echo "找到 {$notifications->count()} 条开票相关通知\n\n";

foreach ($notifications as $notification) {
    echo "通知 ID: {$notification->id}\n";
    echo "类型: {$notification->type}\n";
    echo "用户ID: {$notification->user_id}\n";
    echo "标题: {$notification->title}\n";
    echo "内容: {$notification->content}\n";
    echo "已读: " . ($notification->is_read ? '是' : '否') . "\n";
    echo "创建时间: {$notification->created_at}\n";
    
    if ($notification->data) {
        echo "数据:\n";
        $data = $notification->data;
        echo "  - project_id: " . ($data['project_id'] ?? 'null') . "\n";
        echo "  - project_name: " . ($data['project_name'] ?? 'null') . "\n";
        echo "  - year: " . ($data['year'] ?? 'null') . "\n";
        echo "  - month: " . ($data['month'] ?? 'null') . "\n";
        echo "  - reason: " . ($data['reason'] ?? 'null') . "\n";
    }
    echo "\n" . str_repeat('-', 80) . "\n\n";
}

// 检查每个项目是否有已提交原因的通知
echo "=== 检查项目是否已提交原因 ===\n\n";

$projects = Project::where('status', 'active')->get();

foreach ($projects as $project) {
    echo "项目: {$project->name} (ID: {$project->id})\n";
    
    // 检查是否有已提交原因的通知
    $hasReasonSubmitted = Notification::where('type', 'invoice_reason_submitted')
        ->where('data', 'LIKE', '%"project_id":' . $project->id . '%')
        ->where('data', 'LIKE', '%"year":"' . $currentYear . '"%')
        ->where('data', 'LIKE', '%"month":"' . $currentMonth . '"%')
        ->exists();
    
    if ($hasReasonSubmitted) {
        echo "  ✓ 已提交原因\n";
        
        // 显示具体的通知
        $reasonNotifications = Notification::where('type', 'invoice_reason_submitted')
            ->where('data', 'LIKE', '%"project_id":' . $project->id . '%')
            ->where('data', 'LIKE', '%"year":"' . $currentYear . '"%')
            ->where('data', 'LIKE', '%"month":"' . $currentMonth . '"%')
            ->get();
        
        foreach ($reasonNotifications as $n) {
            echo "    通知ID: {$n->id}, 用户ID: {$n->user_id}\n";
        }
    } else {
        echo "  ✗ 未提交原因\n";
    }
    
    echo "\n";
}

echo "完成！\n";
