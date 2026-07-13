<?php

namespace App\Services;

use App\Models\PendingTask;
use App\Models\InvoiceApplication;
use App\Models\PaymentRequest;
use App\Models\Employee;
use App\Models\EmployeeDeductionDetail;
use App\Models\SpecialDeductionItem;
use App\Models\ApprovalFlowConfig;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PendingTaskService
{
    /**
     * 发票申请创建后，创建填写发票信息待办
     */
    public static function createInvoiceFillTask(InvoiceApplication $application)
    {
        try {
            $handler = self::getInvoiceFillHandler($application);
            if (!$handler) {
                Log::warning('无法确定发票填写待办处理人', [
                    'invoice_application_id' => $application->id,
                    'account_set_id' => $application->account_set_id,
                ]);
                return null;
            }

            $title = "{$application->project_name} 发票待开具";
            $description = "发票申请 {$application->application_no} 已创建，请开具并发起审批。";

            $existingTask = PendingTask::where('account_set_id', $application->account_set_id)
                ->where('task_type', 'invoice_fill')
                ->where('related_id', $application->id)
                ->where('related_type', 'InvoiceApplication')
                ->where('status', 'pending')
                ->first();

            if ($existingTask) {
                $updateData = [];
                if ($existingTask->title !== $title) {
                    $updateData['title'] = $title;
                }
                if ($existingTask->description !== $description) {
                    $updateData['description'] = $description;
                }
                if ((int) $existingTask->handler_id !== (int) $handler->id) {
                    $updateData['handler_id'] = $handler->id;
                    $updateData['handler_name'] = $handler->name;
                }

                $routeParams = [
                    'id' => $application->id,
                    'action' => 'fill_invoice_info',
                ];
                if (($existingTask->route_params ?? []) !== $routeParams) {
                    $updateData['route_params'] = json_encode($routeParams);
                }

                if (!empty($updateData)) {
                    $existingTask->update([
                        ...$updateData,
                    ]);
                }

                return $existingTask;
            }

            $task = PendingTask::create([
                'account_set_id' => $application->account_set_id,
                'task_type' => 'invoice_fill',
                'title' => $title,
                'description' => $description,
                'related_id' => $application->id,
                'related_type' => 'InvoiceApplication',
                'handler_id' => $handler->id,
                'handler_name' => $handler->name,
                'status' => 'pending',
                'route_name' => 'invoice-applications',
                'route_params' => json_encode([
                    'id' => $application->id,
                    'action' => 'fill_invoice_info',
                ]),
            ]);

            Log::info('创建发票填写待办任务', [
                'task_id' => $task->id,
                'invoice_application_id' => $application->id,
                'handler_id' => $handler->id,
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('创建发票填写待办任务失败', [
                'invoice_application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public static function getInvoiceFillHandler(InvoiceApplication $application): ?User
    {
        $firstApprover = ApprovalFlowConfig::getFirstEffectiveApprover(
            (int) $application->account_set_id,
            '发票申请',
            ApprovalFlowConfig::MIN_APPROVAL_LEVEL
        );

        return $firstApprover ? User::find($firstApprover->user_id) : null;
    }

    /**
     * 完成发票填写待办
     */
    public static function checkAndCompleteInvoiceFillTask(InvoiceApplication $application)
    {
        if (!$application->is_completed) {
            return 0;
        }

        return self::completeInvoiceFillTask($application);
    }

    public static function completeInvoiceFillTask(InvoiceApplication $application)
    {
        $tasks = PendingTask::where('account_set_id', $application->account_set_id)
            ->where('task_type', 'invoice_fill')
            ->where('related_id', $application->id)
            ->where('related_type', 'InvoiceApplication')
            ->where('status', 'pending')
            ->get();

        $completedCount = 0;
        foreach ($tasks as $task) {
            $task->markAsCompleted();
            $completedCount++;
        }

        if ($completedCount > 0) {
            Log::info('发票填写待办任务已完成', [
                'invoice_application_id' => $application->id,
                'completed_count' => $completedCount,
            ]);
        }

        return $completedCount;
    }

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
            $payload = self::buildDocumentDeliveryTaskPayload($documentDelivery);
            if (!$payload) {
                return null;
            }

            $tasks = self::syncPendingTasksForHandlers([
                'account_set_id' => $documentDelivery->account_set_id,
                'task_type' => 'document_delivery',
                'title' => $payload['title'],
                'description' => $payload['description'],
                'related_id' => $documentDelivery->id,
                'related_type' => 'DocumentDelivery',
                'status' => 'pending',
                'route_name' => 'document-deliveries',
                'route_params' => null,
            ], $payload['handlers']);

            if (empty($tasks)) {
                return null;
            }

            Log::info('创建文档交付待办任务', [
                'delivery_id' => $documentDelivery->id,
                'task_count' => count($tasks),
                'handler_ids' => array_values(array_map(fn ($task) => (int) $task->handler_id, $tasks)),
                'project_name' => $documentDelivery->project->name ?? null
            ]);

            return count($tasks) === 1 ? $tasks[0] : $tasks;
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

    public static function syncDocumentDeliveryTask($documentDelivery)
    {
        if (($documentDelivery->status ?? null) !== 'pending') {
            return null;
        }

        return self::createDocumentDeliveryTask($documentDelivery);
    }

    private static function getProjectRoleHandlers($accountSetId, $projectId, $roleType)
    {
        if (!$projectId) {
            return collect();
        }

        $userIds = app(ProjectRoleUserService::class)->getProjectRoleUserIds(
            (int) $accountSetId,
            (int) $projectId,
            (string) $roleType
        );

        if (empty($userIds)) {
            return collect();
        }

        return User::whereIn('id', $userIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private static function resolveProjectHandlers($accountSetId, $projectId, $roleType, $fallbackHandlers = null)
    {
        return self::getProjectRoleHandlers($accountSetId, $projectId, $roleType);
    }

    private static function normalizeRouteParams($routeParams): ?array
    {
        if (is_string($routeParams)) {
            $decoded = json_decode($routeParams, true);
            $routeParams = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($routeParams) || empty($routeParams)) {
            return null;
        }

        ksort($routeParams);

        return $routeParams;
    }

    private static function routeParamsMatch($currentRouteParams, ?array $targetRouteParams): bool
    {
        return self::normalizeRouteParams($currentRouteParams) === self::normalizeRouteParams($targetRouteParams);
    }

    private static function syncPendingTasksForHandlers(array $baseTaskData, $handlers, ?array $routeParams = null): array
    {
        $handlers = collect($handlers)->filter(function ($handler) {
            return $handler instanceof User;
        })->unique('id')->values();

        $normalizedRouteParams = self::normalizeRouteParams($routeParams);

        $existingTasks = PendingTask::where('account_set_id', $baseTaskData['account_set_id'])
            ->where('task_type', $baseTaskData['task_type'])
            ->where('related_id', $baseTaskData['related_id'])
            ->where('related_type', $baseTaskData['related_type'])
            ->where('status', 'pending')
            ->get()
            ->filter(function (PendingTask $task) use ($normalizedRouteParams) {
                return self::routeParamsMatch($task->route_params, $normalizedRouteParams);
            })
            ->keyBy(function (PendingTask $task) {
                return (int) $task->handler_id;
            });

        if ($handlers->isEmpty()) {
            $existingTasks->each(function (PendingTask $task) {
                $task->delete();
            });

            return [];
        }

        $syncedTasks = [];
        foreach ($handlers as $handler) {
            $taskData = $baseTaskData;
            $taskData['handler_id'] = $handler->id;
            $taskData['handler_name'] = $handler->name;
            $taskData['route_params'] = $normalizedRouteParams;

            $existingTask = $existingTasks->get((int) $handler->id);
            if ($existingTask) {
                $updateData = [];
                foreach (['title', 'description', 'route_name', 'handler_name'] as $field) {
                    if (($existingTask->{$field} ?? null) !== ($taskData[$field] ?? null)) {
                        $updateData[$field] = $taskData[$field] ?? null;
                    }
                }

                if (!self::routeParamsMatch($existingTask->route_params, $normalizedRouteParams)) {
                    $updateData['route_params'] = $normalizedRouteParams;
                }

                if (!empty($updateData)) {
                    $existingTask->update($updateData);
                    $existingTask->refresh();
                }

                $syncedTasks[] = $existingTask;
                continue;
            }

            $syncedTasks[] = PendingTask::create($taskData);
        }

        $activeHandlerIds = $handlers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $existingTasks->each(function (PendingTask $task) use ($activeHandlerIds) {
            if (!in_array((int) $task->handler_id, $activeHandlerIds, true)) {
                $task->delete();
            }
        });

        return $syncedTasks;
    }

    /**
     * 获取项目的业务人员（第一个审批节点账号）
     */
    private static function getProjectOperator($projectId, $accountSetId, $businessType = null)
    {
        $firstApprover = ApprovalFlowConfig::getFirstEffectiveApprover(
            (int) $accountSetId,
            $businessType ? (string) $businessType : null
        );

        return $firstApprover ? User::find($firstApprover->id) : null;
    }

    private static function buildDocumentDeliveryTaskPayload($documentDelivery): ?array
    {
        $documentDelivery->loadMissing('project');

        $project = $documentDelivery->project;
        if (!$project) {
            Log::warning('文档交付记录缺少项目信息', [
                'delivery_id' => $documentDelivery->id
            ]);
            return null;
        }

            $handlers = self::resolveProjectHandlers(
                $documentDelivery->account_set_id,
                $documentDelivery->project_id,
                ProjectRoleUserService::ROLE_DELIVERY,
                [self::getProjectOperator($documentDelivery->project_id, $documentDelivery->account_set_id, 'document_delivery')]
            );
            if ($handlers->isEmpty()) {
                self::syncPendingTasksForHandlers([
                    'account_set_id' => $documentDelivery->account_set_id,
                    'task_type' => 'document_delivery',
                    'title' => '',
                    'description' => '',
                    'related_id' => $documentDelivery->id,
                    'related_type' => 'DocumentDelivery',
                    'status' => 'pending',
                    'route_name' => 'document-deliveries',
                    'route_params' => null,
                ], collect());
            }
            if ($handlers->isEmpty()) {
                Log::warning('无法确定文档交付处理人', [
                    'delivery_id' => $documentDelivery->id,
                'project_id' => $documentDelivery->project_id
            ]);
            return null;
        }

        $cycleText = $documentDelivery->delivery_cycle === 'monthly' ? '月度' : '季度';
        $taskMonth = $documentDelivery->display_month ?: $documentDelivery->delivery_period;

        return [
            'title' => "{$project->name} {$taskMonth} {$cycleText}资料交付待处理",
            'description' => "项目 {$project->name} 的 {$documentDelivery->delivery_period} {$cycleText}资料需要在 {$taskMonth} 完成交付，请及时处理。",
            'handlers' => $handlers,
        ];
    }

    public static function createSpecialDeductionTask($accountSetId, $month)
    {
        try {
            if (!SpecialDeductionItem::where('account_set_id', $accountSetId)
                ->where(function ($query) {
                    $query->where('item_type', 'special')
                        ->orWhereNull('item_type');
                })
                ->where('is_active', true)
                ->exists()) {
                self::completeSpecialDeductionTask($accountSetId, $month);
                return null;
            }

            $activeEmployeeIds = Employee::where('account_set_id', $accountSetId)
                ->where('contract_status', 'active')
                ->pluck('id');

            if ($activeEmployeeIds->isEmpty()) {
                self::completeSpecialDeductionTask($accountSetId, $month);
                return null;
            }

            $configuredCount = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                ->where('month', $month)
                ->where('deduction_type', 'special')
                ->where('is_active', true)
                ->whereNull('project_id')
                ->whereIn('employee_id', $activeEmployeeIds)
                ->distinct('employee_id')
                ->count('employee_id');

            if ($configuredCount >= $activeEmployeeIds->count()) {
                self::completeSpecialDeductionTask($accountSetId, $month);
                return null;
            }

            $operator = self::getProjectOperator(null, $accountSetId, '工资表审批');
            if (!$operator) {
                Log::warning('未找到专项扣除待办处理人', [
                    'account_set_id' => $accountSetId,
                    'month' => $month,
                ]);
                return null;
            }

            $existingTask = PendingTask::where('account_set_id', $accountSetId)
                ->where('task_type', 'special_deduction')
                ->where('related_type', 'AccountSet')
                ->where('handler_id', $operator->id)
                ->where('status', 'pending')
                ->get()
                ->first(function (PendingTask $task) use ($month) {
                    $routeParams = json_decode($task->route_params, true);
                    return is_array($routeParams) && ($routeParams['month'] ?? null) === $month;
                });

            if ($existingTask) {
                return $existingTask;
            }

            return PendingTask::create([
                'account_set_id' => $accountSetId,
                'task_type' => 'special_deduction',
                'title' => "{$month} 专项扣除待设置",
                'description' => "{$month} 在职人员专项扣除金额需要设置，请及时处理。",
                'related_id' => $accountSetId,
                'related_type' => 'AccountSet',
                'handler_id' => $operator->id,
                'handler_name' => $operator->name,
                'status' => 'pending',
                'route_name' => 'employee-special-deductions',
                'route_params' => json_encode(['month' => $month]),
            ]);
        } catch (\Exception $e) {
            Log::error('创建专项扣除待办任务失败', [
                'account_set_id' => $accountSetId,
                'month' => $month,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public static function completeSpecialDeductionTask($accountSetId, $month): void
    {
        PendingTask::where('account_set_id', $accountSetId)
            ->where('task_type', 'special_deduction')
            ->where('related_type', 'AccountSet')
            ->where('status', 'pending')
            ->get()
            ->each(function (PendingTask $task) use ($month) {
                $routeParams = json_decode($task->route_params, true);
                if (is_array($routeParams) && ($routeParams['month'] ?? null) === $month) {
                    $task->markAsCompleted();
                }
            });
    }

    /**
     * 为工资依据创建待办任务
     * 每月1日检查上个月的依据是否已上传
     */
    public static function createSalaryBasisTask($accountSetId, $projectId, $month, $processingMonth = null)
    {
        try {
            $project = \App\Models\Project::find($projectId);
            if (!$project || !$project->requires_salary_basis) {
                return null;
            }

            // 已存在且已上传附件，说明该月工资依据已完成，无需再创建任务
            $basisRecord = \App\Models\BasisRecord::withCount('attachments')
                ->where('account_set_id', $accountSetId)
                ->where('project_id', $projectId)
                ->where('type', 'salary')
                ->where('month', $month)
                ->first();

            if ($basisRecord && (int) ($basisRecord->attachments_count ?? 0) > 0) {
                return null;
            }

            $routeMonth = $processingMonth ?: $month;

            $handlers = self::resolveProjectHandlers(
                $accountSetId,
                $projectId,
                ProjectRoleUserService::ROLE_SALARY,
                self::getBusinessApprovers($accountSetId, '工资表审批')
            );
            if ($handlers->isEmpty()) {
                self::syncPendingTasksForHandlers([
                    'account_set_id' => $accountSetId,
                    'task_type' => 'salary_basis',
                    'title' => '',
                    'description' => '',
                    'related_id' => $projectId,
                    'related_type' => 'Project',
                    'status' => 'pending',
                    'route_name' => 'salary-basis',
                ], collect(), [
                    'month' => $routeMonth,
                    'project_id' => $projectId,
                ]);
            }
            if ($handlers->isEmpty()) {
                Log::warning('未找到审批人', [
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId
                ]);
                return null;
            }

            $tasks = self::syncPendingTasksForHandlers([
                'account_set_id' => $accountSetId,
                'task_type' => 'salary_basis',
                'title' => "{$project->name} {$month} 工资依据待上传",
                'description' => "项目 {$project->name} 的 {$month} 工资依据需要上传，请及时处理。",
                'related_id' => $projectId,
                'related_type' => 'Project',
                'status' => 'pending',
                'route_name' => 'salary-basis',
            ], $handlers, [
                'month' => $routeMonth,
                'project_id' => $projectId,
            ]);

            Log::info('创建工资依据待办任务', [
                'project_id' => $projectId,
                'month' => $month,
                'task_count' => count($tasks),
                'handler_ids' => array_values(array_map(fn ($task) => (int) $task->handler_id, $tasks)),
            ]);

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
    public static function createAttendanceBasisTask($accountSetId, $projectId, $month, $processingMonth = null)
    {
        try {
            $project = \App\Models\Project::find($projectId);
            if (!$project || !$project->requires_attendance_basis) {
                return null;
            }

            // 已存在且已上传附件，说明该月考勤依据已完成，无需再创建任务
            $basisRecord = \App\Models\BasisRecord::withCount('attachments')
                ->where('account_set_id', $accountSetId)
                ->where('project_id', $projectId)
                ->where('type', 'attendance')
                ->where('month', $month)
                ->first();

            if ($basisRecord && (int) ($basisRecord->attachments_count ?? 0) > 0) {
                return null;
            }

            $routeMonth = $processingMonth ?: $month;

            $handlers = self::resolveProjectHandlers(
                $accountSetId,
                $projectId,
                ProjectRoleUserService::ROLE_SALARY,
                self::getBusinessApprovers($accountSetId, '考勤申请')
            );
            if ($handlers->isEmpty()) {
                self::syncPendingTasksForHandlers([
                    'account_set_id' => $accountSetId,
                    'task_type' => 'attendance_basis',
                    'title' => '',
                    'description' => '',
                    'related_id' => $projectId,
                    'related_type' => 'Project',
                    'status' => 'pending',
                    'route_name' => 'attendance-basis',
                ], collect(), [
                    'month' => $routeMonth,
                    'project_id' => $projectId,
                ]);
            }
            if ($handlers->isEmpty()) {
                Log::warning('未找到审批人', [
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId
                ]);
                return null;
            }

            $tasks = self::syncPendingTasksForHandlers([
                'account_set_id' => $accountSetId,
                'task_type' => 'attendance_basis',
                'title' => "{$project->name} {$month} 考勤依据待上传",
                'description' => "项目 {$project->name} 的 {$month} 考勤依据需要上传，请及时处理。",
                'related_id' => $projectId,
                'related_type' => 'Project',
                'status' => 'pending',
                'route_name' => 'attendance-basis',
            ], $handlers, [
                'month' => $routeMonth,
                'project_id' => $projectId,
            ]);

            Log::info('创建考勤依据待办任务', [
                'project_id' => $projectId,
                'month' => $month,
                'task_count' => count($tasks),
                'handler_ids' => array_values(array_map(fn ($task) => (int) $task->handler_id, $tasks)),
            ]);

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

        // 无附件时保持待提交，不关闭任务
        if ((int) $basisRecord->attachments()->count() <= 0) {
            return;
        }

        $project = $basisRecord->project ?: \App\Models\Project::find($basisRecord->project_id);
        $processingMonth = $project
            ? $project->resolveBasisProcessingMonth($basisRecord->month)
            : $basisRecord->month;

        // 待办路由保存的是处理月，依据记录保存的是实际业务月
        $tasks = PendingTask::where('account_set_id', $basisRecord->account_set_id)
            ->where('task_type', 'salary_basis')
            ->where('related_id', $basisRecord->project_id)
            ->where('related_type', 'Project')
            ->where('status', 'pending')
            ->where('route_params', 'LIKE', '%' . $processingMonth . '%')
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

        // 无附件时保持待提交，不关闭任务
        if ((int) $basisRecord->attachments()->count() <= 0) {
            return;
        }

        $project = $basisRecord->project ?: \App\Models\Project::find($basisRecord->project_id);
        $processingMonth = $project
            ? $project->resolveBasisProcessingMonth($basisRecord->month)
            : $basisRecord->month;

        // 待办路由保存的是处理月，依据记录保存的是实际业务月
        $tasks = PendingTask::where('account_set_id', $basisRecord->account_set_id)
            ->where('task_type', 'attendance_basis')
            ->where('related_id', $basisRecord->project_id)
            ->where('related_type', 'Project')
            ->where('status', 'pending')
            ->where('route_params', 'LIKE', '%' . $processingMonth . '%')
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
    private static function getBusinessApprovers($accountSetId, $businessType)
    {
        $approvers = ApprovalFlowConfig::getEnabledApprovers(
            (int) $accountSetId,
            $businessType,
            ApprovalFlowConfig::APPROVER_MIN_LEVEL
        );

        return collect($approvers)->map(function($approver) {
            return User::find($approver->user_id);
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

            $handlers = self::resolveProjectHandlers(
                $accountSetId,
                $projectId,
                ProjectRoleUserService::ROLE_SALARY,
                [self::getProjectOperator($projectId, $accountSetId, '考勤申请')]
            );
            if ($handlers->isEmpty()) {
                self::syncPendingTasksForHandlers([
                    'account_set_id' => $accountSetId,
                    'task_type' => 'attendance_sheet',
                    'title' => '',
                    'description' => '',
                    'related_id' => $projectId,
                    'related_type' => 'Project',
                    'status' => 'pending',
                    'route_name' => 'attendance-sheets',
                ], collect(), [
                    'month' => $month,
                    'project_id' => $projectId,
                ]);
            }
            if ($handlers->isEmpty()) {
                Log::warning('未找到项目业务人员', [
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId
                ]);
                return null;
            }

            $tasks = self::syncPendingTasksForHandlers([
                'account_set_id' => $accountSetId,
                'task_type' => 'attendance_sheet',
                'title' => "{$project->name} {$month} 考勤表待提交",
                'description' => "项目 {$project->name} 的 {$month} 考勤表需要提交，请及时处理。",
                'related_id' => $projectId,
                'related_type' => 'Project',
                'status' => 'pending',
                'route_name' => 'attendance-sheets',
            ], $handlers, [
                'month' => $month,
                'project_id' => $projectId,
            ]);

            Log::info('创建考勤表待办任务', [
                'project_id' => $projectId,
                'month' => $month,
                'task_count' => count($tasks),
                'handler_ids' => array_values(array_map(fn ($task) => (int) $task->handler_id, $tasks)),
            ]);

            if (empty($tasks)) {
                return null;
            }

            return count($tasks) === 1 ? $tasks[0] : $tasks;
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
    public static function createSalarySheetTask($accountSetId, $projectId, $month, $referenceMonth = null)
    {
        try {
            $project = \App\Models\Project::find($projectId);
            if (!$project) {
                return null;
            }

            $hasSalaryHistory = \App\Models\Salary::where('account_set_id', $accountSetId)
                ->where('project_id', $projectId)
                ->exists();

            if (!$project->canCreateSalaryForMonth($month, $hasSalaryHistory, $referenceMonth)) {
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

            $handlers = self::resolveProjectHandlers(
                $accountSetId,
                $projectId,
                ProjectRoleUserService::ROLE_SALARY,
                [self::getProjectOperator($projectId, $accountSetId, '工资表审批')]
            );
            if ($handlers->isEmpty()) {
                self::syncPendingTasksForHandlers([
                    'account_set_id' => $accountSetId,
                    'task_type' => 'salary_sheet',
                    'title' => '',
                    'description' => '',
                    'related_id' => $projectId,
                    'related_type' => 'Project',
                    'status' => 'pending',
                    'route_name' => 'salary-sheets',
                ], collect(), [
                    'month' => $month,
                    'project_id' => $projectId,
                ]);
            }
            if ($handlers->isEmpty()) {
                Log::warning('未找到项目业务人员', [
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId
                ]);
                return null;
            }

            $tasks = self::syncPendingTasksForHandlers([
                'account_set_id' => $accountSetId,
                'task_type' => 'salary_sheet',
                'title' => "{$project->name} {$month} 工资表待提交",
                'description' => "项目 {$project->name} 的 {$month} 工资表需要提交，请及时处理。",
                'related_id' => $projectId,
                'related_type' => 'Project',
                'status' => 'pending',
                'route_name' => 'salary-sheets',
            ], $handlers, [
                'month' => $month,
                'project_id' => $projectId,
            ]);

            Log::info('创建工资表待办任务', [
                'project_id' => $projectId,
                'month' => $month,
                'task_count' => count($tasks),
                'handler_ids' => array_values(array_map(fn ($task) => (int) $task->handler_id, $tasks)),
            ]);

            if (empty($tasks)) {
                return null;
            }

            return count($tasks) === 1 ? $tasks[0] : $tasks;
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
                $routeParams = is_array($task->route_params)
                    ? $task->route_params
                    : json_decode((string) $task->route_params, true);
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
                $routeParams = is_array($task->route_params)
                    ? $task->route_params
                    : json_decode((string) $task->route_params, true);
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
            $title = "{$task->company_name} {$task->declaration_date->format('Y-m')} 税费申报待处理";
            $description = "公司 {$task->company_name} 的税费申报任务需要处理，申报月份：{$task->declaration_date->format('Y-m')}";

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
