<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Console\Commands\CheckInsuranceDeadlines;
use App\Http\Middleware\DailyAssessmentCheck;
use App\Models\AccountSet;
use App\Models\ApprovalFlowConfig;
use App\Models\ApprovalInstance;
use App\Models\AssessmentRecord;
use App\Models\AttendanceSheet;
use App\Models\BasisAttachment;
use App\Models\BasisRecord;
use App\Models\ContractReminder;
use App\Models\DocumentDelivery;
use App\Models\DocumentDeliveryReminder;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\PaymentDueDateConfig;
use App\Models\PendingTask;
use App\Models\ProcessApproval;
use App\Models\Project;
use App\Models\ProjectDeliveryConfig;
use App\Models\ProjectDocumentConfig;
use App\Models\SalaryApproval;
use App\Models\TaxDeclarationConfig;
use App\Models\TaxDeclarationTask;
use App\Models\User;
use App\Services\DocumentDeliveryService;
use App\Services\PendingTaskService;
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

function runArtisan(string $command, array $parameters = []): string
{
    Artisan::call($command, $parameters);
    return Artisan::output();
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

function createApprover(int $accountSetId, int $level): User
{
    $user = User::create([
        'name' => 'codex_cmd_l' . $level . '_' . substr(uniqid(), -6),
        'nickname' => 'cmd_l' . $level,
        'email' => 'cmd_l' . $level . '_' . uniqid() . '@example.com',
        'phone' => '188' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
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

$summary = [
    'commands' => [],
    'services' => [],
    'http_checks' => [],
    'skips' => [],
];

DB::beginTransaction();

try {
    $admin = User::findOrFail(2);
    $testNow = Carbon::create(2030, 2, 13, 9, 0, 0);
    Carbon::setTestNow($testNow);

    $accountSet = AccountSet::create([
        'name' => 'codex-cmd-' . uniqid(),
        'code' => 'CC' . substr(uniqid(), -6),
        'company_name' => 'Codex 命令测试公司',
        'status' => 'active',
        'is_default' => false,
        'created_by' => $admin->id,
        'enabled_date' => $testNow->toDateString(),
    ]);

    DB::table('account_set_users')->insert([
        'account_set_id' => $accountSet->id,
        'user_id' => $admin->id,
        'role' => 'admin',
        'approval_level' => 1,
        'approval_level_name' => '第1级审批',
        'is_default' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $approver8 = createApprover($accountSet->id, 8);
    $approver10 = createApprover($accountSet->id, 10);

    foreach ([
        'document_upload',
        'employee_contract',
        'probation_period',
        '工资付款申请',
        '保险汇总',
        'tax_declaration',
        '考勤申请',
        '工资表审批',
        'document_delivery',
        '保险汇总付款申请',
        'insurance_enrollment',
    ] as $businessType) {
        setConfig($accountSet->id, $businessType, [8, 10]);
    }

    $project = Project::create([
        'account_set_id' => $accountSet->id,
        'name' => 'Codex 测试项目 ' . substr(uniqid(), -6),
        'code' => 'PJ' . substr(uniqid(), -6),
        'status' => 'active',
        'start_date' => $testNow->copy()->subMonth()->toDateString(),
        'end_date' => $testNow->copy()->addYear()->toDateString(),
        'salary_payment_date' => $testNow->copy()->addDay()->day,
        'require_attendance' => true,
        'requires_attendance' => true,
        'requires_salary_basis' => true,
        'requires_attendance_basis' => true,
    ]);

    $month = $testNow->format('Y-m');
    $tomorrow = $testNow->copy()->addDay();

    ProjectDocumentConfig::create([
        'project_id' => $project->id,
        'document_name' => 'Codex必传资料-' . substr(uniqid(), -6),
        'document_type' => 'file',
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $newEmployee = Employee::create([
        'account_set_id' => $accountSet->id,
        'name' => '资料员工-' . substr(uniqid(), -4),
        'employee_number' => 'NE' . substr(uniqid(), -5),
        'id_number' => '11010119900101' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
        'phone' => '177' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'gender' => 'male',
        'birth_date' => '1990-01-01',
        'hire_date' => $testNow->copy()->subDays(3)->toDateString(),
        'contract_start_date' => $testNow->copy()->subDays(3)->toDateString(),
        'contract_status' => 'active',
    ]);

    runArtisan('assessment:check-new-employee-documents', [
        '--account-set-id' => $accountSet->id,
        '--month' => $month,
    ]);

    $documentAssessment = AssessmentRecord::where('account_set_id', $accountSet->id)
        ->where('business_type', 'document_upload')
        ->where('business_id', $newEmployee->id)
        ->first();

    ensureTrue((bool) $documentAssessment, '新员工资料考核未生成');
    ensureTrue((int) $documentAssessment->handler_id === (int) $approver8->id, '新员工资料考核处理人错误');
    $summary['commands']['check_new_employee_documents'] = [
        'assessment_id' => $documentAssessment->id,
        'handler_id' => $documentAssessment->handler_id,
    ];

    $contractEmployee = Employee::create([
        'account_set_id' => $accountSet->id,
        'name' => '合同员工-' . substr(uniqid(), -4),
        'employee_number' => 'CT' . substr(uniqid(), -5),
        'id_number' => '11010119900202' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
        'phone' => '176' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'gender' => 'male',
        'birth_date' => '1990-02-02',
        'hire_date' => $testNow->copy()->subMonth()->toDateString(),
        'contract_start_date' => $testNow->copy()->subDays(10)->toDateString(),
        'contract_end_date' => $testNow->copy()->addDays(20)->toDateString(),
        'contract_status' => 'active',
    ]);

    runArtisan('contract:check-monthly', [
        '--account-set-id' => $accountSet->id,
        '--date' => $testNow->toDateString(),
    ]);

    $contractReminder = ContractReminder::where('account_set_id', $accountSet->id)
        ->where('employee_id', $contractEmployee->id)
        ->where('reminder_type', 'labor_contract')
        ->first();

    ensureTrue((bool) $contractReminder, '月末合同提醒未生成');
    ensureTrue((int) $contractReminder->handler_id === (int) $approver8->id, '月末合同提醒处理人错误');
    $summary['commands']['check_monthly_contracts'] = [
        'reminder_id' => $contractReminder->id,
        'handler_id' => $contractReminder->handler_id,
    ];

    $probationEmployee = Employee::create([
        'account_set_id' => $accountSet->id,
        'name' => '试用员工-' . substr(uniqid(), -4),
        'employee_number' => 'PR' . substr(uniqid(), -5),
        'id_number' => '11010119900303' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
        'phone' => '175' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
        'gender' => 'male',
        'birth_date' => '1990-03-03',
        'hire_date' => $testNow->copy()->subMonth()->toDateString(),
        'contract_start_date' => $testNow->copy()->subMonth()->toDateString(),
        'probation_end_date' => $testNow->copy()->addDays(7)->toDateString(),
        'contract_status' => 'active',
    ]);

    runArtisan('probation:check', [
        '--date' => $testNow->toDateString(),
    ]);

    $probationReminder = ContractReminder::where('account_set_id', $accountSet->id)
        ->where('employee_id', $probationEmployee->id)
        ->where('reminder_type', 'probation_period')
        ->whereDate('reminder_date', $testNow->toDateString())
        ->first();

    ensureTrue((bool) $probationReminder, '试用期提醒未生成');
    ensureTrue((int) $probationReminder->handler_id === (int) $approver8->id, '试用期提醒处理人错误');
    $summary['commands']['check_probation_period'] = [
        'reminder_id' => $probationReminder->id,
        'handler_id' => $probationReminder->handler_id,
    ];

    runArtisan('salary:check-payment-reminders', [
        '--date' => $testNow->toDateString(),
    ]);

    $salaryNotification = Notification::where('user_id', $approver8->id)
        ->where('type', 'salary_payment_reminder')
        ->where('title', '工资发放提醒')
        ->where('created_at', '>=', $testNow->copy()->startOfDay())
        ->latest('id')
        ->first();

    ensureTrue((bool) $salaryNotification, '工资发放提醒未生成');
    $salaryData = is_array($salaryNotification->data) ? $salaryNotification->data : json_decode((string) $salaryNotification->data, true);
    ensureTrue((int) ($salaryData['account_set_id'] ?? 0) === (int) $accountSet->id, '工资发放提醒账套错误', $salaryData ?: []);
    ensureTrue(in_array($project->id, $salaryData['project_ids'] ?? [], true), '工资发放提醒项目未命中', $salaryData ?: []);
    $summary['commands']['check_salary_payment_reminders'] = [
        'notification_id' => $salaryNotification->id,
        'user_id' => $salaryNotification->user_id,
    ];

    PaymentDueDateConfig::create([
        'account_set_id' => $accountSet->id,
        'payment_type' => 'social_security',
        'month' => (int) $tomorrow->month,
        'due_day' => (int) $tomorrow->day,
    ]);

    $instance = ApprovalInstance::create([
        'account_set_id' => $accountSet->id,
        'business_type' => '保险汇总',
        'business_id' => 0,
        'current_step' => 1,
        'total_steps' => 2,
        'status' => 'pending',
        'created_by' => $admin->id,
    ]);

    $processApproval = ProcessApproval::create([
        'account_set_id' => $accountSet->id,
        'approval_instance_id' => $instance->id,
        'initiator_id' => $admin->id,
        'title' => 'Codex社保汇总-' . substr(uniqid(), -6),
        'category' => 'social_insurance',
        'month' => $tomorrow->format('Y-m'),
        'project_ids' => [$project->id],
        'description' => 'command regression',
        'status' => 'pending',
    ]);

    $instance->update(['business_id' => $processApproval->id]);

    runArtisan('insurance:check-summary-reminders', [
        '--date' => $testNow->toDateString(),
    ]);

    $insuranceNotification = Notification::where('user_id', $approver8->id)
        ->where('type', 'insurance_summary_reminder')
        ->where('created_at', '>=', $testNow->copy()->startOfDay())
        ->latest('id')
        ->first();

    ensureTrue((bool) $insuranceNotification, '保险汇总审核提醒未生成');
    $insuranceData = is_array($insuranceNotification->data) ? $insuranceNotification->data : json_decode((string) $insuranceNotification->data, true);
    ensureTrue((int) ($insuranceData['account_set_id'] ?? 0) === (int) $accountSet->id, '保险汇总提醒账套错误', $insuranceData ?: []);
    ensureTrue(in_array($processApproval->id, $insuranceData['summary_ids'] ?? [], true), '保险汇总提醒未带上测试汇总单', $insuranceData ?: []);
    $summary['commands']['check_insurance_summary_reminders'] = [
        'notification_id' => $insuranceNotification->id,
        'user_id' => $insuranceNotification->user_id,
    ];

    $taxConfig = TaxDeclarationConfig::create([
        'account_set_id' => $accountSet->id,
        'company_name' => 'Codex税费公司-' . substr(uniqid(), -4),
        'tax_category_ids' => [1],
        'period_type' => 'monthly',
        'declaration_date' => $testNow->format('m-d'),
        'created_by' => $admin->id,
    ]);

    runArtisan('tax:check-reminders');

    $taxTask = TaxDeclarationTask::where('config_id', $taxConfig->id)->first();
    ensureTrue((bool) $taxTask, '税费申报任务未生成');
    ensureTrue((int) $taxTask->handler_id === (int) $approver8->id, '税费申报任务处理人错误');

    $taxPendingTask = PendingTask::where('task_type', 'tax_declaration')
        ->where('related_id', $taxTask->id)
        ->where('related_type', 'TaxDeclarationTask')
        ->first();
    ensureTrue((bool) $taxPendingTask, '税费申报待办未生成');
    ensureTrue((int) $taxPendingTask->handler_id === (int) $approver8->id, '税费申报待办处理人错误');
    $summary['commands']['check_tax_declaration_reminders'] = [
        'task_id' => $taxTask->id,
        'pending_task_id' => $taxPendingTask->id,
    ];

    runArtisan('payment:check-completion', [
        '--month' => $month,
    ]);

    $paymentAssessments = AssessmentRecord::where('account_set_id', $accountSet->id)
        ->where('business_type', 'payment_request_missing')
        ->where('business_id', $project->id)
        ->where('business_name', 'like', '%' . $month . '%')
        ->orderBy('id')
        ->get();

    ensureTrue($paymentAssessments->count() === 2, '付款申请缺失考核数量不正确', ['count' => $paymentAssessments->count()]);
    foreach ($paymentAssessments as $paymentAssessment) {
        ensureTrue((int) $paymentAssessment->handler_id === (int) $approver8->id, '付款申请缺失考核处理人错误', ['assessment_id' => $paymentAssessment->id]);
    }
    $summary['commands']['check_payment_request_completion'] = [
        'assessment_ids' => $paymentAssessments->pluck('id')->all(),
    ];

    $deliveryService = new DocumentDeliveryService();
    $deliveryConfig = ProjectDeliveryConfig::create([
        'account_set_id' => $accountSet->id,
        'project_id' => $project->id,
        'delivery_cycle' => 'monthly',
        'delivery_method' => 'electronic',
        'required_documents' => ['资料A', '资料B'],
        'is_active' => true,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $delivery = $deliveryService->createDeliveryRecord($deliveryConfig, $month);
    ensureTrue((bool) $delivery, '资料交付记录未生成');
    ensureTrue((int) $delivery->handler_id === (int) $approver8->id, '资料交付处理人错误');

    $deliveryTask = PendingTask::where('task_type', 'document_delivery')
        ->where('related_id', $delivery->id)
        ->where('related_type', 'DocumentDelivery')
        ->first();
    ensureTrue((bool) $deliveryTask, '资料交付待办未生成');
    ensureTrue((int) $deliveryTask->handler_id === (int) $approver8->id, '资料交付待办处理人错误');

    $deliveryService->sendNewPeriodReminder($delivery, $approver8->id);
    $deliveryService->sendNotSubmittedReminder($delivery, $approver8->id);

    $deliveryReminders = DocumentDeliveryReminder::where('delivery_id', $delivery->id)->pluck('reminder_type')->all();
    ensureTrue(in_array('new_period', $deliveryReminders, true), '资料交付新周期提醒未生成');
    ensureTrue(in_array('not_submitted', $deliveryReminders, true), '资料交付未提交提醒未生成');

    $deliveryAssessment = AssessmentRecord::where('business_type', 'document_delivery')
        ->where('business_id', $delivery->id)
        ->first();
    ensureTrue((bool) $deliveryAssessment, '资料交付考核未生成');
    ensureTrue((int) $deliveryAssessment->handler_id === (int) $approver8->id, '资料交付考核处理人错误');

    $delivery->update(['status' => 'submitted']);
    PendingTaskService::checkAndCompleteDocumentDeliveryTask($delivery->fresh());
    ensureTrue($deliveryTask->fresh()->status === 'completed', '资料交付待办未完成');
    $summary['services']['document_delivery'] = [
        'delivery_id' => $delivery->id,
        'task_id' => $deliveryTask->id,
        'assessment_id' => $deliveryAssessment->id,
    ];

    $salaryBasisTasks = PendingTaskService::createSalaryBasisTask($accountSet->id, $project->id, $month);
    ensureTrue(is_array($salaryBasisTasks) && count($salaryBasisTasks) === 2, '工资依据待办数量不正确');
    $salaryBasisHandlerIds = collect($salaryBasisTasks)->map(fn($task) => (int) $task->handler_id)->sort()->values()->all();
    ensureTrue($salaryBasisHandlerIds === [(int) $approver8->id, (int) $approver10->id], '工资依据待办处理人错误', ['handlers' => $salaryBasisHandlerIds]);

    $salaryBasisRecord = BasisRecord::create([
        'account_set_id' => $accountSet->id,
        'project_id' => $project->id,
        'type' => 'salary',
        'month' => $month,
        'description' => 'codex salary basis',
        'created_by' => $admin->id,
    ]);
    BasisAttachment::create([
        'basis_record_id' => $salaryBasisRecord->id,
        'file_name' => 'salary-basis.pdf',
        'file_path' => 'tests/salary-basis.pdf',
        'file_type' => 'application/pdf',
        'file_size' => 100,
    ]);
    PendingTaskService::checkAndCompleteSalaryBasisTask($salaryBasisRecord->fresh());
    ensureTrue(
        PendingTask::where('task_type', 'salary_basis')->where('related_id', $project->id)->where('status', 'completed')->count() === 2,
        '工资依据待办未按预期完成'
    );
    $summary['services']['salary_basis'] = [
        'task_ids' => collect($salaryBasisTasks)->pluck('id')->all(),
    ];

    $attendanceBasisTasks = PendingTaskService::createAttendanceBasisTask($accountSet->id, $project->id, $month);
    ensureTrue(is_array($attendanceBasisTasks) && count($attendanceBasisTasks) === 2, '考勤依据待办数量不正确');
    $attendanceBasisHandlerIds = collect($attendanceBasisTasks)->map(fn($task) => (int) $task->handler_id)->sort()->values()->all();
    ensureTrue($attendanceBasisHandlerIds === [(int) $approver8->id, (int) $approver10->id], '考勤依据待办处理人错误', ['handlers' => $attendanceBasisHandlerIds]);

    $attendanceBasisRecord = BasisRecord::create([
        'account_set_id' => $accountSet->id,
        'project_id' => $project->id,
        'type' => 'attendance',
        'month' => $month,
        'description' => 'codex attendance basis',
        'created_by' => $admin->id,
    ]);
    BasisAttachment::create([
        'basis_record_id' => $attendanceBasisRecord->id,
        'file_name' => 'attendance-basis.pdf',
        'file_path' => 'tests/attendance-basis.pdf',
        'file_type' => 'application/pdf',
        'file_size' => 100,
    ]);
    PendingTaskService::checkAndCompleteAttendanceBasisTask($attendanceBasisRecord->fresh());
    ensureTrue(
        PendingTask::where('task_type', 'attendance_basis')->where('related_id', $project->id)->where('status', 'completed')->count() === 2,
        '考勤依据待办未按预期完成'
    );
    $summary['services']['attendance_basis'] = [
        'task_ids' => collect($attendanceBasisTasks)->pluck('id')->all(),
    ];

    $attendanceSheetTask = PendingTaskService::createAttendanceSheetTask($accountSet->id, $project->id, $month);
    ensureTrue((bool) $attendanceSheetTask, '考勤表待办未生成');
    ensureTrue((int) $attendanceSheetTask->handler_id === (int) $approver8->id, '考勤表待办处理人错误');

    $attendanceSheet = AttendanceSheet::create([
        'account_set_id' => $accountSet->id,
        'project_id' => $project->id,
        'month' => $month,
        'work_days' => 20,
        'total_employees' => 1,
        'status' => 'approved',
        'created_by' => $admin->id,
        'approved_by' => $approver8->id,
        'approved_at' => now(),
    ]);
    PendingTaskService::checkAndCompleteAttendanceSheetTask($attendanceSheet->fresh());
    ensureTrue($attendanceSheetTask->fresh()->status === 'completed', '考勤表待办未完成');
    $summary['services']['attendance_sheet'] = [
        'task_id' => $attendanceSheetTask->id,
        'sheet_id' => $attendanceSheet->id,
    ];

    $salarySheetTask = PendingTaskService::createSalarySheetTask($accountSet->id, $project->id, $month);
    ensureTrue((bool) $salarySheetTask, '工资表待办未生成');
    ensureTrue((int) $salarySheetTask->handler_id === (int) $approver8->id, '工资表待办处理人错误');

    $salaryApproval = SalaryApproval::create([
        'account_set_id' => $accountSet->id,
        'project_id' => $project->id,
        'month' => $month,
        'approval_type' => 'online',
        'status' => 'approved',
        'submitted_by' => $admin->id,
        'approved_by' => $approver8->id,
        'approved_at' => now(),
    ]);
    PendingTaskService::checkAndCompleteSalarySheetTask($salaryApproval->fresh());
    ensureTrue($salarySheetTask->fresh()->status === 'completed', '工资表待办未完成');
    $summary['services']['salary_sheet'] = [
        'task_id' => $salarySheetTask->id,
        'salary_approval_id' => $salaryApproval->id,
    ];

    $taxTask->update([
        'status' => 'completed',
        'completed_at' => now(),
        'completed_by' => $approver8->id,
    ]);
    PendingTaskService::checkAndCompleteTaxDeclarationTask($taxTask->fresh());
    ensureTrue($taxPendingTask->fresh()->status === 'completed', '税费申报待办未完成');
    $summary['services']['tax_declaration_complete'] = [
        'task_id' => $taxTask->id,
        'pending_task_id' => $taxPendingTask->id,
    ];

    $operatorId = $deliveryService->getProjectOperatorId($project->id);
    ensureTrue((int) $operatorId === (int) $approver8->id, '资料交付项目经办人获取错误');
    $summary['services']['document_delivery_operator'] = [
        'operator_id' => $operatorId,
    ];

    $insuranceDeadlineOutput = runArtisan('assessment:check-insurance-deadlines');
    ensureTrue(str_contains($insuranceDeadlineOutput, 'employees表中没有insurance_completion_time字段'), '参保入职超期检查未走缺字段跳过路径');
    $summary['skips']['check_insurance_deadlines'] = 'employees.insurance_completion_time 缺失，已验证命令安全跳过';

    $middleware = new DailyAssessmentCheck();
    $cacheKey = 'daily_assessment_checked_' . $testNow->toDateString();
    Cache::forget($cacheKey);
    auth()->guard()->setUser($admin);
    $request = Request::create('/codex-daily-check', 'GET');
    $response = $middleware->handle($request, function () {
        return response('ok', 200);
    });
    ensureTrue($response->getStatusCode() === 200, '每日考核中间件未正常放行');
    ensureTrue(Cache::has($cacheKey), '每日考核中间件未写入当天缓存');
    Cache::forget($cacheKey);
    auth()->guard()->logout();
    $summary['skips']['daily_assessment_check'] = 'employees.insurance_completion_time 缺失，已验证中间件安全跳过并正常缓存';

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
