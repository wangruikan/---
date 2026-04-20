<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 调试任务查询条件 ===\n\n";

$basisRecord = App\Models\BasisRecord::find(9);
$task = App\Models\PendingTask::find(9);

echo "依据记录：\n";
echo "  account_set_id: {$basisRecord->account_set_id}\n";
echo "  project_id: {$basisRecord->project_id}\n";
echo "  type: {$basisRecord->type}\n";
echo "  month: {$basisRecord->month}\n\n";

echo "待办任务：\n";
echo "  account_set_id: {$task->account_set_id}\n";
echo "  task_type: {$task->task_type}\n";
echo "  related_id: {$task->related_id}\n";
echo "  related_type: {$task->related_type}\n";
echo "  status: {$task->status}\n";
echo "  route_params: {$task->route_params}\n\n";

// 测试查询
echo "测试查询条件：\n";
$query = App\Models\PendingTask::where('account_set_id', $basisRecord->account_set_id)
    ->where('task_type', 'attendance_basis')
    ->where('related_id', $basisRecord->project_id)
    ->where('related_type', 'Project')
    ->where('status', 'pending');

echo "SQL: " . $query->toSql() . "\n";
echo "Bindings: " . json_encode($query->getBindings()) . "\n\n";

// 测试 LIKE 查询
$likeQuery = clone $query;
$likeQuery->where('route_params', 'LIKE', '%"month":"' . $basisRecord->month . '"%');
echo "LIKE 查询 SQL: " . $likeQuery->toSql() . "\n";
echo "LIKE 查询 Bindings: " . json_encode($likeQuery->getBindings()) . "\n";
echo "LIKE 查询结果数量: " . $likeQuery->count() . "\n\n";

// 检查 route_params 内容
echo "route_params 内容分析：\n";
echo "原始内容: {$task->route_params}\n";
$params = json_decode($task->route_params, true);
echo "解析后: " . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "month 值: " . ($params['month'] ?? 'null') . "\n";
echo "匹配字符串: " . '%"month":"' . $basisRecord->month . '"%' . "\n";
echo "是否匹配: " . (strpos($task->route_params, '"month":"' . $basisRecord->month . '"') !== false ? '是' : '否') . "\n";
