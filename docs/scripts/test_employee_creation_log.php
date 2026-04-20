<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\OperationLog;
use Illuminate\Support\Facades\Auth;

echo "=== 测试新增员工是否记录操作日志 ===\n\n";

// 模拟登录用户
$user = \App\Models\User::first();
if (!$user) {
    echo "没有找到用户，无法测试\n";
    exit;
}

Auth::login($user);
echo "模拟登录用户: {$user->name}\n\n";

// 记录创建前的日志数量
$logCountBefore = OperationLog::count();
echo "创建前日志总数: {$logCountBefore}\n\n";

// 创建测试员工
$testData = [
    'account_set_id' => 1,
    'name' => '测试员工_' . time(),
    'id_number' => '110101' . rand(1990, 2000) . '0101' . rand(1000, 9999),
    'phone' => '138' . rand(10000000, 99999999),
    'gender' => 'male',
    'birth_date' => '1990-01-01',
    'hire_date' => '2026-01-01',
    'contract_start_date' => '2026-01-01',
    'contract_status' => 'unsigned',
    'social_insurance_enrollment_date' => '2026-01-15',
    'provident_fund_enrollment_date' => '2026-01-15',
];

echo "创建员工: {$testData['name']}\n";
$employee = Employee::create($testData);
echo "员工创建成功，ID: {$employee->id}\n\n";

// 检查是否生成了操作日志
$newLog = OperationLog::where('model_type', 'App\Models\Employee')
    ->where('model_id', $employee->id)
    ->where('action', 'created')
    ->first();

if ($newLog) {
    echo "=== 创建操作日志 ===\n";
    echo "操作人: {$newLog->user_name}\n";
    echo "操作: {$newLog->action}\n";
    echo "描述: {$newLog->description}\n";
    echo "时间: {$newLog->created_at}\n\n";
    
    echo "✅ 测试成功：新增员工时记录了操作日志\n";
} else {
    echo "❌ 测试失败：新增员工时没有记录操作日志\n";
}

// 删除测试员工
echo "\n删除测试员工...\n";
$employee->delete();
echo "已删除\n";

// 检查删除日志
$deleteLog = OperationLog::where('model_type', 'App\Models\Employee')
    ->where('model_id', $employee->id)
    ->where('action', 'deleted')
    ->first();

if ($deleteLog) {
    echo "\n=== 删除操作日志 ===\n";
    echo "操作人: {$deleteLog->user_name}\n";
    echo "描述: {$deleteLog->description}\n";
    echo "✅ 删除操作也记录了日志\n";
}
