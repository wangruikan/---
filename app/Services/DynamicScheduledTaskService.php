<?php

namespace App\Services;

use App\Models\BasisRecord;
use App\Models\PendingTask;
use App\Models\Project;
use App\Models\ProjectDeliveryConfig;
use App\Models\SalaryApproval;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DynamicScheduledTaskService
{
    public function syncForAccountSet($accountSetId, ?string $month = null): void
    {
        $month = $this->normalizeMonth($month);
        if (!$accountSetId || !$month || $this->isFutureMonth($month)) {
            return;
        }

        $accountSetId = (int) $accountSetId;

        $this->syncBasisTasksForReferenceMonth($accountSetId, $month);
        $this->syncSheetTasksForReferenceMonth($accountSetId, $month);
        $this->syncDocumentDeliveries($accountSetId, $month);
        $this->syncSpecialDeductions($accountSetId, $month);
    }

    public function syncBasisTasks($accountSetId, ?string $month = null): void
    {
        $month = $this->normalizeMonth($month);
        if (!$accountSetId || !$month || $this->isFutureMonth($month)) {
            return;
        }

        $this->syncBasisRecords((int) $accountSetId, $month);
    }

    public function syncBasisTasksForReferenceMonth($accountSetId, ?string $month = null): void
    {
        $month = $this->normalizeMonth($month);
        if (!$accountSetId || !$month || $this->isFutureMonth($month)) {
            return;
        }

        $this->syncBasisRecords((int) $accountSetId, $month, $month);
    }

    private function syncBasisRecords(int $accountSetId, string $month, ?string $processingMonth = null): void
    {
        $creatorId = $this->resolveCreatorId($accountSetId);
        if (!$creatorId) {
            Log::warning('动态补齐依据任务失败：未找到账套用户', [
                'account_set_id' => $accountSetId,
                'month' => $month,
            ]);
            return;
        }

        Project::where('account_set_id', $accountSetId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('requires_salary_basis', true)
                    ->orWhere('requires_attendance_basis', true);
            })
            ->get()
            ->each(function (Project $project) use ($accountSetId, $month, $processingMonth, $creatorId) {
                $basisMonth = $processingMonth
                    ? $project->resolveBasisMonth($processingMonth)
                    : $month;

                if (!$project->isPayrollBusinessMonthAvailable($basisMonth, $processingMonth)) {
                    return;
                }

                if ($project->requires_salary_basis) {
                    $this->firstOrCreateBasisRecord(
                        $accountSetId,
                        (int) $project->id,
                        'salary',
                        $basisMonth,
                        '系统自动创建，待上传工资依据附件',
                        $creatorId
                    );

                    if ($processingMonth) {
                        PendingTaskService::createSalaryBasisTask(
                            $accountSetId,
                            $project->id,
                            $basisMonth,
                            $processingMonth
                        );
                    }
                }

                if ($project->requires_attendance_basis) {
                    $this->firstOrCreateBasisRecord(
                        $accountSetId,
                        (int) $project->id,
                        'attendance',
                        $basisMonth,
                        '系统自动创建，待上传考勤依据附件',
                        $creatorId
                    );

                    if ($processingMonth) {
                        PendingTaskService::createAttendanceBasisTask(
                            $accountSetId,
                            $project->id,
                            $basisMonth,
                            $processingMonth
                        );
                    }
                }
            });
    }

    public function syncSheetTasks($accountSetId, ?string $month = null): void
    {
        $month = $this->normalizeMonth($month);
        if (!$accountSetId || !$month || $this->isFutureMonth($month)) {
            return;
        }

        $accountSetId = (int) $accountSetId;
        $salaryHistoryProjectIds = \App\Models\Salary::where('account_set_id', $accountSetId)
            ->distinct()
            ->pluck('project_id')
            ->map(fn ($projectId) => intval($projectId))
            ->toArray();

        Project::where('account_set_id', $accountSetId)
            ->where('status', 'active')
            ->get()
            ->each(function (Project $project) use ($accountSetId, $month, $salaryHistoryProjectIds) {
                if (!$project->isPayrollBusinessMonthAvailable($month)) {
                    return;
                }

                if ($project->require_attendance) {
                    PendingTaskService::createAttendanceSheetTask($accountSetId, $project->id, $month);
                }

                $hasSalaryHistory = in_array((int) $project->id, $salaryHistoryProjectIds, true);
                if (
                    $project->canCreateSalaryForMonth($month, $hasSalaryHistory)
                    && !$this->hasSubmittedSalaryApproval($accountSetId, (int) $project->id, $month)
                ) {
                    PendingTaskService::createSalarySheetTask($accountSetId, $project->id, $month);
                }
            });
    }

    public function syncSheetTasksForReferenceMonth($accountSetId, ?string $month = null): void
    {
        $month = $this->normalizeMonth($month);
        if (!$accountSetId || !$month || $this->isFutureMonth($month)) {
            return;
        }

        $accountSetId = (int) $accountSetId;
        $salaryHistoryProjectIds = \App\Models\Salary::where('account_set_id', $accountSetId)
            ->distinct()
            ->pluck('project_id')
            ->map(fn ($projectId) => intval($projectId))
            ->toArray();

        Project::where('account_set_id', $accountSetId)
            ->where('status', 'active')
            ->get()
            ->each(function (Project $project) use ($accountSetId, $month, $salaryHistoryProjectIds) {
                $businessMonth = $project->resolveBasisMonth($month);
                if (!$project->isPayrollBusinessMonthAvailable($businessMonth, $month)) {
                    return;
                }

                if ($project->require_attendance) {
                    PendingTaskService::createAttendanceSheetTask($accountSetId, $project->id, $businessMonth);
                }

                $hasSalaryHistory = in_array((int) $project->id, $salaryHistoryProjectIds, true);

                if (
                    !$project->canCreateSalaryForMonth($businessMonth, $hasSalaryHistory, $month)
                    || $this->hasSubmittedSalaryApproval($accountSetId, (int) $project->id, $businessMonth)
                ) {
                    return;
                }

                PendingTaskService::createSalarySheetTask($accountSetId, $project->id, $businessMonth, $month);
            });
    }

    public function reconcileProjectTasksForReferenceMonth(
        Project $project,
        ?string $previousSalaryPaymentMonth,
        ?string $month = null
    ): void
    {
        $month = $this->normalizeMonth($month);
        if (!$month || $this->isFutureMonth($month)) {
            return;
        }

        $previousBusinessMonth = $previousSalaryPaymentMonth === 'next'
            ? Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m')
            : $month;
        $currentBusinessMonth = $project->resolveBasisMonth($month);
        $sheetMonths = array_values(array_unique([$previousBusinessMonth, $currentBusinessMonth]));

        PendingTask::where('account_set_id', $project->account_set_id)
            ->where('related_type', 'Project')
            ->where('related_id', $project->id)
            ->where('status', 'pending')
            ->whereIn('task_type', [
                'salary_basis',
                'attendance_basis',
                'salary_sheet',
                'attendance_sheet',
            ])
            ->get()
            ->each(function (PendingTask $task) use ($month, $sheetMonths) {
                $routeParams = is_array($task->route_params) ? $task->route_params : [];
                $routeMonth = $routeParams['month'] ?? null;
                $isBasisTask = in_array($task->task_type, ['salary_basis', 'attendance_basis'], true);

                if (($isBasisTask && $routeMonth === $month)
                    || (!$isBasisTask && in_array($routeMonth, $sheetMonths, true))) {
                    $task->delete();
                }
            });

        $this->syncBasisTasksForReferenceMonth($project->account_set_id, $month);
        $this->syncSheetTasksForReferenceMonth($project->account_set_id, $month);
    }

    public function syncDocumentDeliveries($accountSetId, ?string $month = null): void
    {
        $month = $this->normalizeMonth($month);
        if (!$accountSetId || !$month || $this->isFutureMonth($month)) {
            return;
        }

        $accountSetId = (int) $accountSetId;
        $date = Carbon::createFromFormat('Y-m-d', $month . '-01');
        $deliveryService = app(DocumentDeliveryService::class);

        ProjectDeliveryConfig::with('project')
            ->where('account_set_id', $accountSetId)
            ->where('is_active', true)
            ->whereHas('project', function ($query) {
                $query->where('status', 'active');
            })
            ->get()
            ->each(function (ProjectDeliveryConfig $config) use ($deliveryService, $date) {
                $period = $deliveryService->resolveDeliveryPeriodForDisplayMonth($config, $date->copy());
                if (!$period) {
                    return;
                }

                $delivery = $deliveryService->createDeliveryRecord($config, $period, $date->format('Y-m'));
                $operatorId = $deliveryService->getProjectOperatorId($config->project_id);
                if ($operatorId) {
                    $deliveryService->sendNewPeriodReminder($delivery, $operatorId);
                }
            });
    }

    public function syncSpecialDeductions($accountSetId, ?string $month = null): void
    {
        $month = $this->normalizeMonth($month);
        if (!$accountSetId || !$month || $this->isFutureMonth($month)) {
            return;
        }

        PendingTaskService::createSpecialDeductionTask((int) $accountSetId, $month);
    }

    private function firstOrCreateBasisRecord(
        int $accountSetId,
        int $projectId,
        string $type,
        string $month,
        string $description,
        int $creatorId
    ): BasisRecord {
        $record = BasisRecord::where('account_set_id', $accountSetId)
            ->where('project_id', $projectId)
            ->where('type', $type)
            ->where('month', $month)
            ->first();

        if ($record) {
            return $record;
        }

        return BasisRecord::create([
            'account_set_id' => $accountSetId,
            'project_id' => $projectId,
            'type' => $type,
            'month' => $month,
            'description' => $description,
            'created_by' => $creatorId,
        ]);
    }

    private function resolveCreatorId(int $accountSetId): ?int
    {
        $userId = DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->where('users.is_active', true)
            ->orderByRaw('CASE WHEN account_set_users.approval_level IS NULL THEN 999 ELSE account_set_users.approval_level END ASC')
            ->value('account_set_users.user_id');

        return $userId ? (int) $userId : null;
    }

    private function hasSubmittedSalaryApproval(int $accountSetId, int $projectId, string $month): bool
    {
        return SalaryApproval::where('account_set_id', $accountSetId)
            ->where('project_id', $projectId)
            ->where('month', $month)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
    }

    private function normalizeMonth(?string $month): ?string
    {
        if (!$month) {
            return now('Asia/Shanghai')->format('Y-m');
        }

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : null;
    }

    private function isFutureMonth(string $month): bool
    {
        return $month > now('Asia/Shanghai')->format('Y-m');
    }
}
