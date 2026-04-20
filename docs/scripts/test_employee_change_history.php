<?php

/**
 * 测试员工变更历史记录功能
 * 
 * 使用方法：
 * php docs/scripts/test_employee_change_history.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Employee;
use App\Models\OperationLog;

echo "=== 测试员工变更历史记录功能 ===\n\n";

// 1. 查找一个员工
$employee = Employee::first();

if (!$employee) {
    echo "❌ 没有找到员工数据\n";
    exit(1);
}

echo "✓ 找到员工: {$employee->name} (ID: {$employee->id})\n\n";

// 2. 修改员工信息以创建变更记录
echo "--- 修改员工信息 ---\n";
$oldPhone = $employee->phone;
$newPhone = '13800138000';

$employee->phone = $newPhone;
$employee->position = '测试岗位';
$employee->save();

echo "✓ 已修改员工信息\n";
echo "  - 手机号: {$oldPhone} → {$newPhone}\n";
echo "  - 岗位: 测试岗位\n\n";

// 3. 查询变更历史
echo "--- 查询变更历史 ---\n";
$logs = OperationLog::where('model_type', Employee::class)
    ->where('model_id', $employee->id)
    ->where('action', 'updated')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "✓ 找到 {$logs->count()} 条变更记录\n\n";

foreach ($logs as $index => $log) {
    echo "记录 " . ($index + 1) . ":\n";
    echo "  操作人: {$log->user_name}\n";
    echo "  描述: {$log->description}\n";
    echo "  时间: {$log->created_at}\n";
    echo "  IP: {$log->ip_address}\n";
    
    if ($log->old_values && $log->new_values) {
        $oldValues = $log->old_values; // 已经是数组
        $newValues = $log->new_values; // 已经是数组
        
        echo "  变更字段:\n";
        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? 'null';
            echo "    - {$field}: {$oldValue} → {$newValue}\n";
        }
    }
    echo "\n";
}

echo "=== 测试完成 ===\n";
echo "\nAPI接口: GET /api/employees/{$employee->id}/change-history\n";
