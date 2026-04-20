<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\OperationLog;

echo "=== 测试只修改手机号（不应记录日期字段变更） ===\n\n";

// 找一个有参保日期的员工
$employee = Employee::whereNotNull('social_insurance_enrollment_date')
    ->whereNotNull('phone')
    ->first();

if (!$employee) {
    echo "没有找到合适的测试员工\n";
    exit;
}

echo "测试员工: {$employee->name} (ID: {$employee->id})\n";
echo "原手机号: {$employee->phone}\n";
echo "社保参保日期: " . ($employee->social_insurance_enrollment_date ? $employee->social_insurance_enrollment_date->format('Y-m-d') : 'null') . "\n\n";

// 记录更新前的日志数量
$logCountBefore = OperationLog::where('model_type', 'App\Models\Employee')
    ->where('model_id', $employee->id)
    ->count();

echo "更新前日志数量: {$logCountBefore}\n\n";

// 只修改手机号（修改最后一位数字）
$originalPhone = $employee->phone;
$newPhone = substr($originalPhone, 0, -1) . '9';

echo "修改手机号为: {$newPhone}\n";
$employee->phone = $newPhone;
$employee->save();

echo "保存完成\n\n";

// 检查新增的日志
$newLog = OperationLog::where('model_type', 'App\Models\Employee')
    ->where('model_id', $employee->id)
    ->orderBy('created_at', 'desc')
    ->first();

if ($newLog) {
    echo "=== 新增的操作日志 ===\n";
    echo "操作: {$newLog->action}\n";
    echo "描述: {$newLog->description}\n\n";
    
    echo "旧值:\n";
    echo json_encode($newLog->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    
    echo "新值:\n";
    echo json_encode($newLog->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    
    // 检查是否包含日期字段
    $dateFields = ['social_insurance_enrollment_date', 'provident_fund_enrollment_date', 
                   'medical_insurance_enrollment_date', 'large_medical_enrollment_date'];
    
    $hasDateFields = false;
    foreach ($dateFields as $field) {
        if (isset($newLog->old_values[$field]) || isset($newLog->new_values[$field])) {
            $hasDateFields = true;
            break;
        }
    }
    
    if ($hasDateFields) {
        echo "❌ 测试失败：日志中包含了日期字段的变更（不应该记录）\n";
    } else {
        echo "✅ 测试成功：日志中只包含手机号的变更\n";
    }
} else {
    echo "没有找到新增的日志\n";
}

// 恢复原手机号
echo "\n恢复原手机号...\n";
$employee->phone = $originalPhone;
$employee->save();
echo "已恢复\n";
