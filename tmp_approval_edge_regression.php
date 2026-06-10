<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Console\Commands\CheckInsuranceSummaryReminders;
use App\Console\Commands\CheckSalaryPaymentReminders;
use App\Models\AccountSet;
use App\Models\ApprovalFlowConfig;
use App\Models\ApprovalInstance;
use App\Models\Notification;
use App\Models\PaymentDueDateConfig;
use App\Models\ProcessApproval;
use App\Models\Project;
use App\Models\User;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function ensureTrue($condition, $message, array $context = []): void
{
    if (!$condition) {
        throw new RuntimeException(
            $message . (empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE))
        );
    }
}

function callJson($kernel, string $method, string $uri, User $actor, ?array $data = null): array
{
    Sanctum::actingAs($actor);
    $server = ['HTTP_ACCEPT' => 'application/json'];

    if ($data === null) {
        $request = Request::create($uri, $method, [], [], [], $server);
    } else {
        $server['CONTENT_TYPE'] = 'application/json';
        $request = Request::create($uri, $method, [], [], [], $server, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    $response = $kernel->handle($request);
    $content = $response->getContent();
    $decoded = json_decode($content, true);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $decoded, $content];
}

function setConfig(int $accountSetId, string $businessType, array $levels): void
{
    ApprovalFlowConfig::updateOrCreate(
        [
            'account_set_id' => $accountSetId,
            'business_type' => $businessType,
        ],
        [
            'enabled_levels' => $levels,
        ]
    );
}

function createApprover(int $accountSetId, int $level, string $namePrefix): User
{
    $user = User::create([
        'name' => $namePrefix . '_' . substr(uniqid(), -5),
        'nickname' => $namePrefix,
        'email' => $namePrefix . '_' . uniqid() . '@example.com',
        'phone' => '166' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'password' => Hash::make('test123456'),
        'role' => 'admin',
        'current_account_set_id' => $accountSetId,
        'is_active' => true,
    ]);

    DB::table('account_set_users')->insert([
        'account_set_id' => $accountSetId,
        'user_id' => $user->id,
        'role' => 'admin',
        'approval_level' => $level,
        'approval_level_name' => '第' . $level . '级审批',
        'is_default' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function createAccountSet(User $admin, string $suffix): AccountSet
{
    $accountSet = AccountSet::create([
        'name' => 'codex-edge-' . $suffix,
        'code' => 'EDGE' . substr(uniqid(), -5),
        'company_name' => 'Codex Edge ' . $suffix,
        'status' => 'active',
        'is_default' => false,
        'created_by' => $admin->id,
        'enabled_date' => now()->toDateString(),
    ]);

    DB::table('account_set_users')->insert([
        'account_set_id' => $accountSet->id,
        'user_id' => $admin->id,
        'role' => 'admin',
        'approval_level' => 1,
        'approval_level_name' => '经办',
        'is_default' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $accountSet;
}

function createProject(int $accountSetId, string $name, Carbon $baseDate): Project
{
    return Project::create([
        'account_set_id' => $accountSetId,
        'name' => $name,
        'code' => 'P' . substr(uniqid(), -5),
        'status' => 'active',
        'start_date' => $baseDate->copy()->subMonth()->toDateString(),
        'end_date' => $baseDate->copy()->addYear()->toDateString(),
        'salary_payment_date' => $baseDate->copy()->addDay()->day,
        'require_attendance' => true,
        'requires_attendance' => true,
        'requires_salary_basis' => true,
        'requires_attendance_basis' => true,
    ]);
}

function createProcess(int $accountSetId, int $initiatorId, int $projectId, string $title, string $month): ProcessApproval
{
    return ProcessApproval::create([
        'account_set_id' => $accountSetId,
        'initiator_id' => $initiatorId,
        'title' => $title,
        'category' => 'social_insurance',
        'month' => $month,
        'project_ids' => [$projectId],
        'description' => 'edge regression',
        'status' => 'draft',
    ]);
}

$summary = [
    'single_level_ten' => [],
    'initiator_is_only_approver' => [],
    'first_effective_multi_users' => [],
];

DB::beginTransaction();

try {
    $approvalService = app(ApprovalService::class);
    $admin = User::findOrFail(2);
    $baseDate = Carbon::create(2030, 3, 18, 10, 0, 0);
    Carbon::setTestNow($baseDate);

    // 场景1：仅启用10级，真实待办/已办接口与审批流转一致
    $accountSet1 = createAccountSet($admin, 'single10');
    $approver10 = createApprover($accountSet1->id, 10, 'edge10a');
    setConfig($accountSet1->id, '保险汇总', [10]);

    $project1 = createProject($accountSet1->id, 'Edge单10项目', $baseDate);
    $process1 = createProcess($accountSet1->id, $admin->id, $project1->id, 'Edge单10汇总', $baseDate->format('Y-m'));
    $instance1 = $approvalService->createApprovalInstance(
        $accountSet1->id,
        '保险汇总',
        $process1->id,
        $admin->id,
        [],
        false,
        'online',
        ['stamp_selection_mode' => 'none']
    );

    $records1 = $instance1->records()->orderBy('step_order')->get();
    ensureTrue($records1->count() === 1, '单10级流程审批节点数错误', ['count' => $records1->count()]);
    ensureTrue((int) $records1->first()->approver_id === (int) $approver10->id, '单10级流程审批人错误');
    ensureTrue($records1->first()->status === 'pending', '单10级流程首节点状态错误', ['status' => $records1->first()->status]);

    [$tasksStatus1, $tasksJson1] = callJson($kernel, 'GET', '/api/approvals/my-tasks?current_account_set_id=' . $accountSet1->id, $approver10);
    ensureTrue($tasksStatus1 === 200 && ($tasksJson1['success'] ?? false), '单10级我的待办接口失败');
    ensureTrue((int) ($tasksJson1['total'] ?? 0) === 1, '单10级我的待办数量错误', ['total' => $tasksJson1['total'] ?? null]);

    [$approveStatus1, $approveJson1] = callJson(
        $kernel,
        'POST',
        '/api/approvals/records/' . $records1->first()->id . '/approve',
        $approver10,
        ['comment' => 'edge approve']
    );
    ensureTrue($approveStatus1 === 200 && ($approveJson1['success'] ?? false), '单10级审批通过接口失败');

    $process1->refresh();
    $instance1->refresh();
    ensureTrue($process1->status === 'approved', '单10级流程业务状态未完成', ['status' => $process1->status]);
    ensureTrue($instance1->status === 'approved', '单10级流程实例状态未完成', ['status' => $instance1->status]);

    [$approvedStatus1, $approvedJson1] = callJson($kernel, 'GET', '/api/approvals/my-approved?current_account_set_id=' . $accountSet1->id, $approver10);
    ensureTrue($approvedStatus1 === 200 && ($approvedJson1['success'] ?? false), '单10级我的已办接口失败');
    ensureTrue((int) ($approvedJson1['total'] ?? 0) === 1, '单10级我的已办数量错误', ['total' => $approvedJson1['total'] ?? null]);

    $summary['single_level_ten'] = [
        'instance_id' => $instance1->id,
        'record_id' => $records1->first()->id,
        'approver_id' => $approver10->id,
    ];

    // 场景2：发起人就是唯一审批人，应该自动完成而不是卡死
    $accountSet2 = createAccountSet($admin, 'initiator-only');
    $soleApprover = createApprover($accountSet2->id, 10, 'edge10b');
    setConfig($accountSet2->id, '保险汇总', [10]);

    $project2 = createProject($accountSet2->id, 'Edge发起人即审批人项目', $baseDate);
    $process2 = createProcess($accountSet2->id, $soleApprover->id, $project2->id, 'Edge发起人即审批人汇总', $baseDate->format('Y-m'));
    $instance2 = $approvalService->createApprovalInstance(
        $accountSet2->id,
        '保险汇总',
        $process2->id,
        $soleApprover->id,
        [],
        false,
        'online',
        ['stamp_selection_mode' => 'none']
    );

    $process2->refresh();
    $instance2->refresh();
    ensureTrue($instance2->status === 'approved', '唯一审批人即发起人时审批实例未自动完成', [
        'instance_status' => $instance2->status,
        'current_step' => $instance2->current_step,
    ]);
    ensureTrue($process2->status === 'approved', '唯一审批人即发起人时业务状态未自动完成', [
        'process_status' => $process2->status,
    ]);

    $records2 = $instance2->records()->orderBy('step_order')->get();
    ensureTrue($records2->count() === 1, '唯一审批人即发起人节点数错误', ['count' => $records2->count()]);
    ensureTrue($records2->first()->status === 'approved', '唯一审批人即发起人节点未自动通过', ['status' => $records2->first()->status]);

    $summary['initiator_is_only_approver'] = [
        'instance_id' => $instance2->id,
        'record_status' => $records2->first()->status,
        'instance_status' => $instance2->status,
        'process_status' => $process2->status,
    ];

    // 场景3：首个有效审批人有多人时，提醒类逻辑是否都发给所有首节点用户
    $accountSet3 = createAccountSet($admin, 'multi-first');
    $firstA = createApprover($accountSet3->id, 10, 'edge10c');
    $firstB = createApprover($accountSet3->id, 10, 'edge10d');
    setConfig($accountSet3->id, '工资付款申请', [10]);
    setConfig($accountSet3->id, '保险汇总', [10]);

    $project3 = createProject($accountSet3->id, 'Edge多人首节点项目', $baseDate);

    $firstApprovers = ApprovalFlowConfig::getFirstEffectiveApprovers($accountSet3->id, '工资付款申请');
    ensureTrue($firstApprovers->count() === 2, '多人首节点查询数量错误', ['count' => $firstApprovers->count()]);

    Artisan::call(CheckSalaryPaymentReminders::class, ['--date' => $baseDate->toDateString()]);
    $salaryNotifications = Notification::where('type', 'salary_payment_reminder')
        ->whereIn('user_id', [$firstA->id, $firstB->id])
        ->where('created_at', '>=', $baseDate->copy()->startOfDay())
        ->get();
    ensureTrue($salaryNotifications->count() === 2, '工资发放提醒未发给全部首节点用户', ['count' => $salaryNotifications->count()]);

    PaymentDueDateConfig::create([
        'account_set_id' => $accountSet3->id,
        'payment_type' => 'social_security',
        'month' => (int) $baseDate->copy()->addDay()->month,
        'due_day' => (int) $baseDate->copy()->addDay()->day,
    ]);

    $process3 = ProcessApproval::create([
        'account_set_id' => $accountSet3->id,
        'initiator_id' => $admin->id,
        'title' => 'Edge多人首节点社保汇总',
        'category' => 'social_insurance',
        'month' => $baseDate->copy()->addDay()->format('Y-m'),
        'project_ids' => [$project3->id],
        'description' => 'edge multi first reminder',
        'status' => 'pending',
    ]);

    $instance3 = ApprovalInstance::create([
        'account_set_id' => $accountSet3->id,
        'business_type' => '保险汇总',
        'business_id' => $process3->id,
        'current_step' => 1,
        'total_steps' => 1,
        'status' => 'pending',
        'created_by' => $admin->id,
    ]);
    $process3->update(['approval_instance_id' => $instance3->id]);

    Artisan::call(CheckInsuranceSummaryReminders::class, ['--date' => $baseDate->toDateString()]);
    $insuranceNotifications = Notification::where('type', 'insurance_summary_reminder')
        ->whereIn('user_id', [$firstA->id, $firstB->id])
        ->where('created_at', '>=', $baseDate->copy()->startOfDay())
        ->get();
    ensureTrue($insuranceNotifications->count() === 2, '保险汇总提醒未发给全部首节点用户', ['count' => $insuranceNotifications->count()]);

    $summary['first_effective_multi_users'] = [
        'salary_notification_user_ids' => $salaryNotifications->pluck('user_id')->sort()->values()->all(),
        'insurance_notification_user_ids' => $insuranceNotifications->pluck('user_id')->sort()->values()->all(),
    ];

    echo json_encode([
        'success' => true,
        'summary' => $summary,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'summary' => $summary,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} finally {
    Carbon::setTestNow();
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}
