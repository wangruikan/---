<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\InsuranceController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\SharedFileController;
use App\Http\Controllers\AccountSetController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\SocialSecurityController;
use App\Http\Controllers\HousingFundController;
use App\Http\Controllers\SalarySheetController;
use App\Http\Controllers\HousingFundConfigController;
use App\Http\Controllers\HousingFundRegionController;
use App\Http\Controllers\OtherInsuranceController;
use App\Http\Controllers\InsuranceSurrenderController;
use App\Http\Controllers\SpecialDeductionController;
use App\Http\Controllers\InsuranceCompensationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollRemarkController;
use App\Http\Controllers\BidProjectController;
use App\Http\Controllers\InvoiceProjectController;
use App\Http\Controllers\InvoiceApplicationController;
use App\Http\Controllers\ReimbursementController;
use App\Http\Controllers\ReimbursementPaymentRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProcessRecordController;
use App\Http\Controllers\SalaryPaymentRecordController;
use App\Http\Controllers\MaterialAssetController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\PaymentReminderController;
// use App\Http\Controllers\SystemSettingController;
// use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/test', function () {
    return response()->json(['message' => 'Laravel API is working!']);
});

Route::get('/debug-auth-header', function (Request $request) {
    return response()->json([
        'authorization' => $request->header('Authorization'),
        'bearer'        => $request->bearerToken(),
        'user_id'       => optional($request->user())->id,
    ]);
});

// 认证路由 - 不需要认证的路由
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// 定时任务触发端点（不需要认证）
Route::get('/cron/check-insurance-deadlines', [App\Http\Controllers\CronController::class, 'checkInsuranceDeadlines']);
Route::match(['GET', 'POST'], '/cron/apply-base-adjustments', [App\Http\Controllers\BaseAdjustmentController::class, 'applyDue']);

Route::match(['GET', 'POST'], '/cron/process-employee-adjustments', function (\Illuminate\Http\Request $request) {
    $dateInput = $request->input('date');
    if ($dateInput) {
        try {
            $date = \Carbon\Carbon::createFromFormat('Y-m-d', $dateInput);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => '日期格式错误，请使用 YYYY-MM-DD',
            ], 422);
        }
        if (!$date || $date->format('Y-m-d') !== $dateInput) {
            return response()->json([
                'success' => false,
                'message' => '日期格式错误，请使用 YYYY-MM-DD',
            ], 422);
        }
        $date = $date->toDateString();
    } else {
        $date = now()->toDateString();
    }

    $exitCode = \Artisan::call('base:process-employee-adjustments', ['--date' => $date]);

    return response()->json([
        'success' => $exitCode === 0,
        'message' => $exitCode === 0 ? '处理完成' : '处理失败',
        'output' => \Artisan::output(),
        'date' => $date
    ], $exitCode === 0 ? 200 : 500);
});

Route::match(['GET', 'POST'], '/cron/process-base-compensation', function (\Illuminate\Http\Request $request) {
    $dateInput = $request->input('date');
    if ($dateInput) {
        try {
            $date = \Carbon\Carbon::createFromFormat('Y-m-d', $dateInput);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => '日期格式错误，请使用 YYYY-MM-DD',
            ], 422);
        }
        if (!$date || $date->format('Y-m-d') !== $dateInput) {
            return response()->json([
                'success' => false,
                'message' => '日期格式错误，请使用 YYYY-MM-DD',
            ], 422);
        }
        $date = $date->toDateString();
    } else {
        $date = now()->toDateString();
    }

    $exitCode = \Artisan::call('base:process-compensation', ['--date' => $date]);

    return response()->json([
        'success' => $exitCode === 0,
        'message' => $exitCode === 0 ? '处理完成' : '处理失败',
        'output' => \Artisan::output(),
        'date' => $date
    ], $exitCode === 0 ? 200 : 500);
});

Route::match(['GET', 'POST'], '/cron/process-limit-effective', function (\Illuminate\Http\Request $request) {
    $dateInput = $request->input('date');
    if ($dateInput) {
        try {
            $date = \Carbon\Carbon::createFromFormat('Y-m-d', $dateInput);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => '日期格式错误，请使用 YYYY-MM-DD',
            ], 422);
        }
        if (!$date || $date->format('Y-m-d') !== $dateInput) {
            return response()->json([
                'success' => false,
                'message' => '日期格式错误，请使用 YYYY-MM-DD',
            ], 422);
        }
        $date = $date->toDateString();
    } else {
        $date = now()->toDateString();
    }

    $exitCode = \Artisan::call('insurance:process-limit-effective', ['--date' => $date]);

    return response()->json([
        'success' => $exitCode === 0,
        'message' => $exitCode === 0 ? '处理完成' : '处理失败',
        'output' => \Artisan::output(),
        'date' => $date
    ], $exitCode === 0 ? 200 : 500);
});

// 测试PDF生成（不需要认证）
Route::get('/test-pdf-form', [App\Http\Controllers\EmployeeController::class, 'testOnboardingFormPdf']);

// 获取不带签名的PDF供前端合成
Route::get('/get-pdf-for-merge', [App\Http\Controllers\EmployeeController::class, 'getPdfForMerge']);

// 批量获取员工PDF数据供前端合成签名
Route::post('/get-batch-pdfs-for-merge', [App\Http\Controllers\EmployeeController::class, 'getBatchPdfsForMerge']);

// 最简单的中文PDF测试
Route::get('/test-chinese-pdf', function() {
    try {
        $html = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
</head>
<body>
    <h1>中文测试</h1>
    <p>姓名：张三</p>
    <p>部门：技术部</p>
    <p>职位：软件工程师</p>
</body>
</html>';
        
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->WriteHTML($html);
        
        return response($mpdf->Output('', 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="test-chinese.pdf"');
            
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// 合同下载（公开，不走 Sanctum，供前端带自定义 Token 调用）
Route::get('/employees/contracts/{id}/download', [App\Http\Controllers\EmployeeContractController::class, 'download']);
// 汇总申请附件下载（公开，不走 Sanctum，兼容直接下载场景）
Route::get('/process-approvals/{id}/attachments/{attachmentId}/download', [App\Http\Controllers\ProcessApprovalController::class, 'downloadAttachment']);

// 需要认证的路由
Route::middleware('auth:sanctum')->group(function () {
    // 认证相关
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });

    // Dashboard相关
    Route::prefix('dashboard')->group(function () {
        Route::get('/data', [DashboardController::class, 'getDashboardData']);  // 统一接口
        Route::get('/stats', [DashboardController::class, 'getStats']);
        Route::get('/reminders', [DashboardController::class, 'getReminders']);
        Route::get('/projects', [DashboardController::class, 'getProjects']);
        Route::get('/employee-distribution', [DashboardController::class, 'getEmployeeDistribution']);
        Route::get('/contract-statistics', [DashboardController::class, 'getContractStatistics']);
        Route::post('/reminders/mark-read', [DashboardController::class, 'markReminderAsRead']);
    });

    // 缴费提醒配置相关（全局配置，不分账套）
    Route::prefix('payment-reminders')->group(function () {
        // 缴费日期配置
        Route::get('/due-date-configs', [PaymentReminderController::class, 'getDueDateConfigs']);
        Route::post('/due-date-configs', [PaymentReminderController::class, 'saveDueDateConfig']);
        Route::post('/due-date-configs/batch', [PaymentReminderController::class, 'batchSaveDueDateConfigs']);
        
        // 提醒时间配置
        Route::get('/reminder-configs', [PaymentReminderController::class, 'getReminderConfigs']);
        Route::post('/reminder-configs', [PaymentReminderController::class, 'saveReminderConfig']);
        Route::put('/reminder-configs/{id}', [PaymentReminderController::class, 'updateReminderConfig']);
        Route::delete('/reminder-configs/{id}', [PaymentReminderController::class, 'deleteReminderConfig']);
    });

    // 流程记录管理路由
    Route::get('/process-records', [ProcessRecordController::class, 'index']);
    Route::get('/process-records/stats', [ProcessRecordController::class, 'getStats']);
    Route::get('/process-records/check-access', [ProcessRecordController::class, 'checkAccess']);

    // 用户管理（超级管理员专用）
    Route::prefix('users')->group(function () {
        // 用户当前账套选择（必须放在 {id} 路由之前）
        Route::put('/current-account-set', [UserController::class, 'updateCurrentAccountSet']);
        Route::get('/current-account-set', [UserController::class, 'getCurrentAccountSet']);
        
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::put('/{id}/status', [UserController::class, 'updateStatus']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
    });

    // 权限管理（仅admin可访问）
    Route::prefix('permissions')->group(function () {
        Route::get('/', [App\Http\Controllers\PermissionController::class, 'index']);
        Route::get('/my', [App\Http\Controllers\PermissionController::class, 'myPermissions']);
        Route::get('/users', [App\Http\Controllers\PermissionController::class, 'getUsersWithPermissions']);
        Route::get('/users/{userId}', [App\Http\Controllers\PermissionController::class, 'getUserPermissions']);
        Route::put('/users/{userId}', [App\Http\Controllers\PermissionController::class, 'updateUserPermissions']);
    });
    
    // 黑名单管理
    Route::prefix('blacklist')->group(function () {
        Route::get('/', [App\Http\Controllers\BlacklistController::class, 'index']);
        Route::post('/', [App\Http\Controllers\BlacklistController::class, 'store']);
        Route::delete('/{id}', [App\Http\Controllers\BlacklistController::class, 'destroy']);
        Route::post('/check', [App\Http\Controllers\BlacklistController::class, 'check']);
    });
    
    // 报表模板管理
    Route::prefix('report-templates')->group(function () {
        Route::get('/', [App\Http\Controllers\ReportTemplateController::class, 'index']);
        Route::post('/', [App\Http\Controllers\ReportTemplateController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\ReportTemplateController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\ReportTemplateController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\ReportTemplateController::class, 'destroy']);
        Route::post('/{id}/copy-to-regions', [App\Http\Controllers\ReportTemplateController::class, 'copyToRegions']);
    });
    
    // 角色权限管理（RBAC）
    Route::prefix('roles')->group(function () {
        Route::get('/', [App\Http\Controllers\RoleController::class, 'index']);
        Route::get('/permissions/all', [App\Http\Controllers\RoleController::class, 'getPermissions']);
        Route::get('/{id}', [App\Http\Controllers\RoleController::class, 'show']);
        Route::put('/{id}/permissions', [App\Http\Controllers\RoleController::class, 'updatePermissions']);
        Route::put('/{id}/visible-menus', [App\Http\Controllers\RoleController::class, 'updateVisibleMenus']);
    });

    // 员工管理 - 真实数据库
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index']);
        Route::post('/', [EmployeeController::class, 'store']);
        Route::get('/debug-data', [EmployeeController::class, 'debugEmployeeData']); // 调试员工数据
        Route::get('/test-registration-pdf', [EmployeeController::class, 'testRegistrationFormPdf']); // 测试从业人员登记表PDF
        Route::get('/expired-id-cards', [EmployeeController::class, 'getExpiredIdCards']); // 获取身份证过期员工列表
        Route::get('/download-import-template', [EmployeeController::class, 'downloadImportTemplate']); // 下载批量导入模板
        Route::post('/import', [EmployeeController::class, 'importEmployees']); // 批量导入员工
        
        // 线下入职相关（必须在 /{id} 之前）
        Route::get('/pending-contract-upload', [App\Http\Controllers\OfflineOnboardingController::class, 'getPendingContractUpload']);
        
        Route::get('/{id}', [EmployeeController::class, 'show']);
        Route::put('/{id}', [EmployeeController::class, 'update']);
        Route::delete('/{id}', [EmployeeController::class, 'destroy']);
        
        // 员工删除审批
        Route::post('/delete-approval', [EmployeeController::class, 'submitDeleteApproval']);
        // 员工工资调整审批
        Route::post('/salary-adjustment-approval', [EmployeeController::class, 'submitSalaryAdjustmentApproval']);
        
        // 线下入职相关
        Route::post('/{id}/offline-onboarding', [App\Http\Controllers\OfflineOnboardingController::class, 'submitOfflineOnboarding']);
        Route::post('/{id}/mark-contract-uploaded', [App\Http\Controllers\OfflineOnboardingController::class, 'markContractUploaded']);
        
        // 新增生成工号路由
        Route::get('/generate-employee-number', [EmployeeController::class, 'generateEmployeeNumberApi']);
        
        // 批量下载员工资料
        Route::post('/batch-download-documents', [EmployeeController::class, 'batchDownloadDocuments']);
        
        // 获取员工入职登记表
        Route::get('/{id}/onboarding-form', [EmployeeController::class, 'getOnboardingForm']);
        Route::get('/{id}/view-details', [EmployeeController::class, 'getViewDetails']);
        
        // 获取员工从业人员登记表
        Route::get('/{id}/registration-form', [EmployeeController::class, 'getRegistrationForm']);
        
        // 测试生成PDF格式
        Route::get('/test-pdf', [EmployeeController::class, 'testOnboardingFormPdf']);
        
        // 员工合同管理
        Route::get('/{employeeId}/contracts', [App\Http\Controllers\EmployeeContractController::class, 'index']);
        Route::post('/contracts', [App\Http\Controllers\EmployeeContractController::class, 'store']);
        Route::post('/contracts/upload-signed', [App\Http\Controllers\EmployeeContractController::class, 'uploadSignedContract']); // 上传已签署合同（线下入职专用）
        Route::post('/contracts/with-placeholder-fill', [App\Http\Controllers\EmployeeContractController::class, 'createWithPlaceholderFill']);
        Route::post('/contracts/save-filled', [App\Http\Controllers\EmployeeContractController::class, 'saveFilledContract']);
        Route::post('/contracts/{id}/submit', [App\Http\Controllers\EmployeeContractController::class, 'submit']);
        Route::post('/contracts/{id}/employee-sign', [App\Http\Controllers\EmployeeContractController::class, 'employeeSign']);
        Route::post('/contracts/{id}/complete', [App\Http\Controllers\EmployeeContractController::class, 'complete']);
        Route::post('/contracts/merge-signature', [App\Http\Controllers\EmployeeContractController::class, 'mergeSignature']);
        Route::delete('/contracts/{id}', [App\Http\Controllers\EmployeeContractController::class, 'destroy']);
        Route::get('/contracts/{id}/download-auth', [App\Http\Controllers\EmployeeContractController::class, 'download']);
        
        // 员工参保地区选择
        Route::get('/projects/{projectId}/social-security-regions', [EmployeeController::class, 'getProjectSocialSecurityRegions']);
        Route::get('/projects/{projectId}/housing-fund-regions', [EmployeeController::class, 'getProjectHousingFundRegions']);
        Route::get('/projects/{projectId}/medical-insurance-regions', [EmployeeController::class, 'getProjectMedicalInsuranceRegions']);
        Route::get('/projects/{projectId}/other-insurance-policies', [EmployeeController::class, 'getProjectOtherInsurancePolicies']);
        Route::get('/projects/{projectId}/large-medical-insurance-configs', [EmployeeController::class, 'getProjectLargeMedicalInsuranceConfigs']);
        
        // 员工资料上传管理
        Route::get('/{employeeId}/documents', [App\Http\Controllers\EmployeeDocumentController::class, 'index']);
        Route::post('/{employeeId}/documents/upload', [App\Http\Controllers\EmployeeDocumentController::class, 'upload']);
        Route::delete('/{employeeId}/documents/{documentId}', [App\Http\Controllers\EmployeeDocumentController::class, 'destroy']);
        Route::get('/{employeeId}/documents/{documentId}/download', [App\Http\Controllers\EmployeeDocumentController::class, 'download']);
        Route::get('/{employeeId}/documents/{documentId}/preview', [App\Http\Controllers\EmployeeDocumentController::class, 'preview']);

        // 员工离职证明管理
        Route::get('/{employeeId}/resignation-certificates', [App\Http\Controllers\ResignationCertificateController::class, 'index']);
        Route::post('/{employeeId}/resignation-certificates/upload', [App\Http\Controllers\ResignationCertificateController::class, 'upload']);
        Route::delete('/resignation-certificates/{id}', [App\Http\Controllers\ResignationCertificateController::class, 'destroy']);
        Route::get('/resignation-certificates/{id}/download', [App\Http\Controllers\ResignationCertificateController::class, 'download']);

        // 员工项目变更日志（调动记录）
        Route::get('/{employeeId}/project-change-logs', [EmployeeController::class, 'getProjectChangeLogs']);
        
        // 员工信息变更历史记录
        Route::get('/{id}/change-history', [EmployeeController::class, 'getChangeHistory']);
        
        // 大额医疗保险管理
        Route::get('/{id}/large-medical-status', [EmployeeController::class, 'getLargeMedicalStatus']);
        Route::post('/{id}/enable-large-medical', [EmployeeController::class, 'enableLargeMedical']);
    });
    
    // 批量导出入职登记表PDF
    Route::post('/employees/export-onboarding-pdfs', [EmployeeController::class, 'exportOnboardingFormsPdf']);
    
    // 批量导出从业人员登记表PDF
    Route::post('/employees/export-registration-pdfs', [EmployeeController::class, 'exportRegistrationFormPdf']);
    
    // 智能批量导出登记表PDF（根据项目设置自动选择）
    Route::post('/employees/export-smart-registration-pdfs', [EmployeeController::class, 'exportSmartRegistrationPdfs']);

    // 项目管理
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/generate-code-preview', [ProjectController::class, 'generateCodePreview']);
        Route::get('/{id}', [ProjectController::class, 'show']);
        Route::put('/{id}', [ProjectController::class, 'update']);
        Route::delete('/{id}', [ProjectController::class, 'destroy']);
        Route::get('/{id}/statistics', [ProjectController::class, 'getStatistics']);
        Route::post('/{id}/contract-notices', [ProjectController::class, 'setContractNotices']);  // 设置劳动合同须知
        Route::get('/{id}/contract-notices', [ProjectController::class, 'getContractNotices']);   // 获取劳动合同须知
        
        // 合同模板管理
        Route::get('/{id}/contract-templates', [ContractTemplateController::class, 'index']);
        Route::post('/{id}/contract-templates', [ContractTemplateController::class, 'store']);
        Route::post('/contract-templates/{templateId}/set-default', [ContractTemplateController::class, 'setDefault']);
        Route::delete('/contract-templates/{templateId}', [ContractTemplateController::class, 'destroy']);
        Route::get('/{id}/default-templates', [ContractTemplateController::class, 'getDefaultTemplates']);
        
        // 合同模板占位符位置管理
        Route::post('/contract-templates/placeholder-positions', [ContractTemplateController::class, 'savePlaceholderPositions']);
        Route::get('/contract-templates/{templateId}/placeholder-positions', [ContractTemplateController::class, 'getPlaceholderPositions']);
        
        // 占位符字段配置
        Route::get('/available-placeholder-fields', [ProjectController::class, 'getAvailablePlaceholderFields']);
        Route::get('/{id}/placeholder-fields', [ProjectController::class, 'getPlaceholderFields']);
        Route::post('/{id}/placeholder-fields', [ProjectController::class, 'savePlaceholderFields']);
        
        // 项目资料配置管理
        Route::get('/{projectId}/document-configs', [App\Http\Controllers\ProjectDocumentConfigController::class, 'index']);
        Route::post('/{projectId}/document-configs', [App\Http\Controllers\ProjectDocumentConfigController::class, 'store']);
        Route::put('/{projectId}/document-configs/{id}', [App\Http\Controllers\ProjectDocumentConfigController::class, 'update']);
        Route::delete('/{projectId}/document-configs/{id}', [App\Http\Controllers\ProjectDocumentConfigController::class, 'destroy']);
        Route::post('/{projectId}/document-configs/sort', [App\Http\Controllers\ProjectDocumentConfigController::class, 'updateSort']);
        
        // 社保和公积金地区管理 - 先定义具体路径，再定义参数路径
        Route::get('/available/social-security-regions', [ProjectController::class, 'getAvailableSocialSecurityRegions']);
        Route::get('/available/housing-fund-regions', [ProjectController::class, 'getAvailableHousingFundRegions']);
        Route::get('/available/medical-insurance-regions', [ProjectController::class, 'getAvailableMedicalInsuranceRegions']);
        Route::get('/available/other-insurance-policies', [ProjectController::class, 'getAvailableOtherInsurancePolicies']);
        Route::get('/available/large-medical-insurance-regions', [ProjectController::class, 'getAvailableLargeMedicalInsuranceRegions']);
        Route::get('/{id}/social-security-regions', [ProjectController::class, 'getSocialSecurityRegions']);
        Route::post('/{id}/social-security-regions', [ProjectController::class, 'setSocialSecurityRegions']);
        Route::get('/{id}/housing-fund-regions', [ProjectController::class, 'getHousingFundRegions']);
        Route::post('/{id}/housing-fund-regions', [ProjectController::class, 'setHousingFundRegions']);
        Route::get('/{id}/medical-insurance-regions', [ProjectController::class, 'getMedicalInsuranceRegions']);
        Route::post('/{id}/medical-insurance-regions', [ProjectController::class, 'setMedicalInsuranceRegions']);
        Route::get('/{id}/other-insurance-policies', [ProjectController::class, 'getOtherInsurancePolicies']);
        Route::post('/{id}/other-insurance-policies', [ProjectController::class, 'setOtherInsurancePolicies']);
        Route::get('/{id}/large-medical-insurance-configs', [ProjectController::class, 'getLargeMedicalInsuranceConfigs']);
        Route::post('/{id}/large-medical-insurance-configs', [ProjectController::class, 'setLargeMedicalInsuranceConfigs']);
    });

    // 考勤管理
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::get('/{id}', [AttendanceController::class, 'show']);
        Route::put('/{id}', [AttendanceController::class, 'update']);
        Route::delete('/{id}', [AttendanceController::class, 'destroy']);
        Route::post('/{id}/submit', [AttendanceController::class, 'submit']);
        Route::post('/{id}/reject', [AttendanceController::class, 'reject']);
        Route::post('/{id}/attendance-data', [AttendanceController::class, 'saveAttendanceData']);
        Route::get('/project/{projectId}/employees', [AttendanceController::class, 'getProjectEmployees']);
        Route::post('/{id}/upload-files', [AttendanceController::class, 'uploadFiles']); // 上传附件
        Route::get('/{id}/export', [AttendanceController::class, 'export']); // 导出考勤表
        Route::get('/{id}/approval-detail', [AttendanceController::class, 'getApprovalDetail']); // 获取审批详情
    });

    // 工资管理（旧版 - 单条记录）
    Route::prefix('salaries-old')->group(function () {
        Route::get('/', [SalaryController::class, 'index']);
        Route::post('/', [SalaryController::class, 'store']);
        Route::put('/{id}', [SalaryController::class, 'update']);
        Route::delete('/{id}', [SalaryController::class, 'destroy']);
        Route::post('/batch', [SalaryController::class, 'batchCreate']);
        Route::post('/{id}/submit', [SalaryController::class, 'submit']);
        Route::post('/{id}/approve', [SalaryController::class, 'approve']);
        Route::post('/{id}/reject', [SalaryController::class, 'reject']);
        Route::get('/summary', [SalaryController::class, 'getSummary']);
        Route::get('/payslip', [SalaryController::class, 'generatePayslip']);
    });
    
    // 工资管理（新版 - 按项目分组）
    Route::prefix('salaries')->group(function () {
        Route::get('/', [SalaryController::class, 'index']); // 获取工资表列表（按项目+期间分组）
        Route::post('/generate', [SalaryController::class, 'generate']); // 生成工资表
        Route::get('/details', [SalaryController::class, 'details']); // 获取工资明细
        Route::post('/validate-before-submit', [SalaryController::class, 'validateBeforeSubmit']); // 提交前验证
        Route::post('/submit', [SalaryController::class, 'submitSalary']); // 提交审批
        Route::post('/approve', [SalaryController::class, 'approveSalary']); // 审批通过
        Route::post('/reject', [SalaryController::class, 'rejectSalary']); // 审批拒绝
        Route::post('/pay', [SalaryController::class, 'paySalary']); // 标记发放
        Route::delete('/', [SalaryController::class, 'deleteSalary']); // 删除工资表
        Route::post('/create-payment-request', [SalaryController::class, 'createPaymentRequest']); // 发起付款申请
        Route::post('/import-gross-salary', [SalaryController::class, 'importGrossSalary']); // 导入应发工资
    });

    // 工资汇总
    Route::prefix('salary-summaries')->group(function () {
        Route::get('/', [App\Http\Controllers\SalarySummaryController::class, 'index']); // 获取汇总列表
        Route::get('/{id}', [App\Http\Controllers\SalarySummaryController::class, 'show']); // 获取详情
    });

    // 依据管理
    Route::prefix('basis-records')->group(function () {
        Route::get('/', [App\Http\Controllers\BasisRecordController::class, 'index']);
        Route::post('/', [App\Http\Controllers\BasisRecordController::class, 'store']);
        Route::post('/copy-last-month', [App\Http\Controllers\BasisRecordController::class, 'copyLastMonth']);
        Route::get('/available-projects', [App\Http\Controllers\BasisRecordController::class, 'getAvailableProjects']);
        Route::post('/check-exists', [App\Http\Controllers\BasisRecordController::class, 'checkBasisExists']);
        Route::get('/{id}', [App\Http\Controllers\BasisRecordController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\BasisRecordController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\BasisRecordController::class, 'destroy']);
        Route::post('/upload-attachment', [App\Http\Controllers\BasisRecordController::class, 'uploadAttachment']);
        Route::delete('/attachments/{id}', [App\Http\Controllers\BasisRecordController::class, 'deleteAttachment']);
    });

    // 工资表审批管理
    Route::prefix('salary-approvals')->group(function () {
        Route::get('/', [\App\Http\Controllers\SalaryApprovalController::class, 'index']); // 获取审批列表
        Route::post('/submit', [\App\Http\Controllers\SalaryApprovalController::class, 'submit']); // 提交审批
        Route::post('/complete-submission', [\App\Http\Controllers\SalaryApprovalController::class, 'completeSubmission']); // 完成提交（创建审批流程）
        Route::post('/approve', [\App\Http\Controllers\SalaryApprovalController::class, 'approve']); // 审批通过
        Route::post('/reject', [\App\Http\Controllers\SalaryApprovalController::class, 'reject']); // 审批拒绝
        Route::delete('/', [\App\Http\Controllers\SalaryApprovalController::class, 'destroy']); // 撤回审批
        
        // 附件管理
        Route::post('/attachments/upload', [\App\Http\Controllers\SalaryApprovalController::class, 'uploadAttachment']); // 上传附件
        Route::delete('/attachments', [\App\Http\Controllers\SalaryApprovalController::class, 'deleteAttachment']); // 删除附件
        Route::get('/attachments', [\App\Http\Controllers\SalaryApprovalController::class, 'getAttachments']); // 获取附件列表
        Route::get('/attachments/{id}/download', [\App\Http\Controllers\SalaryApprovalController::class, 'downloadAttachment']); // 下载附件
    });

    // 工资付款申请管理
    Route::prefix('salary-payment-requests')->group(function () {
        Route::get('/', [\App\Http\Controllers\SalaryPaymentRequestController::class, 'index']); // 获取付款申请列表
        Route::post('/submit', [\App\Http\Controllers\SalaryPaymentRequestController::class, 'submit']); // 提交付款申请
        Route::post('/complete-submission', [\App\Http\Controllers\SalaryPaymentRequestController::class, 'completeSubmission']); // 完成提交（创建审批流程）
        
        // 附件管理
        Route::post('/attachments/upload', [\App\Http\Controllers\SalaryPaymentRequestController::class, 'uploadAttachment']); // 上传附件
        Route::delete('/attachments', [\App\Http\Controllers\SalaryPaymentRequestController::class, 'deleteAttachment']); // 删除附件
    });

    // 保险付款申请管理
    Route::prefix('insurance-payment-requests')->group(function () {
        Route::post('/submit', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'submit']); // 提交付款申请
        Route::post('/complete-submission', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'completeSubmission']); // 完成提交（创建审批流程）
        
        // 附件管理
        Route::post('/attachments/upload', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'uploadAttachment']); // 上传附件
        Route::post('/attachments/replace', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'replaceAttachment']); // 替换附件
        Route::delete('/attachments', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'deleteAttachment']); // 删除附件
        
        // 发票审批相关
        Route::get('/check-invoice-permission', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'checkInvoiceUploadPermission']); // 检查发票上传权限
        Route::post('/invoice-attachments/upload', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'uploadInvoiceAttachment']); // 上传发票附件
        Route::delete('/invoice-attachments', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'deleteInvoiceAttachment']); // 删除发票附件
        Route::get('/invoice-attachments', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'getInvoiceAttachments']); // 获取发票附件列表
        Route::post('/submit-invoice-approval', [\App\Http\Controllers\InsurancePaymentRequestController::class, 'submitInvoiceApproval']); // 提交发票审批
    });

    // 报销付款申请管理
    Route::prefix('reimbursement-payment-requests')->group(function () {
        Route::get('/', [ReimbursementPaymentRequestController::class, 'index']); // 获取付款申请列表
        Route::post('/submit', [ReimbursementPaymentRequestController::class, 'submit']); // 提交付款申请
        Route::post('/complete-submission', [ReimbursementPaymentRequestController::class, 'completeSubmission']); // 完成提交（创建审批流程）
        
        // 附件管理
        Route::post('/attachments/upload', [ReimbursementPaymentRequestController::class, 'uploadAttachment']); // 上传附件
        Route::delete('/attachments', [ReimbursementPaymentRequestController::class, 'deleteAttachment']); // 删除附件
    });

    // 出款汇总
    Route::prefix('payment-summaries')->group(function () {
        Route::get('/', [\App\Http\Controllers\PaymentSummaryController::class, 'index']); // 获取出款汇总列表
        Route::get('/export', [\App\Http\Controllers\PaymentSummaryController::class, 'export']); // 导出Excel
    });

    // 保险管理
    Route::prefix('insurance')->group(function () {
        Route::get('/', [InsuranceController::class, 'index']);
        Route::post('/', [InsuranceController::class, 'store']);
        Route::put('/{id}', [InsuranceController::class, 'update']);
        Route::delete('/{id}', [InsuranceController::class, 'destroy']);
        Route::post('/{id}/complete', [InsuranceController::class, 'markAsCompleted']);
        Route::get('/overdue', [InsuranceController::class, 'getOverdue']);
        Route::get('/summary', [InsuranceController::class, 'getSummary']);
    });

    // 社保管理
    Route::prefix('social-security')->group(function () {
        Route::get('/', [SocialSecurityController::class, 'index']);
        Route::post('/', [SocialSecurityController::class, 'store']);
        Route::get('/{id}', [SocialSecurityController::class, 'show']);
        Route::put('/{id}', [SocialSecurityController::class, 'update']);
        Route::delete('/{id}', [SocialSecurityController::class, 'destroy']);
        Route::get('/{id}/limit-histories', [SocialSecurityController::class, 'getRegionLimitHistories']);
        Route::post('/{regionId}/types', [SocialSecurityController::class, 'addType']);
        Route::put('/types/{typeId}', [SocialSecurityController::class, 'updateType']);
        Route::delete('/types/{typeId}', [SocialSecurityController::class, 'destroyType']);
    });

    // 公积金管理
    Route::prefix('housing-fund')->group(function () {
        Route::get('/', [HousingFundController::class, 'index']);
        Route::post('/', [HousingFundController::class, 'store']);
        Route::put('/{id}', [HousingFundController::class, 'update']);
        Route::delete('/{id}', [HousingFundController::class, 'destroy']);
    });

    // 公积金地区管理
    Route::prefix('housing-fund-regions')->group(function () {
        Route::get('/', [HousingFundRegionController::class, 'index']);
        Route::post('/', [HousingFundRegionController::class, 'store']);
        Route::get('/{id}', [HousingFundRegionController::class, 'show']);
        Route::put('/{id}', [HousingFundRegionController::class, 'update']);
        Route::delete('/{id}', [HousingFundRegionController::class, 'destroy']);
        Route::get('/{id}/configs', [HousingFundRegionController::class, 'getConfigs']);
        Route::get('/{id}/limit-histories', [HousingFundRegionController::class, 'getRegionLimitHistories']);
    });

    // 公积金配置管理
    Route::prefix('housing-fund-configs')->group(function () {
        Route::get('/', [HousingFundConfigController::class, 'index']);
        Route::post('/', [HousingFundConfigController::class, 'store']);
        Route::get('/{id}', [HousingFundConfigController::class, 'show']);
        Route::put('/{id}', [HousingFundConfigController::class, 'update']);
        Route::delete('/{id}', [HousingFundConfigController::class, 'destroy']);
    });

    // 医保管理
    Route::prefix('medical-insurance')->group(function () {
        Route::get('/', [\App\Http\Controllers\MedicalInsuranceController::class, 'getRegions']);
        Route::post('/', [\App\Http\Controllers\MedicalInsuranceController::class, 'createRegion']);
        Route::get('/{id}', [\App\Http\Controllers\MedicalInsuranceController::class, 'showRegion']);
        Route::put('/{id}', [\App\Http\Controllers\MedicalInsuranceController::class, 'updateRegion']);
        Route::delete('/{id}', [\App\Http\Controllers\MedicalInsuranceController::class, 'deleteRegion']);
        Route::get('/{id}/limit-histories', [\App\Http\Controllers\MedicalInsuranceController::class, 'getRegionLimitHistories']);
        Route::post('/{regionId}/types', [\App\Http\Controllers\MedicalInsuranceController::class, 'addType']);
        Route::put('/types/{typeId}', [\App\Http\Controllers\MedicalInsuranceController::class, 'updateType']);
        Route::delete('/types/{typeId}', [\App\Http\Controllers\MedicalInsuranceController::class, 'deleteType']);
    });

    // 其他保险管理
    Route::prefix('other-insurance')->group(function () {
        // 保险种类管理
        Route::get('/types', [OtherInsuranceController::class, 'getTypes']);
        Route::post('/types', [OtherInsuranceController::class, 'createType']);
        Route::put('/types/{id}', [OtherInsuranceController::class, 'updateType']);
        Route::delete('/types/{id}', [OtherInsuranceController::class, 'deleteType']);
        
        // 保单管理
        Route::get('/types/{typeId}/policies', [OtherInsuranceController::class, 'getPolicies']);
        Route::post('/types/{typeId}/policies', [OtherInsuranceController::class, 'createPolicy']);
        Route::put('/policies/{id}', [OtherInsuranceController::class, 'updatePolicy']);
        Route::delete('/policies/{id}', [OtherInsuranceController::class, 'deletePolicy']);
    });

    // 审批管理（旧版）
    Route::prefix('approvals')->group(function () {
        Route::get('/', [ApprovalController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\ApprovalFlowController::class, 'store']); // 新：创建审批流程
        Route::post('/resubmit', [\App\Http\Controllers\ApprovalFlowController::class, 'resubmit']); // 新：重新发起审批
        Route::get('/my-tasks', [\App\Http\Controllers\ApprovalFlowController::class, 'myTasks']); // 新：我的待办
        Route::get('/my-approved', [\App\Http\Controllers\ApprovalFlowController::class, 'myApproved']); // 新：我审批的
        Route::get('/my-initiated', [\App\Http\Controllers\ApprovalFlowController::class, 'myInitiated']); // 新：我发起的
        Route::get('/cc-to-me', [\App\Http\Controllers\ApprovalFlowController::class, 'ccToMe']); // 新：抄送给我
        Route::get('/{id}', [\App\Http\Controllers\ApprovalFlowController::class, 'show']); // 新：审批详情
        Route::post('/records/{recordId}/upload-signed-pdf', [\App\Http\Controllers\ApprovalFlowController::class, 'uploadSignedPDF']); // 新：上传合成PDF
        Route::post('/records/{recordId}/batch-stamp', [\App\Http\Controllers\ApprovalFlowController::class, 'batchStamp']); // 新：批量盖章
        Route::post('/records/{recordId}/replace-attachment', [\App\Http\Controllers\ApprovalFlowController::class, 'replaceAttachment']); // 新：替换附件（批量盖章用）
        Route::post('/records/{recordId}/approve', [\App\Http\Controllers\ApprovalFlowController::class, 'approve']); // 新：审批通过
        Route::post('/records/{recordId}/return', [\App\Http\Controllers\ApprovalFlowController::class, 'returnToPrevious']); // 新：退回上一级
        Route::post('/records/{recordId}/reject', [\App\Http\Controllers\ApprovalFlowController::class, 'reject']); // 新：驳回
        Route::post('/{instanceId}/withdraw', [\App\Http\Controllers\ApprovalFlowController::class, 'withdraw']); // 新：撤回审批
        Route::post('/{instanceId}/upload-attachment', [\App\Http\Controllers\ApprovalFlowController::class, 'uploadAttachment']); // 新：上传附件
        Route::delete('/{instanceId}/attachments/{attachmentId}', [\App\Http\Controllers\ApprovalFlowController::class, 'deleteAttachment']); // 新：删除附件
    });

    // 付款管理
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::put('/{id}', [PaymentController::class, 'update']);
        Route::delete('/{id}', [PaymentController::class, 'destroy']);
        Route::post('/{id}/submit', [PaymentController::class, 'submit']);
        Route::post('/{id}/approve', [PaymentController::class, 'approve']);
        Route::post('/{id}/pay', [PaymentController::class, 'pay']);
        Route::post('/{id}/record', [PaymentController::class, 'record']);
        Route::get('/summary', [PaymentController::class, 'getSummary']);
    });

    // 发票管理
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::put('/{id}', [InvoiceController::class, 'update']);
        Route::delete('/{id}', [InvoiceController::class, 'destroy']);
        Route::post('/{id}/submit', [InvoiceController::class, 'submit']);
        Route::post('/{id}/approve', [InvoiceController::class, 'approve']);
        Route::post('/{id}/issue', [InvoiceController::class, 'issue']);
        Route::get('/summary', [InvoiceController::class, 'getSummary']);
    });

    // 招聘管理
Route::prefix('recruitment')->group(function () {
    Route::get('/', [RecruitmentController::class, 'index']);
    Route::get('/permissions', [RecruitmentController::class, 'getPermissions']); // 获取权限信息
    Route::post('/', [RecruitmentController::class, 'store']);
    Route::put('/{id}', [RecruitmentController::class, 'update']);
    Route::delete('/{id}', [RecruitmentController::class, 'destroy']);
    Route::post('/{id}/assign', [RecruitmentController::class, 'assign']);
    Route::post('/{id}/progress', [RecruitmentController::class, 'updateProgress']);
    Route::post('/{id}/complete', [RecruitmentController::class, 'complete']);
    
    // 候选人管理
    Route::get('/{recruitmentId}/candidates', [RecruitmentController::class, 'getCandidates']);
    Route::post('/candidates', [RecruitmentController::class, 'storeCandidate']);
    Route::put('/candidates/{id}', [RecruitmentController::class, 'updateCandidate']);
    Route::delete('/candidates/{id}', [RecruitmentController::class, 'destroyCandidate']);
});

    // 共享文件
    Route::prefix('shared-files')->group(function () {
        Route::get('/', [SharedFileController::class, 'index']);
        Route::post('/', [SharedFileController::class, 'store']);
        Route::get('/{id}', [SharedFileController::class, 'show']);
        Route::put('/{id}', [SharedFileController::class, 'update']);
        Route::delete('/{id}', [SharedFileController::class, 'destroy']);
        Route::get('/{id}/download', [SharedFileController::class, 'download']);
    });

    // 资料/物品中心（公章、营业执照等）
    Route::prefix('material-assets')->group(function () {
        Route::get('/', [MaterialAssetController::class, 'index']);
        Route::post('/', [MaterialAssetController::class, 'store']);
        Route::get('/{id}', [MaterialAssetController::class, 'show']);
        Route::put('/{id}', [MaterialAssetController::class, 'update']);
        Route::delete('/{id}', [MaterialAssetController::class, 'destroy']);
        Route::post('/{id}/files', [MaterialAssetController::class, 'uploadFile']);
        Route::delete('/{id}/files/{fileId}', [MaterialAssetController::class, 'deleteFile']);
    });

    // 资料/物品申请（可多选、可分开归还）
    Route::prefix('material-requests')->group(function () {
        Route::get('/', [MaterialRequestController::class, 'index']);
        Route::post('/', [MaterialRequestController::class, 'store']);
        Route::get('/{id}', [MaterialRequestController::class, 'show']);
        Route::post('/{id}/return', [MaterialRequestController::class, 'returnMaterials']);
    });

    // 账套管理
    Route::prefix('account-sets')->group(function () {
        // 所有用户都可以访问的接口
        Route::get('/my', [AccountSetController::class, 'getMyAccountSets']); // 获取我的账套
        Route::post('/set-user-default', [AccountSetController::class, 'setUserDefault']); // 设置我的默认账套
        
        // 只有管理员可以访问的接口
        Route::get('/statistics', [AccountSetController::class, 'getStatistics']);
        Route::get('/', [AccountSetController::class, 'index']);
        Route::post('/', [AccountSetController::class, 'store']);
        Route::get('/{id}', [AccountSetController::class, 'show']);
        Route::put('/{id}', [AccountSetController::class, 'update']);
        Route::delete('/{id}', [AccountSetController::class, 'destroy']);
        Route::post('/{id}/set-default', [AccountSetController::class, 'setDefault']);
        Route::post('/{id}/archive', [AccountSetController::class, 'archive']);
        
        // 账套管理员分配
        Route::post('/{id}/assign-users', [AccountSetController::class, 'assignUsers']);
        Route::get('/{id}/users', [AccountSetController::class, 'getUsers']);
        Route::delete('/{id}/users/{userId}', [AccountSetController::class, 'removeUser']);
        Route::put('/{id}/users/{userId}/approval-level', [AccountSetController::class, 'setApprovalLevel']);
    });
});

// 系统设置 - 暂时注释掉,因为控制器不存在
// Route::prefix('settings')->group(function () {
//     Route::get('/', [SystemSettingController::class, 'index']);
//     Route::post('/', [SystemSettingController::class, 'store']);
//     Route::put('/{id}', [SystemSettingController::class, 'update']);
//     Route::delete('/{id}', [SystemSettingController::class, 'destroy']);
//     Route::get('/{key}', [SystemSettingController::class, 'get']);
//     Route::post('/{key}', [SystemSettingController::class, 'set']);
// });

// 通知管理 - 暂时注释掉，因为控制器不存在
// Route::prefix('notifications')->group(function () {
//     Route::get('/', [NotificationController::class, 'index']);
//     Route::get('/unread', [NotificationController::class, 'getUnread']);
//     Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
//     Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
// });

// ========================================
// 小程序端 API（员工使用）
// ========================================

// 小程序登录（不需要认证）
Route::prefix('mini')->group(function () {
    Route::post('/login', [App\Http\Controllers\MiniController::class, 'login']);
});

// 小程序需要认证的接口
Route::prefix('mini')->middleware('auth:sanctum')->group(function () {
    // 用户相关
    Route::get('/my-info', [App\Http\Controllers\MiniController::class, 'getMyInfo']);
    
    // 合同相关
    Route::get('/pending-contracts', [App\Http\Controllers\MiniController::class, 'getPendingContracts']);
    Route::get('/my-contracts', [App\Http\Controllers\MiniController::class, 'getMyContracts']);
    Route::get('/contracts/{id}', [App\Http\Controllers\MiniController::class, 'getContractDetail']);
    Route::post('/contracts/{id}/sign', [App\Http\Controllers\MiniController::class, 'signContract']);
    Route::post('/contracts/{id}/reject', [App\Http\Controllers\MiniController::class, 'rejectContract']);
    
    // 资料上传相关
    Route::get('/my-documents', [App\Http\Controllers\MiniController::class, 'getMyDocuments']);
    Route::post('/documents/upload', [App\Http\Controllers\MiniController::class, 'uploadDocument']);
    Route::delete('/documents/{documentId}', [App\Http\Controllers\MiniController::class, 'deleteDocument']);
    
    // 入职登记表相关
    Route::get('/onboarding-form', [App\Http\Controllers\MiniController::class, 'getMyOnboardingForm']);
    Route::post('/onboarding-form', [App\Http\Controllers\MiniController::class, 'submitOnboardingForm']);
    Route::post('/upload-signature', [App\Http\Controllers\MiniController::class, 'uploadSignature']); // 上传签名
    Route::post('/upload-photo', [App\Http\Controllers\MiniController::class, 'uploadPhoto']); // 上传一寸照片
    
    // 从业人员登记表相关
    Route::get('/registration-form', [App\Http\Controllers\MiniController::class, 'getMyRegistrationForm']);
    Route::post('/registration-form', [App\Http\Controllers\MiniController::class, 'submitRegistrationForm']);
    
    // 离职证明相关
    Route::get('/my-resignation-certificates', [App\Http\Controllers\MiniController::class, 'getMyResignationCertificates']);
    Route::post('/my-resignation-certificates/upload', [App\Http\Controllers\MiniController::class, 'uploadResignationCertificate']);
    Route::delete('/resignation-certificates/{id}', [App\Http\Controllers\MiniController::class, 'deleteResignationCertificate']);
});

// 地区网页入口管理
Route::middleware('auth:sanctum')->prefix('region-portals')->group(function () {
    Route::get('/', [App\Http\Controllers\RegionPortalController::class, 'index']);
    Route::post('/', [App\Http\Controllers\RegionPortalController::class, 'store']);
    Route::put('/{id}', [App\Http\Controllers\RegionPortalController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\RegionPortalController::class, 'destroy']);
    Route::post('/{id}/toggle-status', [App\Http\Controllers\RegionPortalController::class, 'toggleStatus']);
});

// 财务软件链接管理
Route::middleware('auth:sanctum')->prefix('financial-software-links')->group(function () {
    Route::get('/', [App\Http\Controllers\FinancialSoftwareLinkController::class, 'index']);
    Route::post('/', [App\Http\Controllers\FinancialSoftwareLinkController::class, 'store']);
    Route::put('/{id}', [App\Http\Controllers\FinancialSoftwareLinkController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\FinancialSoftwareLinkController::class, 'destroy']);
});

// 操作日志管理
Route::middleware('auth:sanctum')->prefix('operation-logs')->group(function () {
    Route::get('/', [App\Http\Controllers\OperationLogController::class, 'index']);
    Route::get('/latest', [App\Http\Controllers\OperationLogController::class, 'getLatest']);
});

// 资料交付管理
Route::middleware('auth:sanctum')->group(function () {
    // 项目交付配置
    Route::prefix('delivery-configs')->group(function () {
        Route::get('/check-access', [App\Http\Controllers\ProjectDeliveryConfigController::class, 'checkAccess']);
        Route::get('/', [App\Http\Controllers\ProjectDeliveryConfigController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\ProjectDeliveryConfigController::class, 'show']);
        Route::post('/', [App\Http\Controllers\ProjectDeliveryConfigController::class, 'store']);
        Route::put('/{id}', [App\Http\Controllers\ProjectDeliveryConfigController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\ProjectDeliveryConfigController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [App\Http\Controllers\ProjectDeliveryConfigController::class, 'toggleStatus']);
    });

    // 资料交付记录
    Route::prefix('document-deliveries')->group(function () {
        Route::get('/', [App\Http\Controllers\DocumentDeliveryController::class, 'index']);
        Route::get('/my-pending', [App\Http\Controllers\DocumentDeliveryController::class, 'getMyPending']);
        Route::get('/{id}', [App\Http\Controllers\DocumentDeliveryController::class, 'show']);
        Route::post('/{id}/submit-express', [App\Http\Controllers\DocumentDeliveryController::class, 'submitExpress']);
        Route::post('/{id}/submit-electronic', [App\Http\Controllers\DocumentDeliveryController::class, 'submitElectronic']);
        Route::post('/{id}/mark-completed', [App\Http\Controllers\DocumentDeliveryController::class, 'markAsCompleted']);
        Route::post('/{id}/attachments', [App\Http\Controllers\DocumentDeliveryController::class, 'uploadAttachment']);
        Route::delete('/{deliveryId}/attachments/{attachmentId}', [App\Http\Controllers\DocumentDeliveryController::class, 'deleteAttachment']);
        Route::get('/{deliveryId}/attachments/{attachmentId}/download', [App\Http\Controllers\DocumentDeliveryController::class, 'downloadAttachment']);
    });
});

// 签名和印章管理
Route::middleware('auth:sanctum')->group(function () {
    // 签名管理
    Route::prefix('signatures')->group(function () {
        Route::get('/my', [App\Http\Controllers\SignatureController::class, 'getMySignature']);
        Route::post('/upload', [App\Http\Controllers\SignatureController::class, 'uploadSignature']);
        Route::delete('/', [App\Http\Controllers\SignatureController::class, 'deleteSignature']);
    });
    
    // 印章管理
    Route::prefix('seals')->group(function () {
        Route::get('/my', [App\Http\Controllers\SignatureController::class, 'getMySeals']);
        Route::post('/upload', [App\Http\Controllers\SignatureController::class, 'uploadSeal']);
        Route::post('/{id}/set-default', [App\Http\Controllers\SignatureController::class, 'setDefaultSeal']);
        Route::delete('/{id}', [App\Http\Controllers\SignatureController::class, 'deleteSeal']);
    });

    // 银行付讫章管理
    Route::prefix('bank-stamps')->group(function () {
        Route::get('/my', [App\Http\Controllers\SignatureController::class, 'getMyBankStamp']);
        Route::post('/upload', [App\Http\Controllers\SignatureController::class, 'uploadBankStamp']);
        Route::put('/position', [App\Http\Controllers\SignatureController::class, 'updateBankStampPosition']);
        Route::delete('/', [App\Http\Controllers\SignatureController::class, 'deleteBankStamp']);
    });

    // 付款申请单历史记忆
    Route::prefix('payment-form-history')->group(function () {
        Route::get('/', [App\Http\Controllers\PaymentFormHistoryController::class, 'index']);
        Route::get('/{bankAccount}', [App\Http\Controllers\PaymentFormHistoryController::class, 'show']);
        Route::post('/', [App\Http\Controllers\PaymentFormHistoryController::class, 'store']);
        Route::delete('/{id}', [App\Http\Controllers\PaymentFormHistoryController::class, 'destroy']);
    });

    // 参保增减管理
    Route::prefix('insurance-changes')->group(function () {
        Route::get('/', [App\Http\Controllers\InsuranceChangeController::class, 'index']);
        Route::get('/details', [App\Http\Controllers\InsuranceChangeController::class, 'getDetails']);
        Route::get('/summaries', [App\Http\Controllers\InsuranceChangeController::class, 'getSummaries']);
        Route::post('/export', [App\Http\Controllers\InsuranceChangeController::class, 'export']);
        Route::post('/generate-registration-reports', [App\Http\Controllers\InsuranceChangeController::class, 'generateRegistrationReports']);
        
        // 基数补差相关 - 必须放在 /{id} 之前，否则会被通配路由拦截
        Route::get('/social-security-compensation', [App\Http\Controllers\InsuranceChangeController::class, 'getSocialSecurityCompensation']);
        Route::get('/medical-insurance-compensation', [App\Http\Controllers\InsuranceChangeController::class, 'getMedicalInsuranceCompensation']);
        Route::get('/housing-fund-compensation', [App\Http\Controllers\InsuranceChangeController::class, 'getHousingFundCompensation']);
        Route::post('/trigger-compensation', [App\Http\Controllers\InsuranceChangeController::class, 'triggerCompensation']);
        
        // 通配路由必须放在最后
        Route::get('/{id}', [App\Http\Controllers\InsuranceChangeController::class, 'show']);
        Route::post('/auto-import', [App\Http\Controllers\InsuranceChangeController::class, 'autoImport']);
        Route::post('/{id}/upload-attachment', [App\Http\Controllers\InsuranceChangeController::class, 'uploadAttachment']);
        Route::delete('/attachments/{attachmentId}', [App\Http\Controllers\InsuranceChangeController::class, 'deleteAttachment']);
        Route::post('/{id}/process', [App\Http\Controllers\InsuranceChangeController::class, 'process']);
        Route::put('/{id}/confirm-process', [App\Http\Controllers\InsuranceChangeController::class, 'confirmProcess']);
        Route::put('/{id}/confirm-other-insurance-only', [App\Http\Controllers\InsuranceChangeController::class, 'confirmOtherInsuranceOnly']);
        Route::put('/{id}/update-other-insurance-cost', [App\Http\Controllers\InsuranceChangeController::class, 'updateOtherInsuranceCost']);
        Route::put('/{id}/update-endorsement-number', [App\Http\Controllers\InsuranceChangeController::class, 'updateEndorsementNumber']);
        Route::post('/{id}/update-per-capita-cost', [App\Http\Controllers\InsuranceChangeController::class, 'updatePerCapitaCost']);
        Route::post('/{id}/use-quota', [App\Http\Controllers\InsuranceChangeController::class, 'useQuota']);
        Route::put('/{id}/toggle-large-medical', [App\Http\Controllers\InsuranceChangeController::class, 'toggleLargeMedical']);
        Route::post('/generate-summary', [App\Http\Controllers\InsuranceChangeController::class, 'generateSummary']);
        Route::post('/export-summary', [App\Http\Controllers\InsuranceChangeController::class, 'exportSummary']);
    });

    // 商业险退保流程（A方案：由“减除任务确认处理”自动生成）
    Route::prefix('insurance-surrenders')->group(function () {
        Route::get('/', [InsuranceSurrenderController::class, 'index']);
        Route::post('/', [InsuranceSurrenderController::class, 'store']); // 手动创建退保记录
        Route::get('/policy-statistics', [InsuranceSurrenderController::class, 'getPolicyStatistics']); // 保单统计
        Route::get('/{id}', [InsuranceSurrenderController::class, 'show']);
        Route::post('/{id}/attachments', [InsuranceSurrenderController::class, 'uploadAttachment']);
        Route::post('/{id}/submit-business', [InsuranceSurrenderController::class, 'submitBusiness']);
        Route::post('/{id}/submit-finance', [InsuranceSurrenderController::class, 'submitFinance']);
    });

    // 大额医疗保险管理
    Route::prefix('large-medical-insurance')->group(function () {
        Route::get('/', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'index']);
        Route::post('/', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'store']);
        Route::put('/{id}', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'destroy']);
        Route::get('/regions', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'getRegions']);
        Route::get('/regions/{regionName}', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'getByRegion']);
        Route::post('/batch-set-employees', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'batchSetEmployees']);
        // 生效时间和历史记录相关
        Route::post('/{id}/pending-changes', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'setPendingChanges']);
        Route::delete('/{id}/pending-changes', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'cancelPendingChanges']);
        Route::post('/{id}/apply-now', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'applyPendingChanges']);
        Route::get('/{id}/histories', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'getHistories']);
        Route::get('/histories/all', [App\Http\Controllers\LargeMedicalInsuranceController::class, 'getAllHistories']);
    });

    // 基数调差管理
    Route::prefix('base-adjustments')->group(function () {
        Route::get('/', [App\Http\Controllers\BaseAdjustmentController::class, 'index']);
        Route::get('/check-permission', [App\Http\Controllers\BaseAdjustmentController::class, 'getAdjustStatus']);
        Route::post('/', [App\Http\Controllers\BaseAdjustmentController::class, 'store']);
        Route::delete('/{id}', [App\Http\Controllers\BaseAdjustmentController::class, 'destroy']);
        Route::post('/{id}/apply-now', [App\Http\Controllers\BaseAdjustmentController::class, 'applyNow']);
        Route::get('/employee/{employeeId}/history', [App\Http\Controllers\BaseAdjustmentController::class, 'history']);
        Route::post('/apply-due', [App\Http\Controllers\BaseAdjustmentController::class, 'applyDue']);
    });

    // 考核记录管理
    Route::prefix('assessment-records')->group(function () {
        Route::get('/', [App\Http\Controllers\AssessmentRecordController::class, 'index']);
        Route::get('/statistics', [App\Http\Controllers\AssessmentRecordController::class, 'statistics']);
        Route::put('/{id}/complete', [App\Http\Controllers\AssessmentRecordController::class, 'complete']);
        Route::put('/{id}/remark', [App\Http\Controllers\AssessmentRecordController::class, 'updateRemark']);
        Route::delete('/{id}', [App\Http\Controllers\AssessmentRecordController::class, 'destroy']);
        Route::post('/refresh-status', [App\Http\Controllers\AssessmentRecordController::class, 'refreshStatus']);
        Route::post('/trigger-check', [App\Http\Controllers\AssessmentRecordController::class, 'triggerCheck']);
        Route::post('/check-new-employee-documents', [App\Http\Controllers\AssessmentRecordController::class, 'checkNewEmployeeDocuments']);
        Route::post('/upload-appeal-image', [App\Http\Controllers\AssessmentRecordController::class, 'uploadAppealImage']);
        Route::post('/{id}/appeals', [App\Http\Controllers\AssessmentRecordController::class, 'submitAppeal']);
        Route::get('/{id}/appeals', [App\Http\Controllers\AssessmentRecordController::class, 'getAppeals']);
    });

    // 专项扣除管理
    Route::prefix('special-deductions')->group(function () {
        // 专项扣除项目管理
        Route::get('/items', [SpecialDeductionController::class, 'getDeductionItems']);
        Route::post('/items', [SpecialDeductionController::class, 'createDeductionItem']);
        Route::put('/items/{id}', [SpecialDeductionController::class, 'updateDeductionItem']);
        Route::delete('/items/{id}', [SpecialDeductionController::class, 'deleteDeductionItem']);
        
        // 员工专项扣除管理
        Route::get('/employees', [SpecialDeductionController::class, 'getEmployeeDeductions']);
        Route::get('/employees/project', [SpecialDeductionController::class, 'getProjectEmployees']);
        Route::get('/employees/{employeeId}/detail', [SpecialDeductionController::class, 'getEmployeeDeductionDetail']);
        Route::post('/employees/set', [SpecialDeductionController::class, 'setEmployeeDeduction']);
        Route::post('/employees/batch-set', [SpecialDeductionController::class, 'batchSetEmployeeDeduction']);
        Route::delete('/employees/{id}', [SpecialDeductionController::class, 'deleteEmployeeDeduction']);
    });

    // 工资管理（新版）
    Route::prefix('payroll')->group(function () {
        // 获取可以生成工资表的项目（考勤已审批）
        Route::get('/available-projects', [PayrollController::class, 'getAvailableProjects']);
        // 获取所有项目（带审批状态标识）
        Route::get('/projects-with-approval', [PayrollController::class, 'getProjectsWithApprovalStatus']);
    });
    
    // 工资表备注管理
    Route::prefix('payroll-remarks')->group(function () {
        Route::get('/', [PayrollRemarkController::class, 'index']);  // 获取备注列表
        Route::get('/{id}', [PayrollRemarkController::class, 'show']);  // 获取单个备注
        Route::post('/', [PayrollRemarkController::class, 'store']);  // 创建/更新备注
        Route::delete('/{id}', [PayrollRemarkController::class, 'destroy']);  // 删除备注
        Route::post('/get-by-project-period', [PayrollRemarkController::class, 'getByProjectAndPeriod']);  // 根据项目和期间获取备注
    });
    
    // 工资表管理（旧版）
    Route::prefix('salary-sheets')->group(function () {
        Route::get('/', [SalarySheetController::class, 'index']);
        Route::post('/', [SalarySheetController::class, 'store']);
        Route::get('/{id}', [SalarySheetController::class, 'show']);
        Route::put('/{id}', [SalarySheetController::class, 'update']);
        Route::post('/{id}/submit', [SalarySheetController::class, 'submit']);
        Route::post('/{id}/approve', [SalarySheetController::class, 'approve']);
        Route::post('/{id}/reject', [SalarySheetController::class, 'reject']);
        Route::post('/{id}/salary-data', [SalarySheetController::class, 'saveSalaryData']);
        Route::get('/{id}/export', [SalarySheetController::class, 'export']);
    });

    // 考勤表管理（已审批的考勤表）
    Route::get('/attendance-sheets/approved', [SalarySheetController::class, 'getApprovedAttendanceSheets']);


    // 流程管理
    Route::prefix('process-approvals')->group(function () {
        Route::get('/', [App\Http\Controllers\ProcessApprovalController::class, 'index']);
        Route::post('/', [App\Http\Controllers\ProcessApprovalController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\ProcessApprovalController::class, 'show']);
        Route::post('/{id}/upload-attachment', [App\Http\Controllers\ProcessApprovalController::class, 'uploadAttachment']);
        Route::delete('/{id}/attachments/{attachmentId}', [App\Http\Controllers\ProcessApprovalController::class, 'deleteAttachment']);
        Route::post('/{id}/submit', [App\Http\Controllers\ProcessApprovalController::class, 'submit']);
        Route::post('/{id}/withdraw', [App\Http\Controllers\ProcessApprovalController::class, 'withdraw']); // 撤回审批
        Route::delete('/{id}', [App\Http\Controllers\ProcessApprovalController::class, 'destroy']);
    });

    // 付款申请管理
    Route::prefix('payment-applications')->group(function () {
        Route::get('/', [App\Http\Controllers\PaymentApplicationController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\PaymentApplicationController::class, 'show']);
        Route::post('/{id}/upload-attachment', [App\Http\Controllers\PaymentApplicationController::class, 'uploadAttachment']);
        Route::delete('/{id}/attachments/{attachmentId}', [App\Http\Controllers\PaymentApplicationController::class, 'deleteAttachment']);
        Route::post('/{id}/submit', [App\Http\Controllers\PaymentApplicationController::class, 'submit']);
        Route::post('/{id}/resubmit', [App\Http\Controllers\PaymentApplicationController::class, 'resubmit']); // 重新申请
        Route::put('/{id}/supplement-attachment', [App\Http\Controllers\PaymentApplicationController::class, 'supplementAttachment']); // 确认补传完成
    });

    // 付款申请附件管理（用于补传）
    Route::prefix('payment-request-attachments')->group(function () {
        Route::post('/', [App\Http\Controllers\PaymentRequestAttachmentController::class, 'upload']); // 上传附件
        Route::get('/', [App\Http\Controllers\PaymentRequestAttachmentController::class, 'index']); // 获取附件列表
        Route::delete('/', [App\Http\Controllers\PaymentRequestAttachmentController::class, 'delete']); // 删除附件
        Route::get('/{id}/download', [App\Http\Controllers\PaymentRequestAttachmentController::class, 'download']); // 下载附件
    });

    // 投标项目管理
    Route::prefix('bid-projects')->group(function () {
        Route::get('/', [BidProjectController::class, 'index']);                              // 获取列表
        Route::post('/', [BidProjectController::class, 'store']);                             // 创建项目
        Route::get('/statistics', [BidProjectController::class, 'statistics']);               // 获取统计数据
        Route::get('/categories', [BidProjectController::class, 'categories']);               // 获取类别列表
        Route::get('/{id}', [BidProjectController::class, 'show']);                           // 获取详情
        Route::put('/{id}', [BidProjectController::class, 'update']);                         // 更新项目
        Route::delete('/{id}', [BidProjectController::class, 'destroy']);                     // 删除项目
        Route::post('/{id}/status', [BidProjectController::class, 'updateStatus']);           // 更新状态
        Route::post('/{id}/bid-result', [BidProjectController::class, 'setBidResult']);       // 设置投标结果
        Route::post('/{id}/documents', [BidProjectController::class, 'uploadDocument']);      // 上传文件
        Route::delete('/{id}/documents/{documentId}', [BidProjectController::class, 'deleteDocument']); // 删除文件
        Route::post('/{id}/progress-logs', [BidProjectController::class, 'addProgressLog']);  // 添加进度记录
    });

    // 发票项目配置（只有第2、3、4节点审批人可访问）
    Route::prefix('invoice-projects')->group(function () {
        Route::get('/', [InvoiceProjectController::class, 'index']);                          // 获取列表
        Route::get('/all', [InvoiceProjectController::class, 'all']);                         // 获取所有（下拉选择）
        Route::post('/', [InvoiceProjectController::class, 'store']);                         // 创建项目
        Route::put('/{id}', [InvoiceProjectController::class, 'update']);                     // 更新项目
        Route::delete('/{id}', [InvoiceProjectController::class, 'destroy']);                 // 删除项目
    });

    // 发票申请管理
    Route::prefix('invoice-applications')->group(function () {
        // 权限检查（必须在 {id} 路由之前）
        Route::get('/check-permission/create', [InvoiceApplicationController::class, 'checkCreatePermission']); // 检查创建权限
        
        // 导出Excel
        Route::get('/export-records', [InvoiceApplicationController::class, 'exportInvoiceRecords']); // 导出发票记录
        
        Route::get('/', [InvoiceApplicationController::class, 'index']);                      // 获取列表
        Route::post('/', [InvoiceApplicationController::class, 'store']);                     // 创建申请
        Route::get('/{id}', [InvoiceApplicationController::class, 'show']);                   // 获取详情
        Route::delete('/{id}', [InvoiceApplicationController::class, 'destroy']);             // 删除申请
        Route::put('/{id}/update-project', [InvoiceApplicationController::class, 'updateProject']); // 更新项目名称
        Route::put('/{id}/update-invoice-details', [InvoiceApplicationController::class, 'updateInvoiceDetails']); // 更新开票详情
        
        // 明细项管理
        Route::post('/{id}/items', [InvoiceApplicationController::class, 'addItem']);         // 添加明细
        Route::put('/{id}/items/{itemId}', [InvoiceApplicationController::class, 'updateItem']); // 更新明细
        Route::delete('/{id}/items/{itemId}', [InvoiceApplicationController::class, 'deleteItem']); // 删除明细
        
        // Excel生成
        Route::post('/{id}/generate-excel', [InvoiceApplicationController::class, 'generateExcel']); // 生成Excel
        
        // 附件管理
        Route::post('/{id}/attachments', [InvoiceApplicationController::class, 'uploadAttachment']); // 上传附件
        Route::delete('/{id}/attachments', [InvoiceApplicationController::class, 'deleteAttachment']); // 删除附件
        
        // 审批操作
        Route::post('/{id}/submit', [InvoiceApplicationController::class, 'submit']);         // 提交审批
        Route::post('/{id}/resubmit', [InvoiceApplicationController::class, 'resubmit']);     // 重新提交
    });

    // 开票提醒管理
    Route::prefix('invoice-reminders')->group(function () {
        Route::post('/submit-reason', [App\Http\Controllers\InvoiceReminderController::class, 'submitReason']); // 提交未开票原因
    });

    // 发票汇总管理
    Route::prefix('invoice-summaries')->group(function () {
        Route::get('/', [App\Http\Controllers\InvoiceSummaryController::class, 'index']);                    // 获取列表
        Route::put('/{id}', [App\Http\Controllers\InvoiceSummaryController::class, 'update']);               // 更新记录
        Route::post('/{id}/mark-completed', [App\Http\Controllers\InvoiceSummaryController::class, 'markAsCompleted']); // 标记为已完成
        Route::get('/export', [App\Http\Controllers\InvoiceSummaryController::class, 'export']);             // 导出Excel
    });

    // 报销管理
    Route::prefix('reimbursements')->group(function () {
        Route::get('/', [ReimbursementController::class, 'index']);                           // 获取列表
        Route::post('/', [ReimbursementController::class, 'store']);                          // 创建申请
        Route::get('/{id}', [ReimbursementController::class, 'show']);                        // 获取详情
        Route::delete('/{id}', [ReimbursementController::class, 'destroy']);                  // 删除申请
        
        // 附件管理
        Route::post('/upload-attachment', [ReimbursementController::class, 'uploadAttachment']); // 上传附件
        
        // 完成提交（创建审批流程）
        Route::post('/complete-submission', [ReimbursementController::class, 'completeSubmission']); // 完成提交
    });

    // 差旅申请管理
    Route::prefix('travel-applications')->group(function () {
        Route::get('/', [App\Http\Controllers\TravelApplicationController::class, 'index']);                           // 获取列表
        Route::post('/', [App\Http\Controllers\TravelApplicationController::class, 'store']);                          // 创建申请
        Route::get('/{id}', [App\Http\Controllers\TravelApplicationController::class, 'show']);                        // 获取详情
        Route::delete('/{id}', [App\Http\Controllers\TravelApplicationController::class, 'destroy']);                  // 删除申请
        
        // 附件管理
        Route::post('/upload-attachment', [App\Http\Controllers\TravelApplicationController::class, 'uploadAttachment']); // 上传附件
        
        // 完成提交（创建审批流程）
        Route::post('/complete-submission', [App\Http\Controllers\TravelApplicationController::class, 'completeSubmission']); // 完成提交
    });

    // 人员变动申请管理
    Route::prefix('personnel-change-requests')->group(function () {
        Route::get('/', [App\Http\Controllers\PersonnelChangeRequestController::class, 'index']);                      // 获取列表
        Route::get('/{id}', [App\Http\Controllers\PersonnelChangeRequestController::class, 'show']);                   // 获取详情
        Route::delete('/{id}', [App\Http\Controllers\PersonnelChangeRequestController::class, 'destroy']);             // 删除申请
        
        // 附件管理
        Route::post('/upload-attachment', [App\Http\Controllers\PersonnelChangeRequestController::class, 'uploadAttachment']); // 上传附件
        
        // 完成提交（创建审批流程）- 跳过第一节点
        Route::post('/complete-submission', [App\Http\Controllers\PersonnelChangeRequestController::class, 'completeSubmission']); // 完成提交
    });

    // 发工资表管理
    Route::prefix('salary-payment-records')->group(function () {
        Route::get('/', [SalaryPaymentRecordController::class, 'index']);                         // 获取列表
        Route::post('/generate', [SalaryPaymentRecordController::class, 'generate']);             // 生成发工资表
        Route::post('/export', [SalaryPaymentRecordController::class, 'export']);                 // 导出 Excel
        Route::delete('/{id}', [SalaryPaymentRecordController::class, 'destroy']);                // 删除记录
    });

    // 待办任务管理
    Route::prefix('pending-tasks')->group(function () {
        Route::get('/', [App\Http\Controllers\PendingTaskController::class, 'index']);                    // 获取待办任务列表
        Route::get('/statistics', [App\Http\Controllers\PendingTaskController::class, 'statistics']);     // 获取待办任务统计
        Route::post('/{id}/complete', [App\Http\Controllers\PendingTaskController::class, 'markAsCompleted']); // 标记任务为已完成
    });

    // 开票提醒管理
    Route::prefix('invoice-reminders')->group(function () {
        Route::post('/submit-reason', [App\Http\Controllers\InvoiceReminderController::class, 'submitReason']); // 提交未开票原因
    });

    // 税费申报管理
    Route::prefix('tax-declarations')->group(function () {
        // 税种类目管理
        Route::get('/categories', [App\Http\Controllers\TaxDeclarationController::class, 'getCategories']);
        Route::post('/categories', [App\Http\Controllers\TaxDeclarationController::class, 'storeCategory']);
        Route::put('/categories/{id}', [App\Http\Controllers\TaxDeclarationController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [App\Http\Controllers\TaxDeclarationController::class, 'deleteCategory']);
        
        // 申报配置管理
        Route::get('/configs', [App\Http\Controllers\TaxDeclarationController::class, 'getConfigs']);
        Route::post('/configs', [App\Http\Controllers\TaxDeclarationController::class, 'storeConfig']);
        Route::put('/configs/{id}', [App\Http\Controllers\TaxDeclarationController::class, 'updateConfig']);
        Route::delete('/configs/{id}', [App\Http\Controllers\TaxDeclarationController::class, 'deleteConfig']);
        
        // 申报任务管理
        Route::get('/tasks', [App\Http\Controllers\TaxDeclarationController::class, 'getTasks']);
        Route::get('/tasks/{id}', [App\Http\Controllers\TaxDeclarationController::class, 'getTaskDetail']);
        Route::post('/tasks/{id}/complete', [App\Http\Controllers\TaxDeclarationController::class, 'completeTask']);
        
        // 附件管理
        Route::post('/attachments/upload', [App\Http\Controllers\TaxDeclarationController::class, 'uploadAttachment']);
        Route::delete('/attachments/{id}', [App\Http\Controllers\TaxDeclarationController::class, 'deleteAttachment']);
    });

    // 工伤和商业险理赔
    Route::prefix('insurance-compensation')->group(function () {
        // 理赔记录列表
        Route::get('/', [App\Http\Controllers\InsuranceCompensationController::class, 'index']);
        // 获取可申报工伤的员工
        Route::get('/work-injury-employees', [App\Http\Controllers\InsuranceCompensationController::class, 'getWorkInjuryEmployees']);
        // 获取可申报商业险的员工
        Route::get('/commercial-insurance-employees', [App\Http\Controllers\InsuranceCompensationController::class, 'getCommercialInsuranceEmployees']);
        // 创建理赔记录（步骤1：登记）
        Route::post('/', [App\Http\Controllers\InsuranceCompensationController::class, 'store']);
        // 获取理赔记录详情
        Route::get('/{id}', [App\Http\Controllers\InsuranceCompensationController::class, 'show']);
        // 更新步骤2
        Route::post('/{id}/step2', [App\Http\Controllers\InsuranceCompensationController::class, 'updateStep2']);
        // 更新步骤3
        Route::post('/{id}/step3', [App\Http\Controllers\InsuranceCompensationController::class, 'updateStep3']);
        // 上传附件
        Route::post('/attachments/upload', [App\Http\Controllers\InsuranceCompensationController::class, 'uploadAttachment']);
        // 删除附件
        Route::delete('/attachments/{id}', [App\Http\Controllers\InsuranceCompensationController::class, 'deleteAttachment']);
        // 删除理赔记录
        Route::delete('/{id}', [App\Http\Controllers\InsuranceCompensationController::class, 'destroy']);
    });
});
