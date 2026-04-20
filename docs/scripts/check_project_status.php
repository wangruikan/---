<?php

/**
 * 检查项目状态
 * 
 * 使用方法：
 * php docs/scripts/check_project_status.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\AccountSet;

echo "=== 检查项目状态 ===\n\n";

// 获取所有账套
$accountSets = AccountSet::all();

foreach ($accountSets as $accountSet) {
    echo "账套：{$accountSet->name} (ID: {$accountSet->id})\n";
    
    $projects = Project::where('account_set_id', $accountSet->id)->get();
    
    if ($projects->count() === 0) {
        echo "  无项目\n\n";
        continue;
    }
    
    echo "  项目数量：{$projects->count()}\n";
    
    foreach ($projects as $project) {
        echo "  - {$project->name}\n";
        echo "    状态：{$project->status}\n";
        echo "    开始日期：{$project->start_date}\n";
        echo "    结束日期：{$project->end_date}\n";
    }
    
    echo "\n";
}

echo "=== 所有可能的状态值 ===\n";
$allStatuses = Project::select('status')
    ->distinct()
    ->pluck('status')
    ->toArray();

foreach ($allStatuses as $status) {
    $count = Project::where('status', $status)->count();
    echo "- {$status}: {$count} 个项目\n";
}

echo "\n完成！\n";
