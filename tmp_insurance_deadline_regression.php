<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Console\Commands\CheckInsuranceDeadlines;
use App\Http\Controllers\CronController;
use App\Http\Middleware\DailyAssessmentCheck;
use App\Models\AccountSet;
use App\Models\ApprovalFlowConfig;
use App\Models\AssessmentRecord;
use App\Models\Employee;
use App\Models\InsuranceChange;
use App\Models\InsuranceChangeAttachment;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function ensureTrue($condition, $message, array $context = []): void
{
    if (!$condition) {
        throw new RuntimeException(
            $message . (empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE))
        );
    }
}

function createAccountSet(User $admin, string $suffix): AccountSet
{
    $accountSet = AccountSet::create([
        'name' => 'codex-insurance-' . $suffix,
        'code' => 'INS' . substr(uniqid(), -5),
        'company_name' => 'Codex Insurance ' . $suffix,
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

function createApprover(int $accountSetId, int $level, string $namePrefix): User
{
    $user = User::create([
        'name' => $namePrefix . '_' . substr(uniqid(), -5),
        'nickname' => $namePrefix,
        'email' => $namePrefix . '_' . uniqid() . '@example.com',
        'phone' => '155' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
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

function createProject(int $accountSetId, Carbon $baseDate, string $name): Project
{
    return Project::create([
        'account_set_id' => $accountSetId,
        'name' => $name,
        'code' => 'IP' . substr(uniqid(), -5),
        'status' => 'active',
        'start_date' => $baseDate->copy()->subMonth()->toDateString(),
        'end_date' => $baseDate->copy()->addYear()->toDateString(),
        'salary_payment_date' => 15,
        'require_attendance' => false,
        'requires_attendance' => false,
        'requires_salary_basis' => false,
        'requires_attendance_basis' => false,
    ]);
}

function createEmployee(int $accountSetId, string $name, string $employeeNumber, string $idNumber, string $phone, string $date): Employee
{
    return Employee::create([
        'account_set_id' => $accountSetId,
        'name' => $name,
        'employee_number' => $employeeNumber,
        'id_number' => $idNumber,
        'phone' => $phone,
        'gender' => 'male',
        'birth_date' => '1990-01-01',
        'hire_date' => $date,
        'contract_start_date' => $date,
        'contract_status' => 'active',
    ]);
}

function createInsuranceChange(Employee $employee, Project $project, int $createdBy, string $status): InsuranceChange
{
    return InsuranceChange::create([
        'employee_id' => $employee->id,
        'employee_name' => $employee->name,
        'employee_id_number' => $employee->id_number,
        'employee_phone' => $employee->phone,
        'project_id' => $project->id,
        'account_set_id' => $employee->account_set_id,
        'change_type' => 'increase',
        'status' => $status,
        'created_by' => $createdBy,
    ]);
}

$summary = [
    'command' => [],
    'middleware' => [],
    'cron' => [],
    'findings' => [],
];

DB::beginTransaction();

try {
    $admin = User::findOrFail(2);
    $today = Carbon::create(2030, 4, 1, 9, 0, 0);
    Carbon::setTestNow($today);

    // 命令测试
    $accountSet1 = createAccountSet($admin, 'command');
    $approver1 = createApprover($accountSet1->id, 8, 'insurance_cmd');
    ApprovalFlowConfig::updateOrCreate(
        ['account_set_id' => $accountSet1->id, 'business_type' => 'insurance_enrollment'],
        ['enabled_levels' => [8]]
    );
    $project1 = createProject($accountSet1->id, $today, '保险命令测试项目');

    $employeeNotDue = createEmployee(
        $accountSet1->id,
        '未到期员工',
        'INS-CMD-000',
        '110101199001010010',
        '15500000010',
        $today->copy()->subDays(29)->toDateString()
    );

    $employeeNoChange = createEmployee(
        $accountSet1->id,
        '无增减员工',
        'INS-CMD-001',
        '110101199001010011',
        '15500000011',
        $today->copy()->subDays(31)->toDateString()
    );

    $employeePending = createEmployee(
        $accountSet1->id,
        '待处理增减员工',
        'INS-CMD-002',
        '110101199001010022',
        '15500000022',
        $today->copy()->subDays(31)->toDateString()
    );
    createInsuranceChange($employeePending, $project1, $admin->id, 'pending');

    $employeeCompletedNoAttachment = createEmployee(
        $accountSet1->id,
        '已完成无附件员工',
        'INS-CMD-003',
        '110101199001010033',
        '15500000033',
        $today->copy()->subDays(31)->toDateString()
    );
    createInsuranceChange($employeeCompletedNoAttachment, $project1, $admin->id, 'completed');

    $employeeCompletedWithAttachment = createEmployee(
        $accountSet1->id,
        '已完成有附件员工',
        'INS-CMD-004',
        '110101199001010044',
        '15500000044',
        $today->copy()->subDays(31)->toDateString()
    );
    $completedWithAttachment = createInsuranceChange($employeeCompletedWithAttachment, $project1, $admin->id, 'completed');
    InsuranceChangeAttachment::create([
        'insurance_change_id' => $completedWithAttachment->id,
        'file_path' => 'tests/insurance-proof.pdf',
        'original_name' => 'insurance-proof.pdf',
        'file_type' => 'application/pdf',
        'file_size' => 128,
        'uploaded_by' => $admin->id,
    ]);

    Artisan::call(CheckInsuranceDeadlines::class);
    Artisan::call(CheckInsuranceDeadlines::class);

    $commandRecords = AssessmentRecord::where('account_set_id', $accountSet1->id)
        ->where('business_type', 'insurance_enrollment')
        ->orderBy('id')
        ->get();

    ensureTrue($commandRecords->count() === 3, '参保超期命令生成考核数量不正确', ['count' => $commandRecords->count()]);
    ensureTrue(
        AssessmentRecord::where('account_set_id', $accountSet1->id)
            ->where('business_id', $employeeNotDue->id)
            ->count() === 0,
        '参保超期命令不应为入职未满30天员工创建考核'
    );
    ensureTrue(
        AssessmentRecord::where('account_set_id', $accountSet1->id)
            ->where('business_id', $employeeCompletedWithAttachment->id)
            ->count() === 0,
        '参保超期命令不应为已完成且有附件员工创建考核'
    );

    foreach ($commandRecords as $record) {
        ensureTrue((int) $record->handler_id === (int) $approver1->id, '参保超期命令处理人错误', ['record_id' => $record->id]);
        $employee = Employee::find($record->business_id);
        $expectedDeadline = Carbon::parse($employee->hire_date)->addDays(30)->toDateString();
        ensureTrue(
            $record->deadline_date->toDateString() === $expectedDeadline,
            '参保超期命令截止日期应按入职日期+30天计算',
            ['record_id' => $record->id, 'deadline_date' => $record->deadline_date->toDateString(), 'expected' => $expectedDeadline]
        );
    }

    $summary['command'] = [
        'assessment_ids' => $commandRecords->pluck('id')->all(),
        'handler_id' => $approver1->id,
    ];

    // 中间件测试
    $accountSet2 = createAccountSet($admin, 'middleware');
    $approver2 = createApprover($accountSet2->id, 8, 'insurance_mid');
    ApprovalFlowConfig::updateOrCreate(
        ['account_set_id' => $accountSet2->id, 'business_type' => 'insurance_enrollment'],
        ['enabled_levels' => [8]]
    );
    $project2 = createProject($accountSet2->id, $today, '保险中间件测试项目');

    $employeeMiddlewareNotDue = createEmployee(
        $accountSet2->id,
        '中间件未到期员工',
        'INS-MID-000',
        '110101199001010054',
        '15500000054',
        $today->copy()->subDays(29)->toDateString()
    );

    $employeeMiddlewareNoChange = createEmployee(
        $accountSet2->id,
        '中间件无增减员工',
        'INS-MID-001',
        '110101199001010055',
        '15500000055',
        $today->copy()->subDays(31)->toDateString()
    );

    $employeeMiddlewarePending = createEmployee(
        $accountSet2->id,
        '中间件待处理员工',
        'INS-MID-002',
        '110101199001010066',
        '15500000066',
        $today->copy()->subDays(31)->toDateString()
    );
    createInsuranceChange($employeeMiddlewarePending, $project2, $admin->id, 'pending');

    $employeeMiddlewareCompletedNoAttachment = createEmployee(
        $accountSet2->id,
        '中间件已完成无附件员工',
        'INS-MID-003',
        '110101199001010077',
        '15500000077',
        $today->copy()->subDays(31)->toDateString()
    );
    createInsuranceChange($employeeMiddlewareCompletedNoAttachment, $project2, $admin->id, 'completed');

    $cacheKey = 'daily_assessment_checked_' . $today->toDateString();
    Cache::forget($cacheKey);
    auth()->guard()->setUser($admin);

    $middleware = new DailyAssessmentCheck();
    $request = Request::create('/codex-insurance-daily-check', 'GET');
    $response = $middleware->handle($request, function () {
        return response('ok', 200);
    });

    ensureTrue($response->getStatusCode() === 200, '每日考核中间件未正常执行');
    ensureTrue(Cache::has($cacheKey), '每日考核中间件未写入缓存');

    $firstPassRecords = AssessmentRecord::where('account_set_id', $accountSet2->id)
        ->where('business_type', 'insurance_enrollment')
        ->orderBy('id')
        ->get();

    ensureTrue($firstPassRecords->count() === 3, '每日考核中间件生成考核数量不正确', ['count' => $firstPassRecords->count()]);
    ensureTrue(
        AssessmentRecord::where('account_set_id', $accountSet2->id)
            ->where('business_id', $employeeMiddlewareNotDue->id)
            ->count() === 0,
        '每日考核中间件不应为入职未满30天员工创建考核'
    );
    foreach ($firstPassRecords as $record) {
        ensureTrue((int) $record->handler_id === (int) $approver2->id, '每日考核中间件处理人错误', ['record_id' => $record->id]);
        $employee = Employee::find($record->business_id);
        $expectedDeadline = Carbon::parse($employee->hire_date)->addDays(30)->toDateString();
        ensureTrue(
            $record->deadline_date->toDateString() === $expectedDeadline,
            '每日考核中间件截止日期应按入职日期+30天计算',
            ['record_id' => $record->id, 'deadline_date' => $record->deadline_date->toDateString(), 'expected' => $expectedDeadline]
        );
    }

    $middleware->handle($request, function () {
        return response('ok', 200);
    });

    $secondPassCount = AssessmentRecord::where('account_set_id', $accountSet2->id)
        ->where('business_type', 'insurance_enrollment')
        ->count();
    ensureTrue($secondPassCount === 3, '每日考核中间件重复执行后不应重复创建考核', ['count' => $secondPassCount]);

    auth()->guard()->logout();

    $summary['middleware'] = [
        'assessment_ids' => $firstPassRecords->pluck('id')->all(),
        'handler_id' => $approver2->id,
        'cache_key' => $cacheKey,
    ];

    // Cron接口测试
    $accountSet3 = createAccountSet($admin, 'cron');
    $approver3 = createApprover($accountSet3->id, 8, 'insurance_cron');
    ApprovalFlowConfig::updateOrCreate(
        ['account_set_id' => $accountSet3->id, 'business_type' => 'insurance_enrollment'],
        ['enabled_levels' => [8]]
    );
    $project3 = createProject($accountSet3->id, $today, '保险Cron测试项目');

    $employeeCronNotDue = createEmployee(
        $accountSet3->id,
        'Cron未到期员工',
        'INS-CRON-000',
        '110101199001010080',
        '15500000080',
        $today->copy()->subDays(29)->toDateString()
    );

    $employeeCronNoChange = createEmployee(
        $accountSet3->id,
        'Cron无增减员工',
        'INS-CRON-001',
        '110101199001010081',
        '15500000081',
        $today->copy()->subDays(31)->toDateString()
    );

    $employeeCronPending = createEmployee(
        $accountSet3->id,
        'Cron待处理员工',
        'INS-CRON-002',
        '110101199001010082',
        '15500000082',
        $today->copy()->subDays(31)->toDateString()
    );
    createInsuranceChange($employeeCronPending, $project3, $admin->id, 'pending');

    $employeeCronCompletedNoAttachment = createEmployee(
        $accountSet3->id,
        'Cron已完成无附件员工',
        'INS-CRON-003',
        '110101199001010083',
        '15500000083',
        $today->copy()->subDays(31)->toDateString()
    );
    createInsuranceChange($employeeCronCompletedNoAttachment, $project3, $admin->id, 'completed');

    $employeeCronCompletedWithAttachment = createEmployee(
        $accountSet3->id,
        'Cron已完成有附件员工',
        'INS-CRON-004',
        '110101199001010084',
        '15500000084',
        $today->copy()->subDays(31)->toDateString()
    );
    $cronCompletedWithAttachment = createInsuranceChange($employeeCronCompletedWithAttachment, $project3, $admin->id, 'completed');
    InsuranceChangeAttachment::create([
        'insurance_change_id' => $cronCompletedWithAttachment->id,
        'file_path' => 'tests/insurance-proof.pdf',
        'original_name' => 'insurance-proof.pdf',
        'file_type' => 'application/pdf',
        'file_size' => 128,
        'uploaded_by' => $admin->id,
    ]);

    $cronRequest = Request::create('/api/cron/check-insurance-deadlines', 'GET', [
        'token' => env('CRON_TOKEN', 'default-cron-token-please-change'),
    ]);
    $cronResponse = (new CronController())->checkInsuranceDeadlines($cronRequest);
    $cronPayload = json_decode($cronResponse->getContent(), true);

    ensureTrue($cronResponse->getStatusCode() === 200, 'Cron参保超期接口响应状态不正确', ['status' => $cronResponse->getStatusCode(), 'payload' => $cronPayload]);
    ensureTrue(($cronPayload['success'] ?? false) === true, 'Cron参保超期接口未成功返回', ['payload' => $cronPayload]);

    $cronRecords = AssessmentRecord::where('account_set_id', $accountSet3->id)
        ->where('business_type', 'insurance_enrollment')
        ->orderBy('id')
        ->get();

    ensureTrue($cronRecords->count() === 3, 'Cron参保超期接口生成考核数量不正确', ['count' => $cronRecords->count()]);
    ensureTrue(
        AssessmentRecord::where('account_set_id', $accountSet3->id)
            ->where('business_id', $employeeCronNotDue->id)
            ->count() === 0,
        'Cron参保超期接口不应为入职未满30天员工创建考核'
    );
    ensureTrue(
        AssessmentRecord::where('account_set_id', $accountSet3->id)
            ->where('business_id', $employeeCronCompletedWithAttachment->id)
            ->count() === 0,
        'Cron参保超期接口不应为已完成且有附件员工创建考核'
    );

    foreach ($cronRecords as $record) {
        ensureTrue((int) $record->handler_id === (int) $approver3->id, 'Cron参保超期接口处理人错误', ['record_id' => $record->id]);
        $employee = Employee::find($record->business_id);
        $expectedDeadline = Carbon::parse($employee->hire_date)->addDays(30)->toDateString();
        ensureTrue(
            $record->deadline_date->toDateString() === $expectedDeadline,
            'Cron参保超期接口截止日期应按入职日期+30天计算',
            ['record_id' => $record->id, 'deadline_date' => $record->deadline_date->toDateString(), 'expected' => $expectedDeadline]
        );
    }

    $summary['cron'] = [
        'assessment_ids' => $cronRecords->pluck('id')->all(),
        'handler_id' => $approver3->id,
        'response' => $cronPayload['data'] ?? null,
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
