<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 检查所有 2026-01 月份的工资表
$month = '2026-01';

echo "检查所有项目的 {$month} 月工资表:\n\n";

$salaries = \App\Models\Salary::where('month', $month)
    ->orderBy('project_id')
    ->get();

if ($salaries->isEmpty()) {
    echo "没有找到工资表记录\n";
} else {
    echo "找到 " . $salaries->count() . " 条记录:\n\n";
    
    $grouped = $salaries->groupBy('project_id');
    
    foreach ($grouped as $projectId => $projectSalaries) {
        echo "项目 ID: {$projectId}\n";
        foreach ($projectSalaries as $salary) {
            echo "  - 工资表 ID: {$salary->id}, 状态: {$salary->status}, 员工: {$salary->employee_name}\n";
        }
        
        // 统计该项目的状态
        $notRejectedCount = $projectSalaries->where('status', '!=', 'rejected')->count();
        $rejectedCount = $projectSalaries->where('status', '=', 'rejected')->count();
        
        echo "  总计: {$projectSalaries->count()} 条, 非驳回: {$notRejectedCount} 条, 已驳回: {$rejectedCount} 条\n";
        echo "  ⚠️ 如果非驳回 > 0，则不能创建新工资表\n";
        echo "------------------------\n";
    }
}

echo "\n\n如果你要测试项目 19 的 2026-01 月份:\n";
$project19Count = \App\Models\Salary::where('project_id', 19)
    ->where('month', $month)
    ->where('status', '!=', 'rejected')
    ->count();

echo "项目 19 非驳回状态的记录数: {$project19Count}\n";
echo $project19Count > 0 ? "❌ 不能创建（有非驳回记录）\n" : "✅ 可以创建（没有非驳回记录）\n";
