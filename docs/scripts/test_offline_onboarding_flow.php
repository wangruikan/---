<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== 测试线下入职完整流程 ===\n\n";

// 1. 查找一个未入职的员工
$employee = \App\Models\Employee::where('contract_status', 'unsigned')
    ->whereNotNull('account_set_id')
    ->first();

if (!$employee) {
    echo "❌ 没有找到未入职的员工\n";
    exit;
}

echo "✓ 找到测试员工:\n";
echo "  ID: {$employee->id}\n";
echo "  姓名: {$employee->name}\n";
echo "  身份证: {$employee->id_number}\n";
echo "  当前状态: {$employee->contract_status}\n";
echo "  账套ID: {$employee->account_set_id}\n";
echo "\n";

// 2. 检查员工是否有项目
$projects = $employee->projects;
echo "关联的项目: " . $projects->count() . " 个\n";
if ($projects->count() === 0) {
    echo "  ⚠️ 该员工没有关联项目，可能影响保险信息导入\n";
}
echo "\n";

// 3. 检查账套的审批人配置
$approvers = DB::table('account_set_users')
    ->where('account_set_id', $employee->account_set_id)
    ->where('approval_level', '>', 1)
    ->orderBy('approval_level')
    ->get();

echo "审批人配置:\n";
if ($approvers->isEmpty()) {
    echo "  ❌ 没有找到审批人配置！\n";
    exit;
}

foreach ($approvers as $approver) {
    $user = \App\Models\User::find($approver->user_id);
    echo "  - 级别 {$approver->approval_level}: {$user->name} (ID: {$user->id})\n";
}
echo "\n";

// 4. 模拟提交线下入职审批
echo "开始模拟提交线下入职审批...\n";

DB::beginTransaction();
try {
    // 获取第一个审批人作为提交人
    $submitter = \App\Models\User::find($approvers->first()->user_id);
    
    // 创建审批实例
    $instance = \App\Models\ApprovalInstance::create([
        'account_set_id' => $employee->account_set_id,
        'business_type' => 'offline_onboarding',
        'business_id' => $employee->id,
        'current_step' => 2,
        'total_steps' => $approvers->count() + 1,
        'status' => 'pending',
        'created_by' => $submitter->id,
        'stamp_method' => 'offline',
    ]);
    
    echo "✓ 创建审批实例: ID {$instance->id}\n";
    
    // 创建经办节点（自动通过）
    \App\Models\ApprovalRecord::create([
        'instance_id' => $instance->id,
        'step_order' => 1,
        'step_name' => '经办',
        'approver_id' => $submitter->id,
        'approver_name' => $submitter->name,
        'status' => 'approved',
        'comment' => '线下入职申请，经办自动通过',
        'approved_at' => now(),
    ]);
    
    // 创建第一个审批节点
    $firstApprover = $approvers->first();
    $firstApproverUser = \App\Models\User::find($firstApprover->user_id);
    
    \App\Models\ApprovalRecord::create([
        'instance_id' => $instance->id,
        'step_order' => 2,
        'step_name' => $firstApprover->approval_level_name,
        'approver_id' => $firstApprover->user_id,
        'approver_name' => $firstApproverUser->name,
        'status' => 'pending',
    ]);
    
    echo "✓ 创建审批记录\n\n";
    
    // 5. 模拟审批通过
    echo "开始模拟审批通过...\n";
    
    $approvalService = new \App\Services\ApprovalService();
    
    // 逐个审批节点通过
    foreach ($approvers as $index => $approver) {
        $stepOrder = $index + 2;
        echo "  审批节点 {$stepOrder}: {$approver->approval_level_name}...\n";
        
        $result = $approvalService->approve(
            $instance->id,
            $approver->user_id,
            $employee->account_set_id,
            '同意'
        );
        
        if (!$result) {
            throw new \Exception("审批失败");
        }
        
        $instance->refresh();
        echo "    状态: {$instance->status}\n";
    }
    
    echo "\n";
    
    // 6. 检查结果
    $employee->refresh();
    
    echo "=== 审批完成后的结果 ===\n\n";
    echo "员工状态:\n";
    echo "  contract_status: {$employee->contract_status}\n";
    echo "  hire_date: {$employee->hire_date}\n";
    echo "  is_offline_onboarding: " . ($employee->is_offline_onboarding ? 'true' : 'false') . "\n";
    echo "  offline_onboarding_date: {$employee->offline_onboarding_date}\n";
    echo "  contract_upload_deadline: {$employee->contract_upload_deadline}\n";
    echo "\n";
    
    // 检查保险变更记录
    $insuranceChanges = \App\Models\InsuranceChange::where('employee_id', $employee->id)
        ->orderBy('created_at', 'desc')
        ->get();
    
    echo "保险变更记录: " . $insuranceChanges->count() . " 条\n";
    if ($insuranceChanges->count() > 0) {
        foreach ($insuranceChanges as $change) {
            echo "  - ID: {$change->id}, 类型: {$change->change_type}, 状态: {$change->status}\n";
        }
        echo "  ✓ 保险信息已自动导入！\n";
    } else {
        echo "  ❌ 没有创建保险变更记录！\n";
    }
    
    DB::rollBack();
    echo "\n✓ 测试完成（已回滚）\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== 测试结束 ===\n";
