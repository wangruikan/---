<?php

namespace App\Http\Controllers;

use App\Models\BasisRecord;
use App\Models\Project;
use App\Models\AttendanceSheet;
use App\Models\Salary;
use App\Models\SalaryApproval;
use App\Models\ApprovalInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Services\DynamicScheduledTaskService;

class PayrollController extends Controller
{
    private const PROGRESS_STATUS_ALL = 'all';
    private const PROGRESS_STATUS_PENDING = 'pending';
    private const PROGRESS_STATUS_COMPLETED = 'completed';

    /**
     * 获取账套ID（优先从请求头/参数获取，其次从用户获取）
     */
    private function getAccountSetId(Request $request)
    {
        return $request->header('X-Account-Set-Id') 
            ?: $request->input('current_account_set_id') 
            ?: Auth::user()->account_set_id;
    }

    private function projectRequiresAttendance(Project $project): bool
    {
        if (!is_null($project->require_attendance)) {
            return (bool) $project->require_attendance;
        }

        if (!is_null($project->requires_attendance)) {
            return (bool) $project->requires_attendance;
        }

        return true;
    }

    private function projectCanCreateSalaryForMonth(Project $project, string $month, bool $hasSalaryHistory): bool
    {
        return $project->canCreateSalaryForMonth($month, $hasSalaryHistory);
    }

    private function projectRequiresSalaryBasis(Project $project): bool
    {
        return (bool) $project->requires_salary_basis;
    }

    private function formatProjectDate($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        return Carbon::parse($date)->format('Y-m-d');
    }

    private function normalizeProgressFilter(?string $status): string
    {
        return match ($status) {
            self::PROGRESS_STATUS_COMPLETED => self::PROGRESS_STATUS_COMPLETED,
            self::PROGRESS_STATUS_PENDING => self::PROGRESS_STATUS_PENDING,
            default => self::PROGRESS_STATUS_ALL,
        };
    }

    private function shouldIncludeProjectForProgress(Project $project, string $month, bool $hasSalaryHistory): bool
    {
        $startMonth = $project->getSalaryStartMonth();
        $endMonth = $project->getSalaryEndMonth();

        if (!$project->isSalaryPeriodReleased($month)) {
            return false;
        }

        if ($startMonth && $month < $startMonth) {
            return false;
        }

        if ($hasSalaryHistory && $endMonth && $month > $endMonth) {
            return false;
        }

        return true;
    }

    private function buildProgressRoute(string $stageKey): string
    {
        return match ($stageKey) {
            'attendance_basis_missing', 'attendance_basis_ready' => '/attendance-basis',
            'attendance_pending_create', 'attendance_draft', 'attendance_submitted' => '/attendance',
            'salary_basis_missing', 'salary_basis_ready' => '/salary-basis',
            'salary_pending_create', 'salary_draft', 'salary_approval_pending', 'salary_approval_rejected', 'completed' => '/salaries',
            default => '/salaries',
        };
    }

    private function buildProgressStep(
        string $stageKey,
        string $label,
        bool $completed,
        array $extra = []
    ): array {
        return array_merge([
            'stage_key' => $stageKey,
            'stage_label' => $label,
            'is_completed' => $completed,
            'route_path' => $this->buildProgressRoute($stageKey),
        ], $extra);
    }

    private function resolveProjectProgress(
        Project $project,
        string $month,
        bool $hasSalaryHistory,
        ?BasisRecord $attendanceBasis,
        ?AttendanceSheet $attendanceSheet,
        ?BasisRecord $salaryBasis,
        ?SalaryApproval $salaryApproval,
        bool $hasSalaryDraft,
        array $approvalFlowMap = []
    ): array {
        $requiresAttendance = $this->projectRequiresAttendance($project);
        $requiresAttendanceBasis = (bool) $project->requires_attendance_basis;
        $requiresSalaryBasis = $this->projectRequiresSalaryBasis($project);

        $attendanceBasisReady = !$requiresAttendanceBasis
            || ($attendanceBasis && $attendanceBasis->attachments_count > 0);
        $salaryBasisReady = !$requiresSalaryBasis
            || ($salaryBasis && $salaryBasis->attachments_count > 0);

        $attendanceStatus = $attendanceSheet?->status;
        $salaryApprovalStatus = $salaryApproval?->status;

        $step = null;
        $progressGroup = self::PROGRESS_STATUS_PENDING;

        if ($requiresAttendance && !$attendanceBasisReady) {
            $step = $this->buildProgressStep(
                'attendance_basis_missing',
                '待上传考勤依据',
                false,
                ['basis_record_id' => $attendanceBasis?->id]
            );
        } elseif ($requiresAttendance && !$attendanceSheet) {
            $step = $this->buildProgressStep('attendance_pending_create', '待制作考勤表', false);
        } elseif ($requiresAttendance && $attendanceStatus === AttendanceSheet::STATUS_DRAFT) {
            $step = $this->buildProgressStep(
                'attendance_draft',
                '考勤表待提交',
                false,
                ['attendance_sheet_id' => $attendanceSheet->id]
            );
        } elseif ($requiresAttendance && $attendanceStatus === AttendanceSheet::STATUS_SUBMITTED) {
            $step = $this->buildProgressStep(
                'attendance_submitted',
                '考勤表待审核',
                false,
                [
                    'attendance_sheet_id' => $attendanceSheet->id,
                    'approval_flow' => $approvalFlowMap['考勤申请:' . $attendanceSheet->id] ?? null,
                ]
            );
        } elseif ($requiresAttendance && $attendanceStatus === AttendanceSheet::STATUS_REJECTED) {
            $step = $this->buildProgressStep(
                'attendance_draft',
                '考勤表已驳回，待处理',
                false,
                [
                    'attendance_sheet_id' => $attendanceSheet->id,
                    'approval_flow' => $approvalFlowMap['考勤申请:' . $attendanceSheet->id] ?? null,
                ]
            );
        } elseif (!$salaryBasisReady) {
            $step = $this->buildProgressStep(
                'salary_basis_missing',
                '待上传工资依据',
                false,
                ['basis_record_id' => $salaryBasis?->id]
            );
        } elseif (!$salaryApproval && !$hasSalaryDraft) {
            $step = $this->buildProgressStep('salary_pending_create', '待生成工资表', false);
        } elseif (!$salaryApproval && $hasSalaryDraft) {
            $step = $this->buildProgressStep('salary_draft', '工资表待提交审批', false);
        } elseif ($salaryApprovalStatus === 'pending') {
            $step = $this->buildProgressStep(
                'salary_approval_pending',
                '工资表待审核',
                false,
                [
                    'salary_approval_id' => $salaryApproval->id,
                    'approval_flow' => $approvalFlowMap['工资表审批:' . $salaryApproval->id] ?? null,
                ]
            );
        } elseif ($salaryApprovalStatus === 'rejected') {
            $step = $this->buildProgressStep(
                'salary_approval_rejected',
                '工资表已驳回，待处理',
                false,
                [
                    'salary_approval_id' => $salaryApproval->id,
                    'approval_flow' => $approvalFlowMap['工资表审批:' . $salaryApproval->id] ?? null,
                ]
            );
        } else {
            $step = $this->buildProgressStep(
                'completed',
                '已完成',
                true,
                [
                    'salary_approval_id' => $salaryApproval?->id,
                    'approval_flow' => $salaryApproval ? ($approvalFlowMap['工资表审批:' . $salaryApproval->id] ?? null) : null,
                ]
            );
            $progressGroup = self::PROGRESS_STATUS_COMPLETED;
        }

        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'project_code' => $project->code,
            'project_status' => $project->status,
            'month' => $month,
            'start_date' => $project->start_date ? Carbon::parse($project->start_date)->format('Y-m-d') : null,
            'end_date' => $project->end_date ? Carbon::parse($project->end_date)->format('Y-m-d') : null,
            'has_salary_history' => $hasSalaryHistory,
            'requires_attendance' => $requiresAttendance,
            'requires_attendance_basis' => $requiresAttendanceBasis,
            'requires_salary_basis' => $requiresSalaryBasis,
            'attendance_basis_id' => $attendanceBasis?->id,
            'attendance_sheet_id' => $attendanceSheet?->id,
            'salary_basis_id' => $salaryBasis?->id,
            'salary_approval_id' => $salaryApproval?->id,
            'has_salary_draft' => $hasSalaryDraft,
            'progress_status' => $progressGroup,
            'current_step' => $step,
        ];
    }

    private function buildApprovalFlowMap($attendanceSheets, $salaryApprovals): array
    {
        $attendanceSheetIds = $attendanceSheets->pluck('id')->filter()->values()->all();
        $salaryApprovalIds = $salaryApprovals->pluck('id')->filter()->values()->all();
        $instances = collect();

        if (!empty($attendanceSheetIds)) {
            $instances = $instances->merge(
                ApprovalInstance::with('records')
                    ->where('business_type', '考勤申请')
                    ->whereIn('business_id', $attendanceSheetIds)
                    ->orderByDesc('id')
                    ->get()
            );
        }

        if (!empty($salaryApprovalIds)) {
            $instances = $instances->merge(
                ApprovalInstance::with('records')
                    ->where('business_type', '工资表审批')
                    ->whereIn('business_id', $salaryApprovalIds)
                    ->orderByDesc('id')
                    ->get()
            );
        }

        return $instances
            ->unique(fn (ApprovalInstance $instance) => $instance->business_type . ':' . $instance->business_id)
            ->mapWithKeys(function (ApprovalInstance $instance) {
                return [$instance->business_type . ':' . $instance->business_id => $this->formatApprovalFlow($instance)];
            })
            ->all();
    }

    private function formatApprovalFlow(ApprovalInstance $instance): array
    {
        $records = $instance->records->values();
        $rejectedIndex = $records->search(fn ($record) => $record->status === 'rejected');
        if ($rejectedIndex !== false) {
            $records = $records->slice(0, $rejectedIndex + 1)->values();
        }

        return [
            'instance_id' => $instance->id,
            'business_type' => $instance->business_type,
            'status' => $instance->status,
            'current_step' => $instance->current_step,
            'total_steps' => $instance->total_steps,
            'nodes' => $records->map(fn ($record) => [
                'id' => $record->id,
                'step_order' => $record->step_order,
                'step_name' => $record->step_name,
                'approver_name' => $record->approver_name,
                'status' => $record->status,
                'approved_at' => optional($record->approved_at)->format('Y-m-d H:i:s'),
                'comment' => $record->comment,
            ])->values()->all(),
        ];
    }

    /**
     * 获取工资流程进度总览
     */
    public function getPayrollProgress(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            $month = $request->input('month', now()->format('Y-m'));
            $progressStatus = $this->normalizeProgressFilter($request->input('progress_status'));
            app(DynamicScheduledTaskService::class)->syncBasisTasks($accountSetId, $month);
            app(DynamicScheduledTaskService::class)->syncSheetTasks($accountSetId, $month);

            $validator = \Validator::make(
                ['month' => $month],
                ['month' => 'required|date_format:Y-m']
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $salaryHistoryProjectIds = Salary::where('account_set_id', $accountSetId)
                ->distinct()
                ->pluck('project_id')
                ->map(fn ($projectId) => intval($projectId))
                ->toArray();

            $projects = Project::where('account_set_id', $accountSetId)
                ->whereIn('status', ['active', 'completed'])
                ->select([
                    'id',
                    'name',
                    'code',
                    'status',
                    'start_date',
                    'end_date',
                    'salary_payment_month',
                    'require_attendance',
                    'requires_attendance',
                    'requires_attendance_basis',
                    'requires_salary_basis',
                ])
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get();

            $attendanceBases = BasisRecord::withCount('attachments')
                ->where('account_set_id', $accountSetId)
                ->where('month', $month)
                ->where('type', 'attendance')
                ->get()
                ->keyBy('project_id');

            $salaryBases = BasisRecord::withCount('attachments')
                ->where('account_set_id', $accountSetId)
                ->where('month', $month)
                ->where('type', 'salary')
                ->get()
                ->keyBy('project_id');

            $attendanceSheets = AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('month', $month)
                ->orderByRaw("FIELD(status, 'submitted', 'draft', 'approved', 'rejected')")
                ->orderByDesc('id')
                ->get()
                ->groupBy('project_id')
                ->map(fn ($items) => $items->first());

            $salaryApprovals = SalaryApproval::where('account_set_id', $accountSetId)
                ->where('month', $month)
                ->orderByRaw("FIELD(status, 'pending', 'rejected', 'approved')")
                ->orderByDesc('id')
                ->get()
                ->groupBy('project_id')
                ->map(fn ($items) => $items->first());

            $salaryDraftProjectIds = Salary::where('account_set_id', $accountSetId)
                ->where('month', $month)
                ->whereNull('salary_approval_id')
                ->pluck('project_id')
                ->map(fn ($projectId) => intval($projectId))
                ->unique()
                ->toArray();

            $approvalFlowMap = $this->buildApprovalFlowMap($attendanceSheets, $salaryApprovals);

            $rows = $projects
                ->filter(function (Project $project) use ($month, $salaryHistoryProjectIds) {
                    $hasSalaryHistory = in_array(intval($project->id), $salaryHistoryProjectIds, true);
                    return $this->shouldIncludeProjectForProgress($project, $month, $hasSalaryHistory);
                })
                ->map(function (Project $project) use (
                    $month,
                    $salaryHistoryProjectIds,
                    $attendanceBases,
                    $attendanceSheets,
                    $salaryBases,
                    $salaryApprovals,
                    $salaryDraftProjectIds,
                    $approvalFlowMap
                ) {
                    $hasSalaryHistory = in_array(intval($project->id), $salaryHistoryProjectIds, true);

                    return $this->resolveProjectProgress(
                        $project,
                        $month,
                        $hasSalaryHistory,
                        $attendanceBases->get($project->id),
                        $attendanceSheets->get($project->id),
                        $salaryBases->get($project->id),
                        $salaryApprovals->get($project->id),
                        in_array(intval($project->id), $salaryDraftProjectIds, true),
                        $approvalFlowMap
                    );
                })
                ->filter(function (array $row) use ($progressStatus) {
                    if ($progressStatus === self::PROGRESS_STATUS_ALL) {
                        return true;
                    }

                    return $row['progress_status'] === $progressStatus;
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $rows,
                'summary' => [
                    'total' => $rows->count(),
                    'completed' => $rows->where('progress_status', self::PROGRESS_STATUS_COMPLETED)->count(),
                    'pending' => $rows->where('progress_status', self::PROGRESS_STATUS_PENDING)->count(),
                ],
                'month' => $month,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取工资流程进度失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取待制作工资表的项目列表
     */
    public function getPendingProjects(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            $month = $request->input('month', now()->format('Y-m'));
            app(DynamicScheduledTaskService::class)->syncBasisTasks($accountSetId, $month);
            app(DynamicScheduledTaskService::class)->syncSheetTasks($accountSetId, $month);

            $validator = \Validator::make(
                ['month' => $month],
                ['month' => 'required|date_format:Y-m']
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $generatedProjectIds = Salary::where('account_set_id', $accountSetId)
                ->where('month', $month)
                ->pluck('project_id')
                ->unique()
                ->toArray();

            $approvedProjectIds = AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('month', $month)
                ->where('status', AttendanceSheet::STATUS_APPROVED)
                ->pluck('project_id')
                ->unique()
                ->toArray();

            $salaryHistoryProjectIds = Salary::where('account_set_id', $accountSetId)
                ->distinct()
                ->pluck('project_id')
                ->map(fn ($projectId) => intval($projectId))
                ->toArray();

            $projects = Project::where('account_set_id', $accountSetId)
                ->where('status', 'active')
                ->select('id', 'name', 'code', 'status', 'start_date', 'end_date', 'salary_payment_month', 'require_attendance', 'requires_attendance', 'requires_salary_basis')
                ->orderBy('name')
                ->get()
                ->filter(function (Project $project) use ($generatedProjectIds, $salaryHistoryProjectIds, $month) {
                    if (in_array($project->id, $generatedProjectIds)) {
                        return false;
                    }

                    $hasSalaryHistory = in_array(intval($project->id), $salaryHistoryProjectIds, true);
                    return $this->projectCanCreateSalaryForMonth($project, $month, $hasSalaryHistory);
                })
                ->map(function (Project $project) use ($accountSetId, $approvedProjectIds, $month, $salaryHistoryProjectIds) {
                    $requireAttendance = $this->projectRequiresAttendance($project);
                    $attendanceApproved = !$requireAttendance || in_array($project->id, $approvedProjectIds);
                    $requiresSalaryBasis = (bool) $project->requires_salary_basis;
                    $hasSalaryHistory = in_array(intval($project->id), $salaryHistoryProjectIds, true);
                    $salaryBasisReady = !$requiresSalaryBasis || BasisRecord::where('account_set_id', $accountSetId)
                        ->where('project_id', $project->id)
                        ->where('month', $month)
                        ->where('type', 'salary')
                        ->whereHas('attachments')
                        ->exists();

                    $disabledReason = null;
                    if (!$attendanceApproved) {
                        $disabledReason = '请先审批本月考勤表';
                    } elseif (!$salaryBasisReady) {
                        $disabledReason = '请先上传本月工资依据';
                    }

                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'code' => $project->code,
                        'start_date' => $this->formatProjectDate($project->start_date),
                        'end_date' => $this->formatProjectDate($project->end_date),
                        'has_salary_history' => $hasSalaryHistory,
                        'month' => $month,
                        'require_attendance' => $requireAttendance,
                        'attendance_approved' => $attendanceApproved,
                        'requires_salary_basis' => $requiresSalaryBasis,
                        'salary_basis_ready' => $salaryBasisReady,
                        'has_generated_salary' => false,
                        'can_create' => $attendanceApproved && $salaryBasisReady,
                        'disabled_reason' => $disabledReason,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $projects,
                'count' => $projects->count(),
                'can_create_count' => $projects->where('can_create', true)->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取待制作工资项目失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取可以生成工资表的项目列表
     * （只返回本月考勤已审批的项目）
     */
    public function getAvailableProjects(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            // 获取期间参数（格式：2025-10）
            $period = $request->input('period');
            
            if (!$period) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择工资期间'
                ], 400);
            }
            
            // 查询该期间考勤已审批的项目ID
            $approvedProjectIds = AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('month', $period)
                ->where('status', AttendanceSheet::STATUS_APPROVED)
                ->pluck('project_id')
                ->toArray();
            
            if (empty($approvedProjectIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => '该期间暂无考勤已审批的项目'
                ]);
            }
            
            // 获取这些项目的详细信息
            $projects = Project::where('account_set_id', $accountSetId)
                ->whereIn('id', $approvedProjectIds)
                ->select('id', 'name', 'code', 'status')
                ->get()
                ->map(function ($project) use ($period) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'code' => $project->code,
                        'status' => $project->status,
                        'period' => $period,
                        'can_create_payroll' => true // 考勤已审批，可以生成工资表
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $projects,
                'count' => $projects->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取可用项目失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取所有项目列表（带考勤审批状态标识）
     * 用于下拉选择时显示哪些可选，哪些不可选
     */
    public function getProjectsWithApprovalStatus(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $period = $request->input('period');
            
            if (!$period) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择工资期间'
                ], 400);
            }
            
            // 调试日志
            \Log::info('获取工资表项目列表', [
                'account_set_id' => $accountSetId,
                'period' => $period
            ]);

            $salaryHistoryProjectIds = Salary::where('account_set_id', $accountSetId)
                ->distinct()
                ->pluck('project_id')
                ->map(fn ($projectId) => intval($projectId))
                ->toArray();
            
            // 获取所有项目（包含是否需要考勤字段）
            $allProjects = Project::where('account_set_id', $accountSetId)
                ->select('id', 'name', 'code', 'status', 'start_date', 'end_date', 'salary_payment_month', 'require_attendance', 'requires_attendance')
                ->get();
            
            \Log::info('查询到的项目数量', ['count' => $allProjects->count()]);
            
            // 获取该期间考勤已审批的项目ID
            $attendanceSheets = AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('month', $period)
                ->get();
            
            // 详细日志：所有考勤表
            \Log::info('该期间的所有考勤表', [
                'period' => $period,
                'account_set_id' => $accountSetId,
                'total_count' => $attendanceSheets->count(),
                'sheets' => $attendanceSheets->map(function($sheet) {
                    return [
                        'id' => $sheet->id,
                        'project_id' => $sheet->project_id,
                        'status' => $sheet->status,
                        'month' => $sheet->month
                    ];
                })->toArray()
            ]);
            
            $approvedProjectIds = $attendanceSheets
                ->where('status', AttendanceSheet::STATUS_APPROVED)
                ->pluck('project_id')
                ->toArray();
            
            \Log::info('考勤已审批的项目ID', [
                'approved_project_ids' => $approvedProjectIds,
                'STATUS_APPROVED' => AttendanceSheet::STATUS_APPROVED
            ]);
            
            // 为每个项目添加考勤审批状态
            $projects = $allProjects
                ->filter(function (Project $project) use ($period, $salaryHistoryProjectIds) {
                    $hasSalaryHistory = in_array(intval($project->id), $salaryHistoryProjectIds, true);
                    return $this->projectCanCreateSalaryForMonth($project, $period, $hasSalaryHistory);
                })
                ->map(function ($project) use ($approvedProjectIds, $period, $salaryHistoryProjectIds) {
                // 判断项目是否需要考勤（兼容两个字段名）
                // 优先使用 require_attendance，如果不存在或为null，则使用 requires_attendance，默认为true
                if (isset($project->require_attendance)) {
                    // 如果 require_attendance 字段存在，使用它的值（0或1转为布尔值）
                    $requireAttendance = (bool) $project->require_attendance;
                } elseif (isset($project->requires_attendance)) {
                    // 如果 requires_attendance 字段存在，使用它的值
                    $requireAttendance = (bool) $project->requires_attendance;
                } else {
                    // 都不存在，默认需要考勤
                    $requireAttendance = true;
                }
                
                // 如果不需要考勤，则直接可以创建工资表
                // 如果需要考勤，则必须考勤已审批才能创建
                $isApproved = in_array($project->id, $approvedProjectIds);
                $canCreate = !$requireAttendance || $isApproved;
                $hasSalaryHistory = in_array(intval($project->id), $salaryHistoryProjectIds, true);
                
                // 生成提示标签
                $label = $project->name;
                if ($requireAttendance && !$isApproved) {
                    $label .= ' (考勤未审批)';
                } elseif (!$requireAttendance) {
                    $label .= ' (无需考勤)';
                }
                
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'status' => $project->status,
                    'start_date' => $this->formatProjectDate($project->start_date),
                    'end_date' => $this->formatProjectDate($project->end_date),
                    'has_salary_history' => $hasSalaryHistory,
                    'period' => $period,
                    'require_attendance' => $requireAttendance,  // 是否需要考勤
                    'attendance_approved' => $isApproved,        // 考勤是否已审批
                    'can_create_payroll' => $canCreate,          // 是否可以生成工资表
                    'disabled' => !$canCreate,                   // 是否禁用（前端使用）
                    'label' => $label                            // 带提示的标签
                ];
                })
                ->values();
            
            // 统计可创建工资表的项目数量
            $canCreateCount = $projects->filter(function ($project) {
                return $project['can_create_payroll'];
            })->count();
            
            // 调试日志
            \Log::info('项目列表处理完成', [
                'total_projects' => $projects->count(),
                'can_create_count' => $canCreateCount,
                'projects' => $projects->toArray()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $projects,
                'approved_count' => count($approvedProjectIds),
                'can_create_count' => $canCreateCount,  // 可创建工资表的项目数量
                'total_count' => $projects->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取项目列表失败: ' . $e->getMessage()
            ], 500);
        }
    }
}

