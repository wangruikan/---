<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\OtherInsuranceType;
use App\Models\OtherInsurancePolicy;
use App\Models\Employee;

$accountSetId = 1;

echo "=== 检查商业险数据 ===\n\n";

// 1. 查找商业险类型
echo "1. 查找商业险类型:\n";
$commercialType = OtherInsuranceType::where('account_set_id', $accountSetId)
    ->where('name', '商业险')
    ->first();

if ($commercialType) {
    echo "找到商业险类型: ID={$commercialType->id}, 名称={$commercialType->name}\n\n";
} else {
    echo "未找到名称为'商业险'的保险类型\n";
    echo "所有保险类型:\n";
    $types = OtherInsuranceType::where('account_set_id', $accountSetId)->get();
    foreach ($types as $type) {
        echo "  - ID={$type->id}, 名称={$type->name}\n";
    }
    exit;
}

// 2. 查找该类型下的保单
echo "2. 查找商业险保单:\n";
$policies = OtherInsurancePolicy::where('account_set_id', $accountSetId)
    ->where('type_id', $commercialType->id)
    ->get();

echo "找到 {$policies->count()} 个保单\n\n";

foreach ($policies as $policy) {
    echo "保单: {$policy->policy_name} (ID={$policy->id})\n";
    echo "  状态: {$policy->status}\n";
    echo "  参保人员列表: " . json_encode($policy->personnel_name_list, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  参保人员数量: " . (is_array($policy->personnel_name_list) ? count($policy->personnel_name_list) : 0) . "\n\n";
}

// 3. 收集所有参保员工姓名
$employeeNames = [];
foreach ($policies as $policy) {
    if ($policy->status === 'active') {
        $personnelList = $policy->personnel_name_list ?? [];
        foreach ($personnelList as $name) {
            $employeeNames[] = $name;
        }
    }
}

echo "3. 参保员工姓名列表:\n";
echo json_encode(array_unique($employeeNames), JSON_UNESCAPED_UNICODE) . "\n\n";

// 4. 查找员工
echo "4. 查找员工信息:\n";
$employees = Employee::where('account_set_id', $accountSetId)
    ->whereIn('name', array_unique($employeeNames))
    ->get();

echo "找到 {$employees->count()} 个员工\n";
foreach ($employees as $employee) {
    echo "  - {$employee->name} (ID={$employee->id})\n";
}
