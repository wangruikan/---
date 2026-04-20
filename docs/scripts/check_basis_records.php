<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 检查 2026-03 的依据记录 ===\n\n";

$records = App\Models\BasisRecord::where('month', '2026-03')
    ->with(['project'])
    ->get();

if ($records->isEmpty()) {
    echo "没有找到 2026-03 的依据记录\n";
} else {
    echo "找到 {$records->count()} 条记录：\n\n";
    foreach ($records as $record) {
        echo "ID: {$record->id}\n";
        echo "项目: {$record->project->name} (ID: {$record->project_id})\n";
        echo "类型: " . ($record->type === 'salary' ? '工资依据' : '考勤依据') . "\n";
        echo "月份: {$record->month}\n";
        echo "创建时间: {$record->created_at}\n";
        echo "---\n";
    }
}

echo "\n=== 检查公园项目的配置 ===\n\n";

$project = App\Models\Project::where('name', '公园项目')->first();
if ($project) {
    echo "项目ID: {$project->id}\n";
    echo "需要工资依据: " . ($project->requires_salary_basis ? '是' : '否') . "\n";
    echo "需要考勤依据: " . ($project->requires_attendance_basis ? '是' : '否') . "\n";
} else {
    echo "未找到公园项目\n";
}

echo "\n=== 检查待办任务 ===\n\n";

$tasks = App\Models\PendingTask::whereIn('task_type', ['salary_basis', 'attendance_basis'])
    ->where('route_params', 'LIKE', '%2026-03%')
    ->get();

if ($tasks->isEmpty()) {
    echo "没有找到 2026-03 的依据待办任务\n";
} else {
    echo "找到 {$tasks->count()} 个待办任务：\n\n";
    foreach ($tasks as $task) {
        echo "ID: {$task->id}\n";
        echo "类型: {$task->task_type}\n";
        echo "标题: {$task->title}\n";
        echo "状态: {$task->status}\n";
        echo "处理人: {$task->handler_name}\n";
        echo "---\n";
    }
}
