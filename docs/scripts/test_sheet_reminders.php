<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 测试考勤表和工资表待办任务 ===\n\n";

// 1. 检查进行中的项目
echo "[1] 检查进行中的项目...\n";
$projects = App\Models\Project::where('status', 'active')->get();
echo "找到 {$projects->count()} 个进行中的项目\n\n";

foreach ($projects as $project) {
    echo "项目: {$project->name} (ID: {$project->id})\n";
    echo "  账套ID: {$project->account_set_id}\n";
    echo "  需要考勤表: " . ($project->require_attendance ? '是' : '否') . "\n";
    echo "---\n";
}

// 2. 运行定时任务（测试模式）
echo "\n[2] 运行定时任务（测试模式）...\n";
echo "执行命令: php artisan sheet:check-reminders --date=2026-03-01 --time=08:00\n\n";

// 3. 检查待办任务
echo "[3] 检查待办任务...\n";
$tasks = App\Models\PendingTask::whereIn('task_type', ['attendance_sheet', 'salary_sheet'])
    ->where('status', 'pending')
    ->get();

if ($tasks->isEmpty()) {
    echo "没有找到考勤表和工资表的待办任务\n";
} else {
    echo "找到 {$tasks->count()} 个待办任务：\n\n";
    foreach ($tasks as $task) {
        echo "ID: {$task->id}\n";
        echo "类型: {$task->task_type}\n";
        echo "标题: {$task->title}\n";
        echo "状态: {$task->status}\n";
        echo "处理人: {$task->handler_name}\n";
        echo "创建时间: {$task->created_at}\n";
        echo "---\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "\n提示：\n";
echo "1. 运行命令: php artisan sheet:check-reminders --date=2026-03-01 --time=08:00\n";
echo "2. 考勤表待办只会为开启了'需要考勤表'的项目创建\n";
echo "3. 工资表待办会为所有进行中的项目创建\n";
echo "4. 待办任务分配给项目的第一个审批节点的人（业务人员）\n";
