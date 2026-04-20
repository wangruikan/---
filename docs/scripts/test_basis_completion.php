<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 测试依据上传和任务完成 ===\n\n";

// 1. 创建工资依据
echo "1. 创建工资依据记录...\n";
$salaryBasis = App\Models\BasisRecord::create([
    'account_set_id' => 1,
    'project_id' => 4,
    'type' => 'salary',
    'month' => '2026-03',
    'description' => '测试工资依据',
    'created_by' => 1,
]);
echo "   创建成功，ID: {$salaryBasis->id}\n";

// 触发任务完成检测
echo "   触发任务完成检测...\n";
App\Services\PendingTaskService::checkAndCompleteSalaryBasisTask($salaryBasis);

// 检查任务状态
$salaryTask = App\Models\PendingTask::where('task_type', 'salary_basis')
    ->where('related_id', 4)
    ->where('route_params', 'LIKE', '%2026-03%')
    ->first();

if ($salaryTask) {
    echo "   任务状态: {$salaryTask->status}\n";
    if ($salaryTask->status === 'completed') {
        echo "   ✓ 工资依据任务已自动完成\n";
    } else {
        echo "   ✗ 工资依据任务未完成\n";
    }
} else {
    echo "   ✗ 未找到工资依据任务\n";
}

echo "\n";

// 2. 创建考勤依据
echo "2. 创建考勤依据记录...\n";
$attendanceBasis = App\Models\BasisRecord::create([
    'account_set_id' => 1,
    'project_id' => 4,
    'type' => 'attendance',
    'month' => '2026-03',
    'description' => '测试考勤依据',
    'created_by' => 1,
]);
echo "   创建成功，ID: {$attendanceBasis->id}\n";

// 触发任务完成检测
echo "   触发任务完成检测...\n";
App\Services\PendingTaskService::checkAndCompleteAttendanceBasisTask($attendanceBasis);

// 检查任务状态
$attendanceTask = App\Models\PendingTask::where('task_type', 'attendance_basis')
    ->where('related_id', 4)
    ->where('route_params', 'LIKE', '%2026-03%')
    ->first();

if ($attendanceTask) {
    echo "   任务状态: {$attendanceTask->status}\n";
    if ($attendanceTask->status === 'completed') {
        echo "   ✓ 考勤依据任务已自动完成\n";
    } else {
        echo "   ✗ 考勤依据任务未完成\n";
    }
} else {
    echo "   ✗ 未找到考勤依据任务\n";
}

echo "\n=== 测试完成 ===\n";
