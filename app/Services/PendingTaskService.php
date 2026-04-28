<?php

namespace App\Services;

use App\Models\PendingTask;
use App\Models\PaymentRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PendingTaskService
{
    /**
     * 为付款申请创建回执任务
     * 当付款申请审批通过后调用
     */
    public static function createPaymentReceiptTask(PaymentRequest $paymentRequest)
    {
        try {
            // 检查是否需要上传发票
            if (!$paymentRequest->needsInvoiceUpload()) {
                return null;
            }

            // 确定处理人
            $handler = self::getPaymentReceiptHandler($paymentRequest);
            if (!$handler) {
                Log::warning('无法确定付款回执处理人', [
                    'payment_request_id' => $paymentRequest->id,
                    'payment_type' => $paymentRequest->payment_type
                ]);
                return null;
            }

            // 检查是否已存在待处理任务
            $existingTask = PendingTask::where('account_set_id', $paymentRequest->account_set_id)
                ->where('task_type', 'payment_receipt')
                ->where('related_id', $paymentRequest->id)
                ->where('related_type', 'PaymentRequest')
                ->where('status', 'pending')
                ->first();

            if ($existingTask) {
                return $existingTask;
            }

            // 生成任务标题和描述
            $insuranceType = $paymentRequest->getInsuranceType();
            $typeText = $insuranceType === 'social_security' ? '社保' : '公积金';
            
            // 获取月份信息，如果没有月份则使用ID
            $month = $paymentRequest->selected_month;
            if ($month) {
                $title = "{$month} {$typeText}付款申请需要上传发票";
            } else {
                $title = "{$typeText}付款申请（ID:{$paymentRequest->id}）需要上传发票";
            }
            
            $description = "付款申请（{$typeText}，金额：¥{$paymentRequest->amount}）已审批通过，请上传发票。";

            // 创建待办任务
            $task = PendingTask::create([
                'account_set_id' => $paymentRequest->account_set_id,
                'task_type' => 'payment_receipt',
                'title' => $title,
                'description' => $description,
                'related_id' => $paymentRequest->id,
                'related_type' => 'PaymentRequest',
                'handler_id' => $handler->id,
                'handler_name' => $handler->name,
                'status' => 'pending',
                'route_name' => 'payment-requests', // 前端路由名称
                'route_params' => null,
            ]);

            Log::info('创建付款回执待办任务', [
                'task_id' => $task->id,
                'payment_request_id' => $paymentRequest->id,
                'handler_id' => $handler->id
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('创建付款回执待办任务失败', [
                'payment_request_id' => $paymentRequest->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 获取付款回执的处理人
     */
    private static function getPaymentReceiptHandler(PaymentRequest $paymentRequest)
    {
        $insuranceType = $paymentRequest->getInsuranceType();

        if ($insuranceType === 'social_security') {
            // 社保：财务人员
            return User::whereHas('accountSets', function($query) use ($paymentRequest) {
                    $query->where('account_sets.id', $paymentRequest->account_set_id);
                })
                ->where('role', 'finance')
                ->where('is_active', true)
                ->first();
        } elseif ($insuranceType === 'housing_fund') {
            // 公积金：发起人
            return User::find($paymentRequest->submitted_by);
        }

        return null;
    }

    /**
     * 检查并完成付款回执任务
     * 当发票状态变化时调用
     */
    public static function checkAndCompletePaymentReceiptTask(PaymentRequest $paymentRequest)
    {
        // 如果发票已上传或已审批，标记任务为完成
        if (in_array($paymentRequest->invoice_status, ['invoice_uploaded', 'invoice_approved'])) {
            $tasks = PendingTask::where('account_set_id', $paymentRequest->account_set_id)
                ->where('task_type', 'payment_receipt')
                ->where('related_id', $paymentRequest->id)
                ->where('related_type', 'PaymentRequest')
                ->where('status', 'pending')
                ->get();

            foreach ($tasks as $task) {
                $task->markAsCompleted();
                Log::info('付款回执任务已完成', [
                    'task_id' => $task->id,
                    'payment_request_id' => $paymentRequest->id
                ]);
            }
        }
    }

    /**
     * 为线下入职员工创建合同上传任务
     * 当线下入职审批通过后调用
     */
    /**
     * 创建付款申请候补资料待办（仅工资/报销）
     */
    public static function createPaymentSupplementTask(PaymentRequest $paymentRequest)
    {
        try {
            if (
                !$paymentRequest->needsSupplementAttachment() ||
                $paymentRequest->isSupplementExpired()
            ) {
                return null;
            }

            $handler = User::find($paymentRequest->submitted_by);
            if (!$handler) {
                Log::warning('无法确定候补资料待办处理人', [
                    'payment_request_id' => $paymentRequest->id,
                    'submitted_by' => $paymentRequest->submitted_by,
                ]);
                return null;
            }

            $existingTask = PendingTask::where('account_set_id', $paymentRequest->account_set_id)
                ->where('task_type', 'payment_supplement')
                ->where('related_id', $paymentRequest->id)
                ->where('related_type', 'PaymentRequest')
                ->where('handler_id', $handler->id)
                ->where('status', 'pending')
                ->first();

            if ($existingTask) {
                return $existingTask;
            }

            $typeText = self::getSupplementTypeText($paymentRequest);
            $deadline = $paymentRequest->getSupplementDeadlineAt();
            $deadlineText = $deadline ? $deadline->format('Y-m-d H:i') : '72小时内';

            $task = PendingTask::create([
                'account_set_id' => $paymentRequest->account_set_id,
                'task_type' => 'payment_supplement',
                'title' => "{$typeText}付款申请候补资料",
                'description' => "请在 {$deadlineText} 前补充发票或单据附件。",
                'related_id' => $paymentRequest->id,
                'related_type' => 'PaymentRequest',
                'handler_id' => $handler->id,
                'handler_name' => $handler->name,
                'status' => 'pending',
                'route_name' => 'payment-applications',
                'route_params' => null,
            ]);

            Log::info('创建付款申请候补资料待办', [
                'task_id' => $task->id,
                'payment_request_id' => $paymentRequest->id,
                'handler_id' => $handler->id,
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('创建付款申请候补资料待办失败', [
                'payment_request_id' => $paymentRequest->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 完成付款申请候补资料待办
     */
    public static function checkAndCompletePaymentSupplementTask(PaymentRequest $paymentRequest, $force = false)
    {
        $shouldComplete = $force ||
            !$paymentRequest->needsSupplementAttachment() ||
            $paymentRequest->isSupplementExpired();

        if (!$shouldComplete) {
            return 0;
        }

        $tasks = PendingTask::where('account_set_id', $paymentRequest->account_set_id)
            ->where('task_type', 'payment_supplement')
            ->where('related_id', $paymentRequest->id)
            ->where('related_type', 'PaymentRequest')
            ->where('status', 'pending')
            ->get();

        $completedCount = 0;
        foreach ($tasks as $task) {
            $task->markAsCompleted();
            $completedCount++;
        }

        if ($completedCount > 0) {
            Log::info('付款申请候补资料待办已完成', [
                'payment_request_id' => $paymentRequest->id,
                'completed_count' => $completedCount,
            ]);
        }

        return $completedCount;
    }

    private static function getSupplementTypeText(PaymentRequest $paymentRequest)
    {
        if ($paymentRequest->payment_type === 'salary') {
            return '工资';
        }

        return '报销';
    }

    public static function createOfflineContractTask(Employee $employee)
    {
        try {
            // 检查是否是线下入职且未上传合同
            if (!$employee->is_offline_onboarding || $employee->contract_uploaded) {
                return null;
            }

            // 获取账套下的所有业务人员
            $businessUsers = self::getBusinessUsers($employee->account_set_id);
            if ($businessUsers->isEmpty()) {
                Log::warning('未找到业务人员', [
                    'account_set_id' => $employee->account_set_id,
                    'employee_id' => $employee->id
                ]);
                return null;
            }

            $tasks = [];
            foreach ($businessUsers as $user) {
                // 检查是否已存在待处理任务
                $existingTask = PendingTask::where('account_set_id', $employee->account_set_id)
                    ->where('task_type', 'offline_contract')
                    ->where('related_id', $employee->id)
                    ->where('related_type', 'Employee')
                    ->where('handler_id', $user->id)
                    ->where('status', 'pending')
                    ->first();

                if ($existingTask) {
                    $tasks[] = $existingTask;
                    continue;
                }

                // 生成任务标题和描述
                $title = "{$employee->name} 的线下合同需要上传";
                $description = "员工 {$employee->name} 于 {$employee->offline_onboarding_date} 线下入职，请在 {$employee->contract_upload_deadline} 前上传合同。";

                // 创建待办任务
                $task = PendingTask::create([
                    'account_set_id' => $employee->account_set_id,
                    'task_type' => 'offline_contract',
                    'title' => $title,
                    'description' => $description,
                    'related_id' => $employee->id,
                    'related_type' => 'Employee',
                    'handler_id' => $user->id,
                    'handler_name' => $user->name,
                    'status' => 'pending',
                    'route_name' => 'employees', // 前端路由名称
                    'route_params' => null,
                ]);

                $tasks[] = $task;

                Log::info('创建线下合同上传待办任务', [
                    'task_id' => $task->id,
                    'employee_id' => $employee->id,
                    'handler_id' => $user->id
                ]);
            }

            return $tasks;
        } catch (\Exception $e) {
            Log::error('创建线下合同上传待办任务失败', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检查并完成线下合同上传任务
     * 当员工合同上传状态变化时调用
     */
    public static function checkAndCompleteOfflineContractTask(Employee $employee)
    {
        // 如果合同已上传，标记任务为完成
        if ($employee->contract_uploaded) {
            $tasks = PendingTask::where('account_set_id', $employee->account_set_id)
                ->where('task_type', 'offline_contract')
                ->where('related_id', $employee->id)
                ->where('related_type', 'Employee')
                ->where('status', 'pending')
                ->get();

            foreach ($tasks as $task) {
                $task->markAsCompleted();
                Log::info('线下合同上传任务已完成', [
                    'task_id' => $task->id,
                    'employee_id' => $employee->id
                ]);
            }
        }
    }

    /**
     * 获取账套下的所有业务人员
     */
    private static function getBusinessUsers($accountSetId)
    {
        return User::where('role', 'employee')
            ->whereHas('accountSets', function($query) use ($accountSetId) {
                $query->where('account_sets.id', $accountSetId);
            })
            ->where('is_active', true)
            ->get();
    }

    /**
     * 为文档交付创建待办任务
     * 当生成交付记录时调用
     */
    public static function createDocumentDeliveryTask($documentDelivery)
    {
        try {
            // 获取项目信息
            $project = $documentDelivery->project;
            if (!$project) {
                Log::warning('文档交付记录缺少项目信息', [
                    'delivery_id' => $documentDelivery->id
                ]);
                return null;
            }

            // 获取处理人（项目的第一个审批节点账号）
            $handler = self::getProjectOperator($documentDelivery->project_id, $documentDelivery->account_set_id);
            if (!$handler) {
                Log::warning('无法确定文档交付处理人', [
                    'delivery_id' => $documentDelivery->id,
                    'project_id' => $documentDelivery->project_id
                ]);
                return null;
            }

            // 检查是否已存在待处理任务
            $existingTask = PendingTask::where('account_set_id', $documentDelivery->account_set_id)
                ->where('task_type', 'document_delivery')
                ->where('related_id', $documentDelivery->id)
                ->where('related_type', 'DocumentDelivery')
                ->where('status', 'pending')
                ->first();

            if ($existingTask) {
                return $existingTask;
            }

            // 生成任务标题和描述
            $cycleText = $documentDelivery->delivery_cycle === 'monthly' ? '月度' : '季度';
            $title = "{$project->name} {$documentDelivery->delivery_period} {$cycleText}资料交付待处理";
            $description = "项目 {$project->name} 的 {$documentDelivery->delivery_period} {$cycleText}资料需要交付，请及时处理。";

            // 创建待办任务
            $task = PendingTask::create([
                'account_set_id' => $documentDelivery->account_set_id,
                'task_type' => 'document_delivery',
                'title' => $title,
                'description' => $description,
                'related_id' => $documentDelivery->id,
                'related_type' => 'DocumentDelivery',
                'handler_id' => $handler->id,
                'handler_name' => $handler->name,
                'status' => 'pending',
                'route_name' => 'document-deliveries',
                'route_params' => null,
            ]);

            Log::info('创建文档交付待办任务', [
                'task_id' => $task->id,
                'delivery_id' => $documentDelivery->id,
                'handler_id' => $handler->id,
                'project_name' => $project->name
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('创建文档交付待办任务失败', [
                'delivery_id' => $documentDelivery->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检查并完成文档交付任务
     * 当交付记录状态变化时调用
     */
    public static function checkAndCompleteDocumentDeliveryTask($documentDelivery)
    {
        // 如果交付记录已提交或已完成，标记任务为完成
        if (in_array($documentDelivery->status, ['submitted', 'completed'])) {
            $tasks = PendingTask::where('account_set_id', $documentDelivery->account_set_id)
                ->where('task_type', 'document_delivery')
                ->where('related_id', $documentDelivery->id)
                ->where('related_type', 'DocumentDelivery')
                ->where('status', 'pending')
                ->get();

            foreach ($tasks as $task) {
                $task->markAsCompleted();
                Log::info('文档交付任务已完成', [
                    'task_id' => $task->id,
                    'delivery_id' => $documentDelivery->id
                ]);
            }
        }
    }

    /**
     * 获取项目的业务人员（第一个审批节点账号）
     */
    private static function getProjectOperator($projectId, $accountSetId)
    {
        // 从项目所属账套中获取第一个审批人（approval_level 最小的）
        $firstApprover = \DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->whereNotNull('account_set_users.approval_level')
            ->where('users.is_active', true)
            ->orderBy('account_set_users.approval_level')
            ->select('users.*')
            ->first();

        return $firstApprover ? User::find($firstApprover->id) : null;
    }

    /**
     * 为工资依据创建待办任务
     * 每月1日检查上个月的依据是否已上传
     */
    public static function createSalaryBasisTask($accountSetId, $projectId, $month)
    {
        try {
            $project = \App\Models\Project::find($projectId);
            if (!$project || !$project->requires_salary_basis) {
                return null;
            }

            // 检查是否已存在依据记录
            $basisExists = \App\Models\BasisRecord::where('account_set_id', $accountSetId)
                ->where('project_id', $projectId)
                ->where('type', 'salary')
                ->where('month', $month)
                ->exists();

            if ($basisExists) {
                return null; // 已上传，不创建任务
            }

            // 获取账套中第2、3、4审批节点的审批人
            $approvers = self::getApprovers($accountSetId, [2, 3, 4]);
            if ($approvers->isEmpty()) {
                Log::warning('未找到审批人', [
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId
                ]);
                return null;
            }

            $tasks = [];
            foreach ($approvers as $approver) {
                // 检查是否已存在待处理任务
                $existingTask = PendingTask::where('account_set_id', $accountSetId)
                    ->where('task_type', 'salary_basis')
                    ->where('related_id', $projectId)
                    ->where('related_type', 'Project')
                    ->where('handler_id', $approver->id)
                    ->where('status', 'pending')
                    ->where('route_params', 'LIKE', '%' . $month . '%')
                    ->first();

                if ($existingTask) {
                    $tasks[] = $existingTask;
                    continue;
                }

                // 生成任务标题和描述
                $title = "{$project->name} {$month} 工资依据待上传";
                $description = "项目 {$project->name} 的 {$month} 工资依据需要上传，请及时处理。";

                // 创建待办任务
                $task = PendingTask::create([
                    'account_set_id' => $accountSetId,
                    'task_type' => 'salary_basis',
                    'title' => $title,
                    'description' => $description,
                    'related_id' => $projectId,
                    'related_type' => 'Project',
                    'handler_id' => $approver->id,
                    'handler_name' => $approver->name,
                    'status' => 'pending',
                    'route_name' => 'salary-basis',
                    'route_params' => json_encode(['month' => $month, 'project_id' => $projectId]),
                ]);

                $tasks[] = $task;

                Log::info('创建工资依据待办任务', [
                    'task_id' => $task->id,
                    'project_id' => $projectId,
                    'month' => $month,
                    'handler_id' => $approver->id
                ]);
            }

            return $tasks;
        } catch (\Exception $e) {
            Log::error('创建工资依据待办任务失败', [
                'project_id' => $projectId,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 为考勤依据创建待办任务
     * 每月1日检查上个月的依据是否已上传
     */
    public static function createAttendanceBasisTask($accountSetId, $projectId, $month)
    {
        try {
            $project = \App\Models\Project::find($projectId);
            if (!$project || !$project->requires_attendance_basis) {
                return null;
            }

            // 检查是否已存在依据记录
            $basisExists = \App\Models\BasisRecord::where('account_set_id', $accountSetId)
                ->where('project_id', $projectId)
                ->where('type', 'attendance')
                ->where('month', $month)
                ->exists();

            if ($basisExists) {
                return null; // 已上传，不创建任务
            }

            // 获取账套中第2、3、4审批节点的审批人
            $approvers = self::getApprovers($accountSetId, [2, 3, 4]);
            if ($approvers->isEmpty()) {
                Log::warning('未找到审批人', [
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId
                ]);
                return null;
            }

            $tasks = [];
            foreach ($approvers as $approver) {
                // 检查是否已存在待处理任务
                $existingTask = PendingTask::where('account_set_id', $accountSetId)
                    ->where('task_type', 'attendance_basis')
                    ->where('related_id', $projectId)
                    ->where('related_type', 'Project')
                    ->where('handler_id', $approver->id)
                    ->where('status', 'pending')
                    ->where('route_params', 'LIKE', '%' . $month . '%')
                    ->first();

                if ($existingTask) {
                    $tasks[] = $existingTask;
                    continue;
                }

                // 生成任务标题和描述
                $title = "{$project->name} {$month} 考勤依据待上传";
                $description = "项目 {$project->name} 的 {$month} 考勤依据需要上传，请及时处理。";

                // 创建待办任务
                $task = PendingTask::create([
                    'account_set_id' => $accountSetId,
                    'task_type' => 'attendance_basis',
                    'title' => $title,
                    'description' => $description,
                    'related_id' => $projectId,
                    'related_type' => 'Project',
                    'handler_id' => $approver->id,
                    'handler_name' => $approver->name,
                    'status' => 'pending',
                    'route_name' => 'attendance-basis',
                    'route_params' => json_encode(['month' => $month, 'project_id' => $projectId]),
                ]);

                $tasks[] = $task;

                Log::info('创建考勤依据待办任务', [
                    'task_id' => $task->id,
                    'project_id' => $projectId,
                    'month' => $month,
                    'handler_id' => $approver->id
                ]);
            }

            return $tasks;
        } catch (\Exception $e) {
            Log::error('创建考勤依据待办任务失败', [
                'project_id' => $projectId,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检查并完成工资依据任务
     * 当依据上传后调用
     */
    public static function checkAndCompleteSalaryBasisTask($basisRecord)
    {
        if ($basisRecord->type !== 'salary') {
            return;
        }

        // 使用简单的 LIKE 匹配月份值
        $tasks = PendingTask::where('account_set_id', $basisRecord->account_set_id)
            ->where('task_type', 'salary_basis')
            ->where('related_id', $basisRecord->project_id)
            ->where('related_type', 'Project')
            ->where('status', 'pending')
            ->where('route_params', 'LIKE', '%' . $basisRecord->month . '%')
            ->get();

        foreach ($tasks as $task) {
            $task->markAsCompleted();
            Log::info('工资依据任务已完成', [
                'task_id' => $task->id,
                'basis_record_id' => $basisRecord->id
            ]);
        }
    }

    /**
     * 检查并完成考勤依据任务
     * 当依据上传后调用
     */
    public static function checkAndCompleteAttendanceBasisTask($basisRecord)
    {
        if ($basisRecord->type !== 'attendance') {
            return;
        }

        // 使用简单的 LIKE 匹配月份值
        $tasks = PendingTask::where('account_set_id', $basisRecord->account_set_id)
            ->where('task_type', 'attendance_basis')
            ->where('related_id', $basisRecord->project_id)
            ->where('related_type', 'Project')
            ->where('status', 'pending')
            ->where('route_params', 'LIKE', '%' . $basisRecord->month . '%')
            ->get();

        foreach ($tasks as $task) {
            $task->markAsCompleted();
            Log::info('考勤依据任务已完成', [
                'task_id' => $task->id,
                'basis_record_id' => $basisRecord->id
            ]);
        }
    }

    /**
     * 获取账套中指定审批级别的审批人
     */
    private static function getApprovers($accountSetId, $levels = [])
    {
        $query = \DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->whereNotNull('account_set_users.approval_level')
            ->where('users.is_active', true);

        if (!empty($levels)) {
            $query->whereIn('account_set_users.approval_level', $levels);
        }

        $approvers = $query->select('users.*')->get();

        return collect($approvers)->map(function($approver) {
            return User::find($approver->id);
        })->filter();
    }

    /**
     * 为考勤表创建待办任务
     * 每月1日检查上个月的考勤表是否已提交
     */
    public static function createAttendanceSheetTask($accountSetId, $projectId, $month)
    {
        try {
            $project = \App\Models\Project::find($projectId);
            if (!$project) {
                return null;
            }

            // 检查项目是否需要考勤表
            if (!$project->require_attendance) {
                Log::info('项目未开启考勤表功能，跳过', [
                    'project_id' => $projectId,
                    'project_name' => $project->name
                ]);
                return null;
            }

            // 检查是否已存在考勤表
            $sheetExists = \App\Models\AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('project_id', $projectId)
                ->where('month', $month)
                ->whereIn('status', ['submitted', 'approved'])
                ->exists();

            if ($sheetExists) {
                return null; // 已提交或已审批，不创建任务
            }

            // 获取项目的第一个审批节点的人（业务人员）
            $operator = self::getProjectOperator($projectId, $accountSetId);
            if (!$operator) {
                Log::warning('未找到项目业务人员', [
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId
                ]);
                return null;
            }

            // 检查是否已存在待处理任务
            $existingTask = \App\Models\PendingTask::where('account_set_id', $accountSetId)
                ->where('task_type', 'attendance_sheet')
                ->where('related_id', $projectId)
                ->where('related_type', 'Project')
                ->where('handler_id', $operator->id)
                ->where('status', 'pending')
                ->where('route_params', 'LIKE', '%' . $month . '%')
                ->first();

            if ($existingTask) {
                return $existingTask;
            }

            // 生成任务标题和描述
            $title = "{$project->name} {$month} 考勤表待提交";
            $description = "项目 {$project->name} 的 {$month} 考勤表需要提交，请及时处理。";

            // 创建待办任务
            $task = \App\Models\PendingTask::create([
                'account_set_id' => $accountSetId,
                'task_type' => 'attendance_sheet',
                'title' => $title,
                'description' => $description,
                'related_id' => $projectId,
                'related_type' => 'Project',
                'handler_id' => $operator->id,
                'handler_name' => $operator->name,
                'status' => 'pending',
                'route_name' => 'attendance-sheets',
                'route_params' => json_encode(['month' => $month, 'project_id' => $projectId]),
            ]);

            Log::info('创建考勤表待办任务', [
                'task_id' => $task->id,
                'project_id' => $projectId,
                'month' => $month,
                'handler_id' => $operator->id
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('创建考勤表待办任务失败', [
                'project_id' => $projectId,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 为工资表创建待办任务
     * 每月1日检查上个月的工资表是否已提交
     */
    public static function createSalarySheetTask($accountSetId, $projectId, $month)
    {
        try {
            $project = \App\Models\Project::find($projectId);
            if (!$project) {
                return null;
            }

            // 检查是否已存在工资表
            $sheetExists = \App\Models\SalarySheet::where('account_set_id', $accountSetId)
                ->where('project_id', $projectId)
                ->where('month', $month)
                ->whereIn('status', ['submitted', 'approved'])
                ->exists();

            if ($sheetExists) {
                return null; // 已提交或已审批，不创建任务
            }

            // 获取项目的第一个审批节点的人（业务人员）
            $operator = self::getProjectOperator($projectId, $accountSetId);
            if (!$operator) {
                Log::warning('未找到项目业务人员', [
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId
                ]);
                return null;
            }

            // 检查是否已存在待处理任务
            $existingTask = \App\Models\PendingTask::where('account_set_id', $accountSetId)
                ->where('task_type', 'salary_sheet')
                ->where('related_id', $projectId)
                ->where('related_type', 'Project')
                ->where('handler_id', $operator->id)
                ->where('status', 'pending')
                ->where('route_params', 'LIKE', '%' . $month . '%')
                ->first();

            if ($existingTask) {
                return $existingTask;
            }

            // 生成任务标题和描述
            $title = "{$project->name} {$month} 工资表待提交";
            $description = "项目 {$project->name} 的 {$month} 工资表需要提交，请及时处理。";

            // 创建待办任务
            $task = \App\Models\PendingTask::create([
                'account_set_id' => $accountSetId,
                'task_type' => 'salary_sheet',
                'title' => $title,
                'description' => $description,
                'related_id' => $projectId,
                'related_type' => 'Project',
                'handler_id' => $operator->id,
                'handler_name' => $operator->name,
                'status' => 'pending',
                'route_name' => 'salary-sheets',
                'route_params' => json_encode(['month' => $month, 'project_id' => $projectId]),
            ]);

            Log::info('创建工资表待办任务', [
                'task_id' => $task->id,
                'project_id' => $projectId,
                'month' => $month,
                'handler_id' => $operator->id
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('创建工资表待办任务失败', [
                'project_id' => $projectId,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检查并完成考勤表任务
     * 当考勤表审批完成时调用
     */
    public static function checkAndCompleteAttendanceSheetTask($attendanceSheet)
    {
        // 只有审批通过时才完成任务
        if ($attendanceSheet->status !== 'approved') {
            return;
        }

        // 精确匹配：账套ID、任务类型、项目ID、月份
        $tasks = \App\Models\PendingTask::where('account_set_id', $attendanceSheet->account_set_id)
            ->where('task_type', 'attendance_sheet')
            ->where('related_id', $attendanceSheet->project_id)
            ->where('related_type', 'Project')
            ->where('status', 'pending')
            ->get()
            ->filter(function($task) use ($attendanceSheet) {
                // 解析 route_params 中的 month 字段进行精确匹配
                $routeParams = json_decode($task->route_params, true);
                return isset($routeParams['month']) && $routeParams['month'] === $attendanceSheet->month;
            });

        foreach ($tasks as $task) {
            $task->markAsCompleted();
            Log::info('考勤表任务已完成', [
                'task_id' => $task->id,
                'attendance_sheet_id' => $attendanceSheet->id,
                'project_id' => $attendanceSheet->project_id,
                'month' => $attendanceSheet->month
            ]);
        }
    }

    /**
     * 检查并完成工资表任务
     * 当工资表审批完成时调用
     */
    public static function checkAndCompleteSalarySheetTask($salaryApproval)
    {
        // 只有审批通过时才完成任务
        if ($salaryApproval->status !== 'approved') {
            return;
        }

        // 精确匹配：账套ID、任务类型、项目ID、月份
        $tasks = \App\Models\PendingTask::where('account_set_id', $salaryApproval->account_set_id)
            ->where('task_type', 'salary_sheet')
            ->where('related_id', $salaryApproval->project_id)
            ->where('related_type', 'Project')
            ->where('status', 'pending')
            ->get()
            ->filter(function($task) use ($salaryApproval) {
                // 解析 route_params 中的 month 字段进行精确匹配
                $routeParams = json_decode($task->route_params, true);
                return isset($routeParams['month']) && $routeParams['month'] === $salaryApproval->month;
            });

        foreach ($tasks as $task) {
            $task->markAsCompleted();
            Log::info('工资表任务已完成', [
                'task_id' => $task->id,
                'salary_approval_id' => $salaryApproval->id,
                'project_id' => $salaryApproval->project_id,
                'month' => $salaryApproval->month
            ]);
        }
    }

    /**
     * 为税费申报任务创建待办
     */
    public static function createTaxDeclarationTask($task)
    {
        try {
            // 检查是否已存在待处理任务
            $existingTask = \App\Models\PendingTask::where('account_set_id', $task->account_set_id)
                ->where('task_type', 'tax_declaration')
                ->where('related_id', $task->id)
                ->where('related_type', 'TaxDeclarationTask')
                ->where('status', 'pending')
                ->first();

            if ($existingTask) {
                return $existingTask;
            }

            // 生成任务标题和描述
            $title = "{$task->company_name} {$task->declaration_date->format('Y-m-d')} 税费申报待处理";
            $description = "公司 {$task->company_name} 的税费申报任务需要处理，申报日期：{$task->declaration_date->format('Y-m-d')}";

            // 创建待办任务
            $pendingTask = \App\Models\PendingTask::create([
                'account_set_id' => $task->account_set_id,
                'task_type' => 'tax_declaration',
                'title' => $title,
                'description' => $description,
                'related_id' => $task->id,
                'related_type' => 'TaxDeclarationTask',
                'handler_id' => $task->handler_id,
                'handler_name' => $task->handler_name,
                'status' => 'pending',
                'route_name' => 'tax-declarations',
                'route_params' => json_encode(['tab' => 'tasks']),
            ]);

            Log::info('创建税费申报待办任务', [
                'task_id' => $pendingTask->id,
                'declaration_task_id' => $task->id,
                'handler_id' => $task->handler_id
            ]);

            return $pendingTask;
        } catch (\Exception $e) {
            Log::error('创建税费申报待办任务失败', [
                'declaration_task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检查并完成税费申报待办任务
     */
    public static function checkAndCompleteTaxDeclarationTask($task)
    {
        // 如果任务已完成，标记待办为完成
        if ($task->status === 'completed') {
            $tasks = \App\Models\PendingTask::where('account_set_id', $task->account_set_id)
                ->where('task_type', 'tax_declaration')
                ->where('related_id', $task->id)
                ->where('related_type', 'TaxDeclarationTask')
                ->where('status', 'pending')
                ->get();

            foreach ($tasks as $pendingTask) {
                $pendingTask->markAsCompleted();
                Log::info('税费申报待办任务已完成', [
                    'task_id' => $pendingTask->id,
                    'declaration_task_id' => $task->id
                ]);
            }
        }
    }
}
