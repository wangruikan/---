<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 重置测试数据 ===\n\n";

// 删除 2026-03 的依据记录
$deleted = App\Models\BasisRecord::where('month', '2026-03')->delete();
echo "删除了 {$deleted} 条依据记录\n";

// 删除 2026-03 的待办任务
$deletedTasks = App\Models\PendingTask::whereIn('task_type', ['salary_basis', 'attendance_basis'])
    ->where('route_params', 'LIKE', '%2026-03%')
    ->delete();
echo "删除了 {$deletedTasks} 个待办任务\n";

echo "\n重置完成！\n";
