<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use Carbon\Carbon;

// 测试日期比较逻辑
echo "=== 测试日期字段比较 ===\n\n";

// 找一个有参保日期的员工
$employee = Employee::whereNotNull('social_insurance_enrollment_date')->first();

if (!$employee) {
    echo "没有找到有参保日期的员工\n";
    exit;
}

echo "员工ID: {$employee->id}\n";
echo "员工姓名: {$employee->name}\n\n";

// 显示原始数据库值
echo "=== 数据库原始值 ===\n";
$rawData = \DB::table('employees')->where('id', $employee->id)->first();
echo "social_insurance_enrollment_date (raw): " . ($rawData->social_insurance_enrollment_date ?? 'null') . "\n";
echo "provident_fund_enrollment_date (raw): " . ($rawData->provident_fund_enrollment_date ?? 'null') . "\n";
echo "medical_insurance_enrollment_date (raw): " . ($rawData->medical_insurance_enrollment_date ?? 'null') . "\n";
echo "large_medical_enrollment_date (raw): " . ($rawData->large_medical_enrollment_date ?? 'null') . "\n\n";

// 显示模型属性值
echo "=== 模型属性值 ===\n";
echo "social_insurance_enrollment_date (model): " . ($employee->social_insurance_enrollment_date ? $employee->social_insurance_enrollment_date->format('Y-m-d H:i:s') : 'null') . "\n";
echo "provident_fund_enrollment_date (model): " . ($employee->provident_fund_enrollment_date ? $employee->provident_fund_enrollment_date->format('Y-m-d H:i:s') : 'null') . "\n";
echo "medical_insurance_enrollment_date (model): " . ($employee->medical_insurance_enrollment_date ? $employee->medical_insurance_enrollment_date->format('Y-m-d H:i:s') : 'null') . "\n";
echo "large_medical_enrollment_date (model): " . ($employee->large_medical_enrollment_date ? $employee->large_medical_enrollment_date->format('Y-m-d H:i:s') : 'null') . "\n\n";

// 测试更新（只修改手机号）
echo "=== 测试更新（只修改手机号） ===\n";
$originalPhone = $employee->phone;
$newPhone = $originalPhone . '_test';

echo "原手机号: {$originalPhone}\n";
echo "新手机号: {$newPhone}\n\n";

// 获取更新前的原始值
$originalValues = $employee->getOriginal();
echo "更新前的原始值:\n";
echo "  phone: " . ($originalValues['phone'] ?? 'null') . "\n";
echo "  social_insurance_enrollment_date: " . ($originalValues['social_insurance_enrollment_date'] ?? 'null') . "\n\n";

// 执行更新
$employee->phone = $newPhone;
$employee->save();

echo "更新完成\n\n";

// 检查操作日志
$latestLog = \App\Models\OperationLog::where('model_type', 'App\Models\Employee')
    ->where('model_id', $employee->id)
    ->orderBy('created_at', 'desc')
    ->first();

if ($latestLog) {
    echo "=== 最新操作日志 ===\n";
    echo "操作: {$latestLog->action}\n";
    echo "描述: {$latestLog->description}\n";
    echo "旧值: " . json_encode($latestLog->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "新值: " . json_encode($latestLog->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} else {
    echo "没有找到操作日志\n";
}

// 恢复原手机号
$employee->phone = $originalPhone;
$employee->save();

echo "\n已恢复原手机号\n";
