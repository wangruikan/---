<?php

/**
 * 测试开票提醒功能
 * 
 * 使用方法：
 * php docs/scripts/test_invoice_reminders.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\InvoiceApplication;
use App\Models\Notification;
use App\Models\User;
use App\Models\AccountSet;

echo "=== 开票提醒功能测试 ===\n\n";

// 获取第一个账套
$accountSet = AccountSet::first();
if (!$accountSet) {
    echo "错误：未找到账套\n";
    exit(1);
}

echo "测试账套：{$accountSet->name} (ID: {$accountSet->id})\n\n";

// 获取进行中的项目
$projects = Project::where('account_set_id', $accountSet->id)
    ->where('status', 'in_progress')
    ->get();

echo "进行中的项目数量：" . $projects->count() . "\n\n";

$currentYear = date('Y');
$currentMonth = date('n');

foreach ($projects as $project) {
    echo "项目：{$project->name}\n";
    
    // 检查本月是否有发票申请
    $hasInvoice = InvoiceApplication::where('account_set_id', $accountSet->id)
        ->where('project_name', $project->name)
        ->where('year', $currentYear)
        ->where('month', $currentMonth)
        ->exists();
    
    if ($hasInvoice) {
        echo "  ✓ 本月已开票\n";
    } else {
        echo "  ✗ 本月未开票\n";
        
        // 检查是否已有提醒
        $existingReminder = Notification::where('type', 'invoice_reminder')
            ->where('data', 'LIKE', '%"project_id":' . $project->id . '%')
            ->where('data', 'LIKE', '%"year":' . $currentYear . '%')
            ->where('data', 'LIKE', '%"month":' . $currentMonth . '%')
            ->first();
        
        if ($existingReminder) {
            echo "  → 已有提醒记录 (ID: {$existingReminder->id})\n";
        } else {
            echo "  → 无提醒记录\n";
        }
    }
    echo "\n";
}

// 获取有开票权限的用户
echo "=== 有开票权限的用户 ===\n";
$users = User::whereHas('accountSets', function($query) use ($accountSet) {
        $query->where('account_sets.id', $accountSet->id);
    })
    ->where(function($query) {
        $query->whereIn('role', ['admin', 'super_admin'])
              ->orWhereHas('accountSets', function($q) {
                  $q->whereIn('account_set_users.approval_level', [2, 3, 4]);
              });
    })
    ->get();

foreach ($users as $user) {
    echo "- {$user->name} (角色: {$user->role})\n";
}

echo "\n=== 执行定时任务 ===\n";
Artisan::call('invoice:check-reminders', ['--account_set_id' => $accountSet->id]);
echo Artisan::output();

echo "\n=== 检查提醒记录 ===\n";
$reminders = Notification::where('type', 'invoice_reminder')
    ->where('data', 'LIKE', '%"account_set_id":' . $accountSet->id . '%')
    ->where('data', 'LIKE', '%"year":' . $currentYear . '%')
    ->where('data', 'LIKE', '%"month":' . $currentMonth . '%')
    ->get();

echo "提醒记录数量：" . $reminders->count() . "\n\n";

foreach ($reminders as $reminder) {
    $data = $reminder->data;
    $user = User::find($reminder->user_id);
    echo "- 用户：{$user->name}\n";
    echo "  项目：{$data['project_name']}\n";
    echo "  期间：{$data['year']}年{$data['month']}月\n";
    echo "  消息：{$reminder->message}\n\n";
}

echo "测试完成！\n";
