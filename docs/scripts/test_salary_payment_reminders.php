<?php

/**
 * 测试工资发放提醒
 * 
 * 使用方法：
 * php docs/scripts/test_salary_payment_reminders.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\AccountSet;
use Carbon\Carbon;

echo "=== 测试工资发放提醒 ===\n\n";

// 获取明天的日期
$tomorrow = Carbon::tomorrow();
$tomorrowDay = $tomorrow->day;

echo "明天日期：{$tomorrow->toDateString()}（{$tomorrowDay}号）\n\n";

// 获取所有账套
$accountSets = AccountSet::all();

echo "=== 检查各账套的项目工资发放日期 ===\n\n";

foreach ($accountSets as $accountSet) {
    echo "账套：{$accountSet->name} (ID: {$accountSet->id})\n";
    
    $projects = Project::where('account_set_id', $accountSet->id)
        ->where('status', 'active')
        ->get();
    
    if ($projects->count() === 0) {
        echo "  无进行中的项目\n\n";
        continue;
    }
    
    echo "  进行中的项目：\n";
    foreach ($projects as $project) {
        $paymentDate = $project->salary_payment_date;
        $status = $paymentDate == $tomorrowDay ? '✓ 明天发放' : '';
        echo "    - {$project->name}: ";
        if ($paymentDate) {
            echo "每月{$paymentDate}号 {$status}\n";
        } else {
            echo "未设置\n";
        }
    }
    echo "\n";
}

echo "=== 明天需要发放工资的项目 ===\n\n";

foreach ($accountSets as $accountSet) {
    $projects = Project::where('account_set_id', $accountSet->id)
        ->where('status', 'active')
        ->where('salary_payment_date', $tomorrowDay)
        ->get();
    
    if ($projects->count() > 0) {
        echo "账套：{$accountSet->name}\n";
        foreach ($projects as $project) {
            echo "  - {$project->name}\n";
        }
        echo "\n";
    }
}

echo "完成！\n";
