<?php
/**
 * 测试数据种子脚本 - 人员变动申请
 *
 * 创建测试所需的全部记录，输出 JSON 格式的 ID 供 Playwright 测试使用。
 * 用法: php tests/api/seed-personnel-change.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PersonnelChangeRequest;
use App\Models\PersonnelChangeRequestAttachment;
use Illuminate\Support\Facades\DB;

$accountSetIdA = 1;
$accountSetIdB = 3;
$projectIdA = 4; // 公园项目 (acct=1)
$projectIdB = 6; // 老年公寓 (acct=1)

$personnel2 = [
    ['name' => '张三', 'id_card' => '110101199001010001'],
    ['name' => '李四', 'id_card' => '110101199001010002'],
];

$personnel1 = [
    ['name' => '王五', 'id_card' => '110101199001010003'],
];

$personnel3 = [
    ['name' => '赵六', 'id_card' => '110101199001010004'],
    ['name' => '钱七', 'id_card' => '110101199001010005'],
    ['name' => '孙八', 'id_card' => '110101199001010006'],
];

function createRecord($data) {
    $existing = PersonnelChangeRequest::where('account_set_id', $data['account_set_id'])
        ->where('project_id', $data['project_id'])
        ->where('month', $data['month'])
        ->where('change_type', $data['change_type'])
        ->withTrashed()
        ->first();

    if ($existing) {
        // 如果已存在但状态不同，更新状态
        if ($existing->status !== $data['status'] || $existing->deleted_at !== null) {
            if ($existing->deleted_at !== null) {
                // 恢复软删除
                $existing->restore();
            }
            $existing->update([
                'status' => $data['status'],
                'personnel_list' => $data['personnel_list'],
                'approval_flow_id' => $data['approval_flow_id'] ?? null,
                'remark' => $data['remark'] ?? null,
            ]);
        }
        return $existing;
    }

    return PersonnelChangeRequest::create($data);
}

$results = [];

// 1. pending / add / 项目A / 2025-01 (已有 2 人)
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-01',
    'change_type' => 'add',
    'personnel_list' => $personnel2,
    'remark' => '测试-pending',
    'status' => 'pending',
    'created_by' => 1,
]);
$results['PENDING_ADD'] = $rec->id;

// 2. rejected / remove / 项目A / 2025-02 (1 人)
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-02',
    'change_type' => 'remove',
    'personnel_list' => $personnel1,
    'remark' => '测试-rejected',
    'status' => 'rejected',
    'created_by' => 1,
]);
$results['REJECTED_REMOVE'] = $rec->id;

// 3. in_approval / add / 项目B / 2025-03 (3 人)
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdB,
    'month' => '2025-03',
    'change_type' => 'add',
    'personnel_list' => $personnel3,
    'remark' => '测试-in_approval',
    'status' => 'in_approval',
    'approval_flow_id' => 999001,
    'created_by' => 1,
]);
$results['IN_APPROVAL_ADD'] = $rec->id;

// 4. approved / remove / 项目B / 2025-04 (1 人)
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdB,
    'month' => '2025-04',
    'change_type' => 'remove',
    'personnel_list' => $personnel1,
    'remark' => '测试-approved',
    'status' => 'approved',
    'approval_flow_id' => 999002,
    'created_by' => 1,
]);
$results['APPROVED_REMOVE'] = $rec->id;

// 5. 其他账套数据 / pending / add / 2025-05 (用于越权测试)
$rec = createRecord([
    'account_set_id' => $accountSetIdB,
    'project_id' => $projectIdA,  // 跨账套用同项目
    'month' => '2025-05',
    'change_type' => 'add',
    'personnel_list' => $personnel2,
    'remark' => '测试-other-account',
    'status' => 'pending',
    'created_by' => 1,
]);
$results['OTHER_ACCOUNT'] = $rec->id;

// 6. 用于上传附件测试的 pending 记录 (独立于提交/删除测试)
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-06',
    'change_type' => 'add',
    'personnel_list' => $personnel2,
    'remark' => '测试-upload',
    'status' => 'pending',
    'created_by' => 1,
]);
$results['UPLOAD_TARGET'] = $rec->id;

// 7. 用于提交审批测试的 pending 记录
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-07',
    'change_type' => 'add',
    'personnel_list' => $personnel2,
    'remark' => '测试-submit',
    'status' => 'pending',
    'created_by' => 1,
]);
$results['SUBMIT_PENDING'] = $rec->id;

// 8. 用于 rejected 提交测试
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-08',
    'change_type' => 'remove',
    'personnel_list' => $personnel1,
    'remark' => '测试-submit-rejected',
    'status' => 'rejected',
    'created_by' => 1,
]);
$results['SUBMIT_REJECTED'] = $rec->id;

// 9. 用于删除测试的 pending 记录
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-09',
    'change_type' => 'add',
    'personnel_list' => $personnel1,
    'remark' => '测试-delete-pending',
    'status' => 'pending',
    'created_by' => 1,
]);
$results['DELETE_PENDING'] = $rec->id;

// 10. 用于删除测试的 rejected 记录
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-10',
    'change_type' => 'remove',
    'personnel_list' => $personnel1,
    'remark' => '测试-delete-rejected',
    'status' => 'rejected',
    'created_by' => 1,
]);
$results['DELETE_REJECTED'] = $rec->id;

// 11. 有附件的 pending 记录 (先创建记录，附件稍后通过 API 上传)
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-11',
    'change_type' => 'add',
    'personnel_list' => $personnel3,
    'remark' => '测试-with-attachment',
    'status' => 'pending',
    'created_by' => 1,
]);
$results['WITH_ATTACHMENT'] = $rec->id;

// 12. 用于重复提交测试
$rec = createRecord([
    'account_set_id' => $accountSetIdA,
    'project_id' => $projectIdA,
    'month' => '2025-12',
    'change_type' => 'add',
    'personnel_list' => $personnel1,
    'remark' => '测试-double-submit',
    'status' => 'pending',
    'created_by' => 1,
]);
$results['DOUBLE_SUBMIT'] = $rec->id;

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
