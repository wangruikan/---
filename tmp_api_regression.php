<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Models\ApprovalInstance;
use App\Models\InvoiceApplication;
use App\Models\PaymentRequest;
use App\Models\PersonnelChangeRequest;
use App\Models\ProcessApproval;
use App\Models\SalaryApproval;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function ensureTrue($condition, $message, $context = []) {
    if (!$condition) {
        throw new RuntimeException($message . (!empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''));
    }
}

function callJson($kernel, $method, $uri, $actor, $data = null) {
    $server = [
        'HTTP_ACCEPT' => 'application/json',
    ];
    if ($actor instanceof User) {
        Sanctum::actingAs($actor);
    } else {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $actor;
    }
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

function callUpload($kernel, $method, $uri, $actor, $fields, $sourcePath, $clientName, $mime) {
    $tmp = tempnam(sys_get_temp_dir(), 'codexup_');
    copy($sourcePath, $tmp);
    $file = new UploadedFile($tmp, $clientName, $mime, null, true);
    $server = [
        'HTTP_ACCEPT' => 'application/json',
    ];
    if ($actor instanceof User) {
        Sanctum::actingAs($actor);
    } else {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $actor;
    }
    $request = Request::create($uri, $method, $fields, [], ['file' => $file], $server);
    $response = $kernel->handle($request);
    $content = $response->getContent();
    $decoded = json_decode($content, true);
    $kernel->terminate($request, $response);
    return [$response->getStatusCode(), $decoded, $content];
}

function postApprove($kernel, $actor, $recordId, $comment) {
    [$status, $json, $raw] = callJson($kernel, 'POST', '/api/approvals/records/' . $recordId . '/approve', $actor, [
        'comment' => $comment,
    ]);
    ensureTrue($status === 200 && ($json['success'] ?? false), '审批通过接口失败', ['record_id' => $recordId, 'status' => $status, 'resp' => $json ?? $raw]);
    return $json;
}

function setConfig($kernel, $adminActor, $businessType, array $levels) {
    [$status, $json, $raw] = callJson($kernel, 'POST', '/api/approval-flow-configs', $adminActor, [
        'account_set_id' => 1,
        'business_type' => $businessType,
        'enabled_levels' => $levels,
    ]);
    ensureTrue($status === 200 && ($json['success'] ?? false), '保存审批配置失败', ['business_type' => $businessType, 'status' => $status, 'resp' => $json ?? $raw]);
}

function assertInstanceSteps($instanceId, $expectedOrders, $expectedApproverIds, $expectedStatuses) {
    $instance = ApprovalInstance::with('records')->find($instanceId);
    ensureTrue((bool) $instance, '审批实例不存在', ['instance_id' => $instanceId]);
    $records = $instance->records->sortBy('step_order')->values();
    ensureTrue($records->count() === count($expectedOrders), '审批节点数不符', ['instance_id' => $instanceId, 'actual' => $records->count()]);
    foreach ($records as $index => $record) {
        ensureTrue((int)$record->step_order === (int)$expectedOrders[$index], '步骤顺序不符', ['instance_id' => $instanceId, 'expected' => $expectedOrders[$index], 'actual' => $record->step_order]);
        ensureTrue((int)$record->approver_id === (int)$expectedApproverIds[$index], '审批人不符', ['instance_id' => $instanceId, 'step' => $record->step_order, 'expected' => $expectedApproverIds[$index], 'actual' => $record->approver_id]);
        ensureTrue($record->status === $expectedStatuses[$index], '审批状态不符', ['instance_id' => $instanceId, 'step' => $record->step_order, 'expected' => $expectedStatuses[$index], 'actual' => $record->status]);
    }
    return $records;
}

$createdFiles = [];
$summary = [
    'config' => [],
    'flows' => [],
];

DB::beginTransaction();
try {
    $admin = User::findOrFail(2);

    $tempApprovers = [];
    foreach ([7, 8, 9, 10] as $level) {
        $user = User::create([
            'name' => 'codex_l' . $level,
            'nickname' => 'codex_l' . $level,
            'email' => 'codex_l' . $level . '_' . uniqid() . '@example.com',
            'phone' => '199' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => Hash::make('test123456'),
            'role' => 'employee',
            'account_set_id' => 1,
            'current_account_set_id' => 1,
            'is_active' => true,
        ]);
        DB::table('account_set_users')->insert([
            'account_set_id' => 1,
            'user_id' => $user->id,
            'role' => 'admin',
            'approval_level' => $level,
            'approval_level_name' => '第' . $level . '级审批',
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tempApprovers[$level] = [
            'user' => $user,
            'token' => $user->createToken('codex-l' . $level . '-' . uniqid())->plainTextToken,
        ];
    }

    [$cfgStatus, $cfgJson, $cfgRaw] = callJson($kernel, 'GET', '/api/approval-flow-configs?account_set_id=1', $admin);
    ensureTrue($cfgStatus === 200 && ($cfgJson['success'] ?? false), '获取审批配置失败', ['status' => $cfgStatus, 'resp' => $cfgJson ?? $cfgRaw]);
    $levels = array_map(fn($row) => (int)$row['level'], $cfgJson['data']['approval_levels'] ?? []);
    ensureTrue(in_array(10, $levels, true), '审批配置未显示到第10级', ['levels' => $levels]);
    $summary['config']['approval_levels'] = $levels;

    $businessTypes = ['保险汇总', '保险汇总付款申请', '工资表审批', '工资付款申请', '报销付款申请', 'personnel_change', '发票申请'];
    foreach ($businessTypes as $type) {
        setConfig($kernel, $admin, $type, [8, 10]);
    }
    foreach (['insurance_enrollment', 'document_upload', 'document_delivery', 'probation_period', 'tax_declaration', 'employee_contract'] as $type) {
        setConfig($kernel, $admin, $type, [8, 10]);
    }
    $summary['config']['set_business_types'] = array_merge($businessTypes, ['insurance_enrollment', 'document_upload', 'document_delivery', 'probation_period', 'tax_declaration', 'employee_contract']);

    // 1. 保险汇总审批
    [$pStatus, $pJson, $pRaw] = callJson($kernel, 'POST', '/api/process-approvals', $admin, [
        'current_account_set_id' => 1,
        'title' => 'CODEx保险汇总-' . uniqid(),
        'category' => 'social_insurance',
        'project_ids' => [10],
        'description' => 'codex insurance summary flow',
    ]);
    ensureTrue($pStatus === 200 && ($pJson['success'] ?? false), '创建保险汇总草稿失败', ['status' => $pStatus, 'resp' => $pJson ?? $pRaw]);
    $processId = (int)$pJson['data']['id'];

    [$uStatus, $uJson, $uRaw] = callUpload($kernel, 'POST', '/api/process-approvals/' . $processId . '/upload-attachment', $admin, [], __DIR__ . '/tmp_attach_paymentapp.txt', 'process_probe.txt', 'text/plain');
    ensureTrue($uStatus === 200 && ($uJson['success'] ?? false), '上传保险汇总附件失败', ['status' => $uStatus, 'resp' => $uJson ?? $uRaw]);
    $createdFiles[] = public_path($uJson['data']['file_path']);

    [$subStatus, $subJson, $subRaw] = callJson($kernel, 'POST', '/api/process-approvals/' . $processId . '/submit', $admin, [
        'stamp_selection_mode' => 'none',
    ]);
    ensureTrue($subStatus === 200 && ($subJson['success'] ?? false), '提交保险汇总审批失败', ['status' => $subStatus, 'resp' => $subJson ?? $subRaw]);
    $process = ProcessApproval::with('approvalInstance.records')->findOrFail($processId);
    $processRecords = assertInstanceSteps($process->approval_instance_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['pending','waiting']);
    postApprove($kernel, $tempApprovers[8]['user'], $processRecords[0]->id, 'L8 approve insurance summary');
    assertInstanceSteps($process->approval_instance_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['approved','pending']);
    postApprove($kernel, $tempApprovers[10]['user'], $processRecords[1]->id, 'L10 approve insurance summary');
    $process = ProcessApproval::with('approvalInstance.records')->findOrFail($processId);
    ensureTrue($process->status === 'approved', '保险汇总业务状态未更新为approved', ['status' => $process->status]);
    ensureTrue($process->approvalInstance->status === 'approved', '保险汇总审批实例未完成', ['status' => $process->approvalInstance->status]);
    $summary['flows']['insurance_summary'] = ['process_id' => $processId, 'instance_id' => $process->approval_instance_id, 'status' => $process->status];

    // 2. 保险汇总付款申请
    $reimbursementForm = [
        'applyDate' => now()->toDateString(),
        'unitName' => '测试单位',
        'reimburser' => 'codex',
        'invoiceNumber' => 'INV-' . random_int(10000,99999),
        'invoiceType' => '普通发票',
        'invoiceAmount' => 1000,
        'taxRate' => '6%',
        'taxAmount' => 56.6,
        'deductionAmount' => 0,
        'amountExcludingTax' => 943.4,
        'paymentDate' => now()->toDateString(),
        'expenditureAmount' => 1000,
        'summary' => 'codex insurance pay request',
        'project' => '哈哈',
        'projectName' => '哈哈',
        'category' => '社保',
    ];
    [$ipSubmitStatus, $ipSubmitJson, $ipSubmitRaw] = callJson($kernel, 'POST', '/api/insurance-payment-requests/submit', $admin, [
        'current_account_set_id' => 1,
        'process_approval_id' => $processId,
        'amount' => 1000,
        'remarks' => 'codex insurance payment request',
        'reimbursement_form_data' => $reimbursementForm,
    ]);
    ensureTrue($ipSubmitStatus === 200 && ($ipSubmitJson['success'] ?? false), '创建保险付款申请失败', ['status' => $ipSubmitStatus, 'resp' => $ipSubmitJson ?? $ipSubmitRaw]);
    $insurancePaymentId = (int)$ipSubmitJson['data']['id'];
    [$ipCompleteStatus, $ipCompleteJson, $ipCompleteRaw] = callJson($kernel, 'POST', '/api/insurance-payment-requests/complete-submission', $admin, [
        'payment_request_id' => $insurancePaymentId,
        'stamp_selection_mode' => 'none',
    ]);
    ensureTrue($ipCompleteStatus === 200 && ($ipCompleteJson['success'] ?? false), '提交保险付款审批失败', ['status' => $ipCompleteStatus, 'resp' => $ipCompleteJson ?? $ipCompleteRaw]);
    $insurancePayment = PaymentRequest::with('approvalInstance.records')->findOrFail($insurancePaymentId);
    $insurancePayRecords = assertInstanceSteps($insurancePayment->approval_instance_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['pending','waiting']);
    postApprove($kernel, $tempApprovers[8]['user'], $insurancePayRecords[0]->id, 'L8 approve insurance payment');
    assertInstanceSteps($insurancePayment->approval_instance_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['approved','pending']);
    postApprove($kernel, $tempApprovers[10]['user'], $insurancePayRecords[1]->id, 'L10 approve insurance payment');
    $insurancePayment = PaymentRequest::with('approvalInstance.records')->findOrFail($insurancePaymentId);
    ensureTrue($insurancePayment->status === 'approved', '保险付款申请业务状态未更新为approved', ['status' => $insurancePayment->status]);
    $summary['flows']['insurance_payment_request'] = ['payment_request_id' => $insurancePaymentId, 'instance_id' => $insurancePayment->approval_instance_id, 'status' => $insurancePayment->status];

    // 3. 工资表审批
    [$saSubmitStatus, $saSubmitJson, $saSubmitRaw] = callJson($kernel, 'POST', '/api/salary-approvals/submit', $admin, [
        'current_account_set_id' => 1,
        'project_id' => 10,
        'month' => '2026-06',
        'approval_type' => 'online',
        'remarks' => 'codex salary approval',
    ]);
    ensureTrue($saSubmitStatus === 200 && ($saSubmitJson['success'] ?? false), '创建工资表审批草稿失败', ['status' => $saSubmitStatus, 'resp' => $saSubmitJson ?? $saSubmitRaw]);
    $salaryApprovalId = (int)$saSubmitJson['data']['id'];
    [$saCompleteStatus, $saCompleteJson, $saCompleteRaw] = callJson($kernel, 'POST', '/api/salary-approvals/complete-submission', $admin, [
        'salary_approval_id' => $salaryApprovalId,
        'stamp_selection_mode' => 'none',
    ]);
    ensureTrue($saCompleteStatus === 200 && ($saCompleteJson['success'] ?? false), '提交工资表审批失败', ['status' => $saCompleteStatus, 'resp' => $saCompleteJson ?? $saCompleteRaw]);
    $salaryApproval = SalaryApproval::with('approvalInstance.records')->findOrFail($salaryApprovalId);
    $salaryRecords = assertInstanceSteps($salaryApproval->approval_instance_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['pending','waiting']);
    postApprove($kernel, $tempApprovers[8]['user'], $salaryRecords[0]->id, 'L8 approve salary approval');
    assertInstanceSteps($salaryApproval->approval_instance_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['approved','pending']);
    postApprove($kernel, $tempApprovers[10]['user'], $salaryRecords[1]->id, 'L10 approve salary approval');
    $salaryApproval = SalaryApproval::with('approvalInstance.records')->findOrFail($salaryApprovalId);
    ensureTrue($salaryApproval->status === 'approved', '工资表审批业务状态未更新为approved', ['status' => $salaryApproval->status]);
    $summary['flows']['salary_approval'] = ['salary_approval_id' => $salaryApprovalId, 'instance_id' => $salaryApproval->approval_instance_id, 'status' => $salaryApproval->status];

    // 4. 工资付款申请
    $salaryApprovalCandidateId = DB::table('salary_approvals as sa')
        ->leftJoin('payment_requests as pr', 'pr.salary_approval_id', '=', 'sa.id')
        ->where('sa.account_set_id', 1)
        ->where('sa.status', 'approved')
        ->whereNull('pr.id')
        ->orderByDesc('sa.id')
        ->value('sa.id');
    $salaryApprovalCandidate = $salaryApprovalCandidateId ? SalaryApproval::find($salaryApprovalCandidateId) : null;
    ensureTrue((bool)$salaryApprovalCandidate, '未找到可用于工资付款申请的已审批工资表');
    [$spSubmitStatus, $spSubmitJson, $spSubmitRaw] = callJson($kernel, 'POST', '/api/salary-payment-requests/submit', $admin, [
        'current_account_set_id' => 1,
        'salary_approval_id' => $salaryApprovalCandidate->id,
        'remarks' => 'codex salary payment request',
        'reimbursement_form_data' => $reimbursementForm,
    ]);
    ensureTrue($spSubmitStatus === 200 && ($spSubmitJson['success'] ?? false), '创建工资付款申请失败', ['status' => $spSubmitStatus, 'resp' => $spSubmitJson ?? $spSubmitRaw]);
    $salaryPaymentId = (int)$spSubmitJson['data']['id'];
    [$spCompleteStatus, $spCompleteJson, $spCompleteRaw] = callJson($kernel, 'POST', '/api/salary-payment-requests/complete-submission', $admin, [
        'payment_request_id' => $salaryPaymentId,
        'stamp_selection_mode' => 'none',
    ]);
    ensureTrue($spCompleteStatus === 200 && ($spCompleteJson['success'] ?? false), '提交工资付款审批失败', ['status' => $spCompleteStatus, 'resp' => $spCompleteJson ?? $spCompleteRaw]);
    $salaryPayment = PaymentRequest::with('approvalInstance.records')->findOrFail($salaryPaymentId);
    $salaryPayRecords = assertInstanceSteps($salaryPayment->approval_instance_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['pending','waiting']);
    postApprove($kernel, $tempApprovers[8]['user'], $salaryPayRecords[0]->id, 'L8 approve salary payment');
    postApprove($kernel, $tempApprovers[10]['user'], $salaryPayRecords[1]->id, 'L10 approve salary payment');
    $salaryPayment = PaymentRequest::findOrFail($salaryPaymentId);
    ensureTrue($salaryPayment->status === 'approved', '工资付款申请业务状态未更新为approved', ['status' => $salaryPayment->status]);
    $summary['flows']['salary_payment_request'] = ['payment_request_id' => $salaryPaymentId, 'instance_id' => $salaryPayment->approval_instance_id, 'status' => $salaryPayment->status];

    // 5. 报销付款申请
    $reimbursementCandidate = DB::table('reimbursements as r')
        ->leftJoin('payment_requests as pr', 'pr.reimbursement_id', '=', 'r.id')
        ->where('r.account_set_id', 1)
        ->where('r.status', 'approved')
        ->whereNull('pr.id')
        ->orderByDesc('r.id')
        ->value('r.id');
    ensureTrue((bool)$reimbursementCandidate, '未找到可用于报销付款申请的已审批报销单');
    [$rpSubmitStatus, $rpSubmitJson, $rpSubmitRaw] = callJson($kernel, 'POST', '/api/reimbursement-payment-requests/submit', $admin, [
        'current_account_set_id' => 1,
        'reimbursement_id' => (int)$reimbursementCandidate,
        'remarks' => 'codex reimbursement payment request',
    ]);
    ensureTrue($rpSubmitStatus === 200 && ($rpSubmitJson['success'] ?? false), '创建报销付款申请失败', ['status' => $rpSubmitStatus, 'resp' => $rpSubmitJson ?? $rpSubmitRaw]);
    $reimPaymentId = (int)$rpSubmitJson['data']['id'];
    [$rpCompleteStatus, $rpCompleteJson, $rpCompleteRaw] = callJson($kernel, 'POST', '/api/reimbursement-payment-requests/complete-submission', $admin, [
        'payment_request_id' => $reimPaymentId,
        'stamp_selection_mode' => 'none',
    ]);
    ensureTrue($rpCompleteStatus === 200 && ($rpCompleteJson['success'] ?? false), '提交报销付款审批失败', ['status' => $rpCompleteStatus, 'resp' => $rpCompleteJson ?? $rpCompleteRaw]);
    $reimPayment = PaymentRequest::with('approvalInstance.records')->findOrFail($reimPaymentId);
    $reimRecords = assertInstanceSteps($reimPayment->approval_instance_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['pending','waiting']);
    postApprove($kernel, $tempApprovers[8]['user'], $reimRecords[0]->id, 'L8 approve reimbursement payment');
    postApprove($kernel, $tempApprovers[10]['user'], $reimRecords[1]->id, 'L10 approve reimbursement payment');
    $reimPayment = PaymentRequest::findOrFail($reimPaymentId);
    ensureTrue($reimPayment->status === 'approved', '报销付款申请业务状态未更新为approved', ['status' => $reimPayment->status]);
    $summary['flows']['reimbursement_payment_request'] = ['payment_request_id' => $reimPaymentId, 'instance_id' => $reimPayment->approval_instance_id, 'status' => $reimPayment->status];

    // 6. 人员变动申请
    $personnelChange = PersonnelChangeRequest::where('account_set_id', 1)
        ->whereNull('approval_flow_id')
        ->orderBy('id')
        ->first();
    ensureTrue((bool)$personnelChange, '未找到可用于人员变动审批的待提交记录');
    $creator = User::findOrFail($personnelChange->created_by);
    [$pcCompleteStatus, $pcCompleteJson, $pcCompleteRaw] = callJson($kernel, 'POST', '/api/personnel-change-requests/complete-submission', $creator, [
        'current_account_set_id' => 1,
        'personnel_change_request_id' => $personnelChange->id,
        'stamp_selection_mode' => 'none',
    ]);
    ensureTrue($pcCompleteStatus === 200 && ($pcCompleteJson['success'] ?? false), '提交人员变动审批失败', ['status' => $pcCompleteStatus, 'resp' => $pcCompleteJson ?? $pcCompleteRaw]);
    $personnelChange = PersonnelChangeRequest::findOrFail($personnelChange->id);
    $pcRecords = assertInstanceSteps($personnelChange->approval_flow_id, [1,2], [$tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['pending','waiting']);
    postApprove($kernel, $tempApprovers[8]['user'], $pcRecords[0]->id, 'L8 approve personnel change');
    postApprove($kernel, $tempApprovers[10]['user'], $pcRecords[1]->id, 'L10 approve personnel change');
    $personnelChange = PersonnelChangeRequest::findOrFail($personnelChange->id);
    $personnelInstance = ApprovalInstance::find($personnelChange->approval_flow_id);
    $summary['flows']['personnel_change'] = [
        'request_id' => $personnelChange->id,
        'instance_id' => $personnelChange->approval_flow_id,
        'status' => $personnelChange->status,
        'instance_status' => $personnelInstance?->status,
    ];
    if (!in_array($personnelChange->status, ['approved', 'completed'], true)) {
        $summary['flows']['personnel_change']['warning'] = '审批实例已完成，但业务表状态未进入完成态';
    }

    // 7. 发票申请
    [$iaStoreStatus, $iaStoreJson, $iaStoreRaw] = callJson($kernel, 'POST', '/api/invoice-applications', $admin, [
        'current_account_set_id' => 1,
        'task_name' => 'CODEx发票申请-' . uniqid(),
        'year' => 2026,
        'month' => 6,
        'project_name' => 'CODEx项目',
        'remark' => 'codex invoice app',
        'period_year' => 2026,
        'period_month' => 6,
        'company_name' => '测试开票公司',
        'application_date' => now()->toDateString(),
        'invoice_method' => 'full',
        'invoice_type' => '普通发票',
        'deduction_amount' => 0,
        'tax_rate' => 0.06,
        'invoice_amount' => 1000,
        'invoice_date' => now()->toDateString(),
        'invoice_remark' => 'codex',
    ]);
    ensureTrue($iaStoreStatus === 200 && ($iaStoreJson['success'] ?? false), '创建发票申请失败', ['status' => $iaStoreStatus, 'resp' => $iaStoreJson ?? $iaStoreRaw]);
    $invoiceAppId = (int)$iaStoreJson['data']['id'];
    [$iaItemStatus, $iaItemJson, $iaItemRaw] = callJson($kernel, 'POST', '/api/invoice-applications/' . $invoiceAppId . '/items', $admin, [
        'item_name' => 'CODEx明细项目',
        'spec_model' => '规格A',
        'unit' => '项',
        'quantity' => 1,
        'unit_price' => 1000,
        'amount' => 1000,
        'tax_rate' => 0.06,
        'tax_amount' => 56.6,
        'remark' => 'codex item',
    ]);
    ensureTrue($iaItemStatus === 200 && ($iaItemJson['success'] ?? false), '添加发票明细失败', ['status' => $iaItemStatus, 'resp' => $iaItemJson ?? $iaItemRaw]);
    [$iaUploadStatus, $iaUploadJson, $iaUploadRaw] = callUpload($kernel, 'POST', '/api/invoice-applications/' . $invoiceAppId . '/attachments', $admin, [], __DIR__ . '/tmp_attach_paymentapp.txt', 'invoice_probe.txt', 'text/plain');
    ensureTrue($iaUploadStatus === 200 && ($iaUploadJson['success'] ?? false), '上传发票申请附件失败', ['status' => $iaUploadStatus, 'resp' => $iaUploadJson ?? $iaUploadRaw]);
    $createdFiles[] = storage_path('app/public/' . $iaUploadJson['data']['path']);
    [$iaSubmitStatus, $iaSubmitJson, $iaSubmitRaw] = callJson($kernel, 'POST', '/api/invoice-applications/' . $invoiceAppId . '/submit', $admin, [
        'stamp_selection_mode' => 'none',
    ]);
    ensureTrue($iaSubmitStatus === 200 && ($iaSubmitJson['success'] ?? false), '提交发票申请审批失败', ['status' => $iaSubmitStatus, 'resp' => $iaSubmitJson ?? $iaSubmitRaw]);
    $invoiceApp = InvoiceApplication::with('approvalInstance.records')->findOrFail($invoiceAppId);
    $invoiceRecords = assertInstanceSteps($invoiceApp->approval_instance_id, [1,2,3], [2, $tempApprovers[8]['user']->id, $tempApprovers[10]['user']->id], ['approved','pending','waiting']);
    postApprove($kernel, $tempApprovers[8]['user'], $invoiceRecords[1]->id, 'L8 approve invoice');
    postApprove($kernel, $tempApprovers[10]['user'], $invoiceRecords[2]->id, 'L10 approve invoice');
    $invoiceApp = InvoiceApplication::with('approvalInstance.records')->findOrFail($invoiceAppId);
    ensureTrue($invoiceApp->approvalInstance->status === 'approved', '发票申请审批实例未完成', ['status' => $invoiceApp->approvalInstance->status]);
    ensureTrue(in_array($invoiceApp->approval_status, ['approved', 'completed', '已通过'], true) || $invoiceApp->approval_status === InvoiceApplication::APPROVAL_STATUS_APPROVED, '发票申请审批状态未更新为approved', ['approval_status' => $invoiceApp->approval_status]);
    $summary['flows']['invoice_application'] = ['application_id' => $invoiceAppId, 'instance_id' => $invoiceApp->approval_instance_id, 'approval_status' => $invoiceApp->approval_status];

    echo json_encode(['success' => true, 'summary' => $summary], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'summary' => $summary], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
} finally {
    foreach ($createdFiles as $file) {
        if ($file && file_exists($file)) { @unlink($file); }
    }
    if (DB::transactionLevel() > 0) {
        while (DB::transactionLevel() > 0) { DB::rollBack(); }
    }
}
