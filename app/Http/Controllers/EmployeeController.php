<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\OnboardingForm;
use App\Models\EmployeeRegistrationForm;
use App\Models\EmployeeFormUpdateRequest;
use App\Models\OperationLog;
use App\Models\ApprovalInstance;
use App\Models\ProjectDocumentConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\ChecksPermission;

class EmployeeController extends ApiController
{
    use ChecksPermission;
    /**
     * 生成员工工号
     * 格式：项目编号 + 序号（例如：AA001, AB001）
     *
     * @param int $accountSetId 账套ID
     * @param int $projectId 项目ID（可选）
     * @return string 生成的工号
     */
    protected function generateEmployeeNumber($accountSetId, $projectId = null)
    {
        try {
            // 检查数据库中是否有employee_number字段
            if (!Schema::hasColumn('employees', 'employee_number')) {
                // 如果字段不存在，返回一个默认的工号格式
                return "E{$accountSetId}-001";
            }
            
            // 如果提供了项目ID，使用项目编号作为前缀
            if ($projectId) {
                $project = \App\Models\Project::find($projectId);
                if ($project && !empty($project->code)) {
                    $prefix = $project->code;
                    
                    // 查找该项目下最大的员工编号
                    $maxNumber = 0;
                    $employeeNumbers = \App\Models\Employee::where('employee_number', 'like', $prefix . '%')
                        ->where('account_set_id', $accountSetId)
                        ->pluck('employee_number');

                    if ($this->hasProjectEmployeeNumberColumn()) {
                        $projectNumbers = DB::table('employee_projects')
                            ->join('employees', 'employees.id', '=', 'employee_projects.employee_id')
                            ->where('employees.account_set_id', $accountSetId)
                            ->where('employee_projects.project_id', $projectId)
                            ->where('employee_projects.employee_number', 'like', $prefix . '%')
                            ->pluck('employee_projects.employee_number');
                        $employeeNumbers = $employeeNumbers->merge($projectNumbers)->unique()->values();
                    }
                    
                    foreach ($employeeNumbers as $empNumber) {
                        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $empNumber, $matches)) {
                            $number = (int)$matches[1];
                            if ($number > $maxNumber) {
                                $maxNumber = $number;
                            }
                        }
                    }
                    
                    return $prefix . str_pad($maxNumber + 1, 3, '0', STR_PAD_LEFT);
                }
            }
            
            // 如果没有项目ID或项目没有编号，使用旧的格式
            $maxNumber = 0;
            $prefix = "E{$accountSetId}-";
            $employees = \App\Models\Employee::where('employee_number', 'like', $prefix . '%')
                ->where('account_set_id', $accountSetId)
                ->get();
            
            foreach ($employees as $emp) {
                if ($emp->employee_number && preg_match('/' . $prefix . '(\d+)/', $emp->employee_number, $matches)) {
                    $number = (int)$matches[1];
                    if ($number > $maxNumber) {
                        $maxNumber = $number;
                    }
                }
            }
            
            // 生成新的序号（当前最大序号+1）
            $nextNumber = $maxNumber + 1;
            $employeeNumber = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            
            return $employeeNumber;
        } catch (\Exception $e) {
            \Log::error('生成工号失败: ' . $e->getMessage());
            // 如果出错，返回一个基于时间戳的工号
            $timestamp = time() % 10000; // 取时间戳后4位
            return "E{$accountSetId}-{$timestamp}";
        }
    }

    protected function hasProjectEmployeeNumberColumn(): bool
    {
        return Schema::hasColumn('employee_projects', 'employee_number');
    }

    protected function withProjectEmployeeNumber(array $pivotData, ?string $employeeNumber): array
    {
        if ($this->hasProjectEmployeeNumberColumn() && $employeeNumber !== null) {
            $pivotData['employee_number'] = $employeeNumber;
        }

        return $pivotData;
    }

    protected function hasProjectDocumentSetColumn(): bool
    {
        return Schema::hasColumn('employee_projects', 'document_set_id');
    }

    protected function withProjectBindingData(
        array $pivotData,
        ?string $employeeNumber,
        ?int $documentSetId = null,
        bool $forceDocumentSet = false
    ): array {
        $pivotData = $this->withProjectEmployeeNumber($pivotData, $employeeNumber);

        if ($this->hasProjectDocumentSetColumn() && ($documentSetId !== null || $forceDocumentSet)) {
            $pivotData['document_set_id'] = $documentSetId;
        }

        return $pivotData;
    }

    protected function resolveProjectDocumentSetId(?int $projectId, $requestedDocumentSetId = null): ?int
    {
        if (!$projectId || !$this->hasProjectDocumentSetColumn() || !Schema::hasTable('project_document_sets')) {
            return null;
        }

        $query = \App\Models\ProjectDocumentSet::where('project_id', $projectId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');

        if ($requestedDocumentSetId !== null && $requestedDocumentSetId !== '') {
            $documentSet = (clone $query)
                ->where('id', (int) $requestedDocumentSetId)
                ->first();

            if (!$documentSet) {
                throw new \InvalidArgumentException('所选资料方案不属于当前项目');
            }

            return (int) $documentSet->id;
        }

        $defaultSet = (clone $query)->first();

        return $defaultSet ? (int) $defaultSet->id : null;
    }

    protected function syncActiveProjectEmployeeNumber(Employee $employee): void
    {
        if (!$this->hasProjectEmployeeNumberColumn() || empty($employee->employee_number)) {
            return;
        }

        DB::table('employee_projects')
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->update([
                'employee_number' => $employee->employee_number,
                'updated_at' => now(),
            ]);
    }

    protected function applyProjectDisplayFields(Employee $employee, $selectedProjectId = null): void
    {
        $employee->display_contract_status = $employee->contract_status;
        $employee->display_personnel_status = $this->resolveEmployeePersonnelStatus($employee);
        $employee->display_labor_contract_status = $this->resolveEmployeeLaborContractStatus($employee);

        if (!$selectedProjectId) {
            $activeProject = $employee->projects->first(function ($project) {
                return optional($project->pivot)->status === 'active';
            });
            if ($activeProject && $this->hasProjectEmployeeNumberColumn() && !empty($activeProject->pivot->employee_number)) {
                $employee->employee_number = $activeProject->pivot->employee_number;
            }
            return;
        }

        $selectedProject = $employee->projects->first(function ($project) use ($selectedProjectId) {
            return (int) $project->id === (int) $selectedProjectId;
        });

        if (!$selectedProject) {
            return;
        }

        if ($this->hasProjectEmployeeNumberColumn() && !empty($selectedProject->pivot->employee_number)) {
            $employee->employee_number = $selectedProject->pivot->employee_number;
        }

        $displayStatus = optional($selectedProject->pivot)->status === 'active'
            ? $employee->contract_status
            : 'transferred_out';
        $employee->display_contract_status = $displayStatus;
        $employee->display_personnel_status = $displayStatus === 'transferred_out'
            ? 'transferred_out'
            : $this->resolveEmployeePersonnelStatus($employee);
        $employee->display_projects = [$selectedProject];
    }

    protected function resolveEmployeePersonnelStatus(Employee $employee): string
    {
        $personnelStatus = $employee->personnel_status;

        if (in_array($personnelStatus, ['retired', '退休'], true) || $employee->is_retired) {
            return 'retired';
        }

        if (in_array($personnelStatus, ['resigned', '离职'], true)) {
            return 'resigned';
        }

        if (in_array($personnelStatus, ['active', '在职'], true)) {
            return 'active';
        }

        return $employee->contract_status === 'terminated' ? 'resigned' : 'active';
    }

    protected function resolveEmployeeLaborContractStatus(Employee $employee): string
    {
        $latestLaborContract = $employee->latestLaborContract;
        $displayPersonnelStatus = $this->resolveEmployeePersonnelStatus($employee);
        $latestEmployeeContract = $employee->latestEmployeeContract;

        if (
            in_array($displayPersonnelStatus, ['resigned', 'retired'], true)
            && $latestEmployeeContract
            && in_array($latestEmployeeContract->contract_type, ['termination', 'retirement'], true)
        ) {
            $latestLaborContract = $latestEmployeeContract;
        }

        if (!$latestLaborContract) {
            return 'pending_signature';
        }

        if ($latestLaborContract->status === 'employee_signed' && !$latestLaborContract->approval_instance_id) {
            return 'pending_stamp';
        }

        if (!in_array($latestLaborContract->status, ['employee_signed', 'in_approval', 'completed'], true)) {
            return 'pending_signature';
        }

        $sourceType = $latestLaborContract->source_type;

        if (!in_array($sourceType, ['online', 'offline'], true) && $latestLaborContract->approvalInstance) {
            $approvalStampMethod = $latestLaborContract->approvalInstance->stamp_method;
            if (in_array($approvalStampMethod, ['online', 'offline'], true)) {
                $sourceType = $approvalStampMethod;
            }
        }

        if (!in_array($sourceType, ['online', 'offline'], true)) {
            $sourceType = $latestLaborContract->stamp_method;
        }

        return $sourceType === 'offline' ? 'offline' : 'online';
    }

    protected function appendMissingRequiredDocumentData($employees, $selectedProjectId = null): void
    {
        if (!$employees || count($employees) === 0) {
            return;
        }

        $defaultDocumentSetIdCache = [];
        $employeeContexts = [];
        $requiredPairs = [];

        foreach ($employees as $employee) {
            $employee->missing_required_document_names = [];
            $employee->has_missing_required_documents = false;

            [$projectId, $documentSetId] = $this->resolveEmployeeDocumentRequirementContext(
                $employee,
                $selectedProjectId,
                $defaultDocumentSetIdCache
            );

            $employeeContexts[$employee->id] = [$projectId, $documentSetId];

            if ($projectId && $documentSetId) {
                $requiredPairs[$projectId . ':' . $documentSetId] = [
                    'project_id' => $projectId,
                    'document_set_id' => $documentSetId,
                ];
            }
        }

        if (empty($requiredPairs)) {
            return;
        }

        $requiredConfigsQuery = ProjectDocumentConfig::query()
            ->select(['id', 'project_id', 'document_set_id', 'document_name', 'sort_order'])
            ->where('is_required', true)
            ->where(function ($query) use ($requiredPairs) {
                foreach (array_values($requiredPairs) as $index => $pair) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}(function ($subQuery) use ($pair) {
                        $subQuery->where('project_id', $pair['project_id'])
                            ->where('document_set_id', $pair['document_set_id']);
                    });
                }
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');

        $requiredConfigMap = $requiredConfigsQuery->get()->groupBy(function ($config) {
            return (int) $config->project_id . ':' . (int) $config->document_set_id;
        });

        foreach ($employees as $employee) {
            [$projectId, $documentSetId] = $employeeContexts[$employee->id] ?? [null, null];
            if (!$projectId || !$documentSetId) {
                continue;
            }

            $requiredConfigs = $requiredConfigMap->get($projectId . ':' . $documentSetId, collect());
            if ($requiredConfigs->isEmpty()) {
                continue;
            }

            $uploadedConfigIds = collect($employee->documents ?? [])
                ->pluck('document_config_id')
                ->filter()
                ->map(function ($id) {
                    return (int) $id;
                })
                ->unique()
                ->all();

            $missingNames = [];
            foreach ($requiredConfigs as $config) {
                if (!in_array((int) $config->id, $uploadedConfigIds, true)) {
                    $missingNames[] = $config->document_name;
                }
            }

            $employee->missing_required_document_names = array_values(array_unique($missingNames));
            $employee->has_missing_required_documents = !empty($employee->missing_required_document_names);
        }
    }

    protected function resolveEmployeeDocumentRequirementContext(
        Employee $employee,
        $selectedProjectId,
        array &$defaultDocumentSetIdCache
    ): array {
        $project = null;

        if ($selectedProjectId) {
            if (!$employee->relationLoaded('projects')) {
                $employee->load('projects');
            }

            $project = $employee->projects->first(function ($item) use ($selectedProjectId) {
                return (int) $item->id === (int) $selectedProjectId;
            });
        }

        if (!$project) {
            $project = $employee->getCurrentProject();
        }

        if (!$project) {
            return [null, null];
        }

        if ($project->pivot && $project->pivot->document_set_id) {
            return [(int) $project->id, (int) $project->pivot->document_set_id];
        }

        if (!Schema::hasTable('project_document_sets')) {
            return [(int) $project->id, null];
        }

        $projectId = (int) $project->id;
        if (!array_key_exists($projectId, $defaultDocumentSetIdCache)) {
            $defaultDocumentSetIdCache[$projectId] = \App\Models\ProjectDocumentSet::where('project_id', $projectId)
                ->orderByDesc('is_default')
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->value('id');
        }

        return [$projectId, $defaultDocumentSetIdCache[$projectId] ? (int) $defaultDocumentSetIdCache[$projectId] : null];
    }

    protected function applyLaborContractSignFilter($query, ?string $contractSignStatus): void
    {
        if (!$contractSignStatus) {
            return;
        }

        if ($contractSignStatus === 'unsigned') {
            $query->where(function ($contractQuery) {
                $contractQuery->whereDoesntHave('latestLaborContract')
                    ->orWhereHas('latestLaborContract', function ($latestQuery) {
                        $latestQuery->whereNotIn('status', ['employee_signed', 'in_approval', 'completed']);
                    });
            });
            return;
        }

        if ($contractSignStatus === 'pending_stamp') {
            $query->whereHas('latestLaborContract', function ($latestQuery) {
                $latestQuery->where('status', 'employee_signed')
                    ->whereNull('approval_instance_id');
            });
            return;
        }

        if ($contractSignStatus === 'signed') {
            $query->whereHas('latestLaborContract', function ($latestQuery) {
                $latestQuery->where(function ($signedQuery) {
                    $signedQuery->whereIn('status', ['in_approval', 'completed'])
                        ->orWhere(function ($pendingApprovalQuery) {
                            $pendingApprovalQuery->where('status', 'employee_signed')
                                ->whereNotNull('approval_instance_id');
                        });
                });
            });
        }
    }

    protected function applyPersonnelStatusFilter($query, ?string $personnelStatus): void
    {
        if (!$personnelStatus) {
            return;
        }

        if ($personnelStatus === 'active') {
            $this->applyActivePersonnelFilter($query);
            return;
        }

        if ($personnelStatus === 'resigned') {
            $this->applyResignedPersonnelFilter($query);
            return;
        }

        if ($personnelStatus === 'retired') {
            $query->where(function ($q) {
                $q->whereIn('personnel_status', ['retired', '退休'])
                    ->orWhere('is_retired', true);
            });
            return;
        }

        if ($personnelStatus === 'terminated') {
            $query->where('contract_status', 'terminated');
            return;
        }

        $query->where('personnel_status', $personnelStatus);
    }

    protected function applyActivePersonnelFilter($query): void
    {
        $query->where(function ($q) {
            $q->whereIn('personnel_status', ['active', '在职'])
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('personnel_status')
                        ->where(function ($retireQuery) {
                            $retireQuery->whereNull('is_retired')
                                ->orWhere('is_retired', false);
                        })
                        ->where(function ($legacy) {
                            $legacy->whereNull('termination_date')
                                ->where(function ($statusQuery) {
                                    $statusQuery->whereNull('contract_status')
                                        ->orWhereIn('contract_status', ['active', 'expired', 'unsigned']);
                                });
                        });
                });
        });
    }

    protected function applyResignedPersonnelFilter($query): void
    {
        $query->where(function ($q) {
            $q->whereIn('personnel_status', ['resigned', '离职'])
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('personnel_status')
                        ->where('contract_status', 'terminated')
                        ->where(function ($legacy) {
                            $legacy->whereNull('is_retired')
                                ->orWhere('is_retired', false);
                        });
                });
        });
    }

    /**
     * 检查参保日期是否早于项目开始时间
     */
    private function checkInsuranceDateVsProjectStart(array $projectIds, Request $request): ?string
    {
        $insuranceDateFields = [
            'social_insurance_enrollment_date' => '社保参保日期',
            'provident_fund_enrollment_date' => '公积金参保日期',
            'medical_insurance_enrollment_date' => '医保参保日期',
        ];

        $hasInsuranceDate = false;
        foreach (array_keys($insuranceDateFields) as $field) {
            if ($request->filled($field)) {
                $hasInsuranceDate = true;
                break;
            }
        }

        if (!$hasInsuranceDate) {
            return null;
        }

        $earliestStartDate = Project::whereIn('id', $projectIds)->min('start_date');

        if (!$earliestStartDate) {
            return null;
        }

        $earliestStartDate = date('Y-m-d', strtotime($earliestStartDate));

        foreach ($insuranceDateFields as $field => $label) {
            if ($request->filled($field)) {
                $enrollmentDate = date('Y-m-d', strtotime($request->input($field)));
                if ($enrollmentDate < $earliestStartDate) {
                    return "{$label}({$enrollmentDate}) 不可早于项目开始时间({$earliestStartDate})";
                }
            }
        }

        return null;
    }

    /**
     * 检查入职日期、合同开始日期是否早于项目开始时间
     */
    private function checkEmploymentDateVsProjectStart(array $projectIds, Request $request): ?string
    {
        $dateFields = [
            'hire_date' => '入职日期',
            'contract_start_date' => '合同开始日期',
        ];

        $hasDate = false;
        foreach (array_keys($dateFields) as $field) {
            if ($request->filled($field)) {
                $hasDate = true;
                break;
            }
        }

        if (!$hasDate) {
            return null;
        }

        $earliestStartDate = Project::whereIn('id', $projectIds)->min('start_date');
        if (!$earliestStartDate) {
            return null;
        }

        $earliestStartDate = date('Y-m-d', strtotime($earliestStartDate));

        foreach ($dateFields as $field => $label) {
            if ($request->filled($field)) {
                $dateValue = date('Y-m-d', strtotime($request->input($field)));
                if ($dateValue < $earliestStartDate) {
                    return "{$label}({$dateValue}) 不可早于项目开始时间({$earliestStartDate})";
                }
            }
        }

        return null;
    }

    /**
     * 检查保险地区和参保日期是否成对填写
     */
    private function checkInsuranceFieldPairs(Request $request): ?string
    {
        $pairs = [
            ['region' => 'social_security_region_id', 'date' => 'social_insurance_enrollment_date', 'regionLabel' => '社保地区', 'dateLabel' => '社保参保日期'],
            ['region' => 'medical_insurance_region_id', 'date' => 'medical_insurance_enrollment_date', 'regionLabel' => '医保地区', 'dateLabel' => '医保参保日期'],
            ['region' => 'housing_fund_region_id', 'date' => 'provident_fund_enrollment_date', 'regionLabel' => '公积金地区', 'dateLabel' => '公积金参保日期'],
        ];

        foreach ($pairs as $pair) {
            $regionFilled = $request->filled($pair['region']);
            $dateFilled = $request->filled($pair['date']);

            if ($regionFilled && !$dateFilled) {
                return "{$pair['regionLabel']}已填写时，必须同时填写{$pair['dateLabel']}";
            }

            if (!$regionFilled && $dateFilled) {
                return "{$pair['dateLabel']}已填写时，必须同时填写{$pair['regionLabel']}";
            }
        }

        return null;
    }

    private function checkInsuranceRequiredFieldsByRegion(Request $request): ?string
    {
        $groups = [
            [
                'region' => 'social_security_region_id',
                'fields' => [
                    'social_security_base' => '社保基数',
                    'social_insurance_enrollment_date' => '社保参保日期',
                ],
            ],
            [
                'region' => 'medical_insurance_region_id',
                'fields' => [
                    'medical_insurance_base' => '医保基数',
                    'medical_insurance_enrollment_date' => '医保参保日期',
                ],
            ],
            [
                'region' => 'housing_fund_region_id',
                'fields' => [
                    'housing_fund_base' => '公积金基数',
                    'provident_fund_enrollment_date' => '公积金参保日期',
                    'housing_fund_config_id' => '公积金配置',
                ],
            ],
        ];

        foreach ($groups as $group) {
            if (!$request->filled($group['region'])) {
                continue;
            }

            foreach ($group['fields'] as $field => $label) {
                if (!$request->filled($field)) {
                    return "{$label}不能为空";
                }
            }
        }

        return null;
    }

    private function normalizeInsuranceBindingId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && trim($value) === '__NONE__') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function resolveLargeMedicalSyncProject(array $data, ?Employee $employee = null): ?Project
    {
        $projectId = null;

        if (!empty($data['project_ids']) && is_array($data['project_ids'])) {
            $projectId = $data['project_ids'][0] ?? null;
        }

        if (!$projectId && $employee) {
            $projectId = $employee->getCurrentProject()?->id;

            if (!$projectId && !empty($employee->project_ids)) {
                $projectId = is_array($employee->project_ids)
                    ? ($employee->project_ids[0] ?? null)
                    : $employee->project_ids;
            }
        }

        return $projectId ? Project::find($projectId) : null;
    }

    private function resolveLargeMedicalConfigFromMedical(?Project $project, ?int $medicalRegionId, ?Employee $employee = null): ?\App\Models\LargeMedicalInsuranceConfig
    {
        if (!$project || !$medicalRegionId) {
            return null;
        }

        $medicalRegion = \App\Models\MedicalInsuranceRegion::find($medicalRegionId);
        if (!$medicalRegion) {
            return null;
        }

        $regionNames = collect([
            $medicalRegion->name ?? null,
            $medicalRegion->region_name ?? null,
        ])->map(function ($name) {
            return trim((string) $name);
        })->filter()->unique()->values();

        if ($regionNames->isEmpty()) {
            return null;
        }

        $resolvedConfig = $project->getResolvedLargeMedicalInsuranceConfigs()
            ->first(function ($config) use ($regionNames) {
                return $regionNames->contains(trim((string) $config->region_name));
            });

        if ($resolvedConfig) {
            return $resolvedConfig;
        }

        $accountSetId = $employee->account_set_id ?? $project->account_set_id ?? null;
        if (!$accountSetId) {
            return null;
        }

        return \App\Models\LargeMedicalInsuranceConfig::where('account_set_id', $accountSetId)
            ->where('status', 1)
            ->whereIn('region_name', $regionNames->all())
            ->orderBy('id')
            ->first();
    }

    private function syncLargeMedicalWithMedical(array &$data, ?Employee $employee = null): void
    {
        $medicalRegionId = $this->normalizeInsuranceBindingId(
            array_key_exists('medical_insurance_region_id', $data)
                ? $data['medical_insurance_region_id']
                : ($employee->medical_insurance_region_id ?? null)
        );

        if (!$medicalRegionId) {
            $data['large_medical_insurance_config_id'] = null;
            $data['large_medical_enrollment_date'] = null;
            $data['large_medical_base'] = null;
            $data['large_medical_company_base'] = null;
            return;
        }

        $project = $this->resolveLargeMedicalSyncProject($data, $employee);
        $largeMedicalConfig = $this->resolveLargeMedicalConfigFromMedical($project, $medicalRegionId, $employee);

        if (!$largeMedicalConfig) {
            $data['large_medical_insurance_config_id'] = null;
            $data['large_medical_enrollment_date'] = null;
            $data['large_medical_base'] = null;
            $data['large_medical_company_base'] = null;
            return;
        }

        $medicalEnrollmentDate = array_key_exists('medical_insurance_enrollment_date', $data)
            ? $data['medical_insurance_enrollment_date']
            : ($employee->medical_insurance_enrollment_date ?? null);
        $medicalBase = array_key_exists('medical_insurance_base', $data)
            ? $data['medical_insurance_base']
            : ($employee->medical_insurance_base ?? null);

        $data['large_medical_insurance_config_id'] = $largeMedicalConfig->id;
        $data['large_medical_enrollment_date'] = $medicalEnrollmentDate ?: null;

        if ($largeMedicalConfig->calculation_type !== 'base') {
            $data['large_medical_base'] = null;
            $data['large_medical_company_base'] = null;
            return;
        }

        if (($largeMedicalConfig->base_source ?? 'employee') === 'config') {
            $data['large_medical_base'] = $largeMedicalConfig->employee_base_amount ?? $largeMedicalConfig->base_amount;
            $data['large_medical_company_base'] = $largeMedicalConfig->base_amount;
            return;
        }

        $data['large_medical_base'] = ($medicalBase === null || $medicalBase === '') ? null : $medicalBase;
        $data['large_medical_company_base'] = null;
    }

    public function index(Request $request)
    {
        // 人员档案查看权限
        if ($response = $this->checkPermission('employees.view')) {
            return $response;
        }
        
        // 添加详细日志
        \Log::info('员工列表查询开始', [
            'request_params' => $request->all(),
            'user_id' => $request->user()->id ?? 'unknown',
            'current_account_set_id' => $request->input('current_account_set_id')
        ]);
        
        try {
            // 构建缓存键
            $cacheKey = 'employees_' . md5(serialize($request->all()));
            
            // 清除缓存，确保获取最新数据
            Cache::forget($cacheKey);
            
            // 尝试从缓存获取
            $employees = Cache::remember($cacheKey, 0, function() use ($request) {
                try {
                    $query = Employee::with([
                        'projects',
                        'socialSecurityRegion',
                        'medicalInsuranceRegion',
                        'housingFundRegion',
                        'housingFundConfig',
                        'largeMedicalInsuranceConfig',
                        'onboardingForm',
                        'registrationForm',
                        'documents',
                        'latestLaborContract.approvalInstance',
                        'latestEmployeeContract.approvalInstance',
                        'latestTerminationContract'
                    ]);
                    
                    // 【账套过滤】根据当前账套过滤员工
                    $currentAccountSetId = $request->input('current_account_set_id');
                    if ($currentAccountSetId) {
                        $query->where('account_set_id', $currentAccountSetId);
                    } elseif ($request->user()->role !== 'admin') {
                        // 非管理员必须有账套ID，否则返回空
                        $query->whereRaw('1 = 0'); // 返回空结果
                    }
                    
                    $selectedProjectId = $request->input('project_id');

                    // 按项目筛选：包含历史调出人员，展示时再根据 pivot 状态标记“调出”
                    if ($request->has('project_id') && $request->project_id) {
                        $query->whereHas('projects', function($q) use ($request) {
                            $q->where('projects.id', $request->project_id);
                        });
                    }
                    
                    $personnelStatus = $request->input('personnel_status', $request->input('contract_status'));
                    $this->applyPersonnelStatusFilter($query, $personnelStatus);

                    $contractSignStatus = $request->input('contract_sign_status');
                    $this->applyLaborContractSignFilter($query, $contractSignStatus);
                    
                    // 搜索
                    if ($request->has('search') && $request->search) {
                        $search = $request->search;
                        $query->where(function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('id_number', 'like', "%{$search}%")
                              ->orWhere('phone', 'like', "%{$search}%");
                        });
                    }
                    
                    if ($selectedProjectId && $this->hasProjectEmployeeNumberColumn()) {
                        $query->leftJoin('employee_projects as selected_project_pivot', function($join) use ($selectedProjectId) {
                            $join->on('employees.id', '=', 'selected_project_pivot.employee_id')
                                 ->where('selected_project_pivot.project_id', '=', $selectedProjectId);
                        })->select('employees.*')
                          ->orderByRaw('COALESCE(selected_project_pivot.employee_number, employees.employee_number) DESC');
                    } else {
                        $query->orderBy('employees.employee_number', 'desc');
                    }

                    $query->withCount([
                        'laborContracts as completed_labor_contract_count' => function ($contractQuery) {
                            $contractQuery->where('status', 'completed');
                        }
                    ]);

                    $perPage = $request->input('per_page', 10);
                    $result = $query->paginate($perPage);
                    
                    \Log::info('员工查询结果', [
                        'total_count' => $result->total(),
                        'current_page' => $result->currentPage(),
                        'per_page' => $result->perPage(),
                        'sample_employee' => $result->items() ? $result->items()[0] ?? null : null
                    ]);
                    
                    return $result;
                } catch (\Exception $e) {
                    \Log::error('查询员工列表失败: ' . $e->getMessage());
                    // 如果查询失败，返回空集合
                    return new \Illuminate\Pagination\LengthAwarePaginator(
                        [], 0, 10, 1, ['path' => $request->url()]
                    );
                }
            });
            
            // 为每个员工添加 pending_deletion_approval 字段
            $employeeIds = $employees->pluck('id')->toArray();
            $pendingDeletionApprovals = \App\Models\ApprovalInstance::where('business_type', 'employee_deletion')
                ->whereIn('business_id', $employeeIds)
                ->where('status', 'pending')
                ->pluck('business_id')
                ->toArray();
            
            // 为每个员工添加 pending_offline_onboarding 字段
            $pendingOfflineOnboardingApprovals = \App\Models\ApprovalInstance::where('business_type', 'offline_onboarding')
                ->whereIn('business_id', $employeeIds)
                ->where('status', 'pending')
                ->pluck('business_id')
                ->toArray();

            // 为每个员工添加 pending_salary_adjustment_approval 字段
            $pendingSalaryAdjustmentApprovals = \App\Models\ApprovalInstance::where('business_type', 'employee_salary_adjustment')
                ->whereIn('business_id', $employeeIds)
                ->where('status', 'pending')
                ->pluck('business_id')
                ->toArray();

            $pendingFormUpdateEmployeeIds = [];
            if (Schema::hasTable('employee_form_update_requests')) {
                $pendingFormUpdateEmployeeIds = EmployeeFormUpdateRequest::whereIn('employee_id', $employeeIds)
                    ->where('status', 'pending')
                    ->pluck('employee_id')
                    ->toArray();
            }

            foreach ($employees as $employee) {
                $employee->pending_deletion_approval = in_array($employee->id, $pendingDeletionApprovals);
                $employee->pending_offline_onboarding = in_array($employee->id, $pendingOfflineOnboardingApprovals);
                $employee->pending_salary_adjustment_approval = in_array($employee->id, $pendingSalaryAdjustmentApprovals);
                $employee->pending_registration_form_update_approval = in_array($employee->id, $pendingFormUpdateEmployeeIds);
                $this->applyProjectDisplayFields($employee, $request->input('project_id'));
                $employee->largeMedicalStatus = $this->buildLargeMedicalStatusData($employee);
            }

            $this->appendMissingRequiredDocumentData($employees->items(), $request->input('project_id'));
            
            // 计算人员统计数据
            $currentAccountSetId = $request->input('current_account_set_id');
            $stats = $this->getEmployeeStats($currentAccountSetId, $request);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $employees->items(),
                    'total' => $employees->total(),
                    'current_page' => $employees->currentPage(),
                    'per_page' => $employees->perPage(),
                    'stats' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('员工列表异常: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取员工列表失败，请联系管理员',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // 人员档案新增权限
        if ($response = $this->checkPermission('employees.create')) {
            return $response;
        }
        
        // 添加详细日志
        \Log::info('员工创建开始', [
            'request_data' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'id_number' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'gender' => 'required|in:male,female',
            'birth_date' => 'required|date',
            'hire_date' => 'required|date',
            'contract_start_date' => 'required|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'exists:projects,id',
            'project_document_set_id' => 'nullable|integer',
            'remittance_remark' => 'nullable|string|max:255',
            'salary_items' => 'nullable|array',
            'salary_items.*.name' => 'required|string|max:50',
            'salary_items.*.amount' => 'required|numeric|min:0',
            'social_security_base' => 'nullable|numeric|min:0',
            'medical_insurance_base' => 'nullable|numeric|min:0',
            'housing_fund_base' => 'nullable|numeric|min:0',
            'social_security_region_id' => 'nullable|integer|exists:social_security_regions,id',
            'medical_insurance_region_id' => 'nullable|integer|exists:medical_insurance_regions,id',
            'housing_fund_region_id' => 'nullable|integer|exists:housing_fund_regions,id',
            'housing_fund_config_id' => 'nullable|integer|exists:housing_fund_configs,id',
            'social_insurance_enrollment_date' => 'nullable|date',
            'medical_insurance_enrollment_date' => 'nullable|date',
            'provident_fund_enrollment_date' => 'nullable|date',
            'other_insurance_policy_ids' => 'nullable|array',
            'other_insurance_policy_ids.*' => 'integer|exists:other_insurance_policies,id',
        ], [
            'social_security_region_id.required' => '请选择社保参保地区',
            'social_security_region_id.integer' => '社保参保地区格式不正确',
            'social_security_region_id.exists' => '所选社保参保地区不存在',
            'medical_insurance_region_id.required' => '请选择医保参保地区',
            'medical_insurance_region_id.integer' => '医保参保地区格式不正确',
            'medical_insurance_region_id.exists' => '所选医保参保地区不存在',
            'housing_fund_region_id.required' => '请选择公积金参保地区',
            'housing_fund_region_id.integer' => '公积金参保地区格式不正确',
            'housing_fund_region_id.exists' => '所选公积金参保地区不存在',
            'housing_fund_config_id.required' => '请选择公积金配置',
            'housing_fund_config_id.integer' => '公积金配置格式不正确',
            'housing_fund_config_id.exists' => '所选公积金配置不存在',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $message = '验证失败';
            
            // 提供更友好的错误提示
            if ($errors->has('project_ids.0') || $errors->has('project_ids.*')) {
                $projectCount = \App\Models\Project::count();
                if ($projectCount === 0) {
                    $message = '系统中还没有项目数据，请先创建项目';
                } else {
                    $requestedIds = $request->project_ids ?? [];
                    $existingIds = \App\Models\Project::whereIn('id', $requestedIds)->pluck('id')->toArray();
                    $invalidIds = array_diff($requestedIds, $existingIds);
                    $message = '以下项目ID不存在: ' . implode(', ', $invalidIds);
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors
            ], 422);
        }

        // 【黑名单检查】检查身份证号是否在黑名单中
        if ($request->filled('id_number')) {
            $blacklistInfo = \App\Models\Blacklist::getBlacklistInfo($request->id_number);
            if ($blacklistInfo) {
                return response()->json([
                    'success' => false,
                    'message' => '该人员已被加入黑名单，不允许办理入职',
                    'is_blacklisted' => true,
                    'blacklist_info' => [
                        'name' => $blacklistInfo->name,
                        'reason' => $blacklistInfo->reason,
                        'created_at' => $blacklistInfo->created_at
                    ]
                ], 403);
            }
        }

        // 检查同一项目是否重复录入（如果有项目关联）
        if ($request->has('project_ids') && is_array($request->project_ids)) {
            foreach ($request->project_ids as $projectId) {
                $existingEmployee = Employee::where('id_number', $request->id_number)
                    ->whereHas('projects', function($q) use ($projectId) {
                        $q->where('project_id', $projectId);
                    })->first();
                    
                if ($existingEmployee) {
                    return response()->json([
                        'success' => false,
                        'message' => "身份证号 {$request->id_number} 在项目 {$projectId} 中已存在"
                    ], 422);
                }
            }
        }

        if ($request->has('project_ids') && is_array($request->project_ids)) {
            $employmentDateError = $this->checkEmploymentDateVsProjectStart($request->project_ids, $request);
            if ($employmentDateError) {
                return response()->json([
                    'success' => false,
                    'message' => $employmentDateError
                ], 422);
            }

            $insuranceFieldError = $this->checkInsuranceFieldPairs($request);
            if ($insuranceFieldError) {
                return response()->json([
                    'success' => false,
                    'message' => $insuranceFieldError
                ], 422);
            }

            $insuranceRequiredError = $this->checkInsuranceRequiredFieldsByRegion($request);
            if ($insuranceRequiredError) {
                return response()->json([
                    'success' => false,
                    'message' => $insuranceRequiredError
                ], 422);
            }
        }

        // 检查参保时间不可早于项目开始时间
        if ($request->has('project_ids') && is_array($request->project_ids)) {
            $insuranceDateError = $this->checkInsuranceDateVsProjectStart($request->project_ids, $request);
            if ($insuranceDateError) {
                return response()->json([
                    'success' => false,
                    'message' => $insuranceDateError
                ], 422);
            }
        }

        $resolvedDocumentSetId = null;
        if ($request->has('project_ids') && is_array($request->project_ids) && !empty($request->project_ids)) {
            try {
                $resolvedDocumentSetId = $this->resolveProjectDocumentSetId(
                    (int) $request->project_ids[0],
                    $request->input('project_document_set_id')
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }
        }

        // 处理日期字段格式 - 包含所有新增字段
        $employeeData = $request->only([
            // 基础信息
            'name', 'employee_number', 'position', 'id_number', 'phone', 'email', 'gender', 'birth_date',
            'nationality', 'marital_status', 'education', 'address',
            'emergency_contact', 'emergency_phone', 'hire_date',
            'contract_start_date', 'contract_end_date', 'probation_end_date', 'project_ids', 
            
            // 身份证有效期
            'id_card_valid_from', 'id_card_valid_until',
            
            // 工资卡信息
            'bank_name', 'bank_account', 'bank_account_holder', 'bank_branch', 'basic_salary', 'salary_items', 'remittance_remark',
            
            // 保险信息
            'social_security_base', 'social_security_enrollment_month', 'medical_insurance_base', 
            'housing_fund_base', 'housing_fund_enrollment_month',
            'large_medical_base', 'large_medical_company_base',
            'special_deduction', 'is_annual_deduction',
            'social_security_region_id', 'medical_insurance_region_id', 
            'housing_fund_region_id', 'housing_fund_config_id', 
            'large_medical_insurance_config_id', 'other_insurance_enabled', 'other_insurance_policy_ids', 'insurance_completed_at',
            'social_insurance_enrollment_date', 'provident_fund_enrollment_date',
            'medical_insurance_enrollment_date', 'large_medical_enrollment_date',
            
            // 新增详细字段
            // 一、基础身份信息
            'country_region', 'chinese_name', 'birth_country', 'other_id_type', 'other_id_number',
            
            // 二、从业任职信息
            'personnel_status', 'employment_type', 'employment_date', 'resignation_date', 
            'signing_location', 'annual_employment_status', 'job_title',
            
            // 三、特殊身份信息
            'is_disabled', 'disability_cert_type', 'disability_cert_number',
            'is_martyr_family', 'martyr_family_cert_number', 'is_elderly_alone',
            'skip_form_filling',
            
            // 四、涉税与投资信息
            'tax_matter', 'deduct_expense', 'personal_investment_amount', 'personal_investment_ratio',
            
            // 五、出入境信息
            'first_entry_date', 'expected_departure_date',
            
            // 六、联系方式与银行信息
            'email_address', 'bank_province',
            
            // 七、地址信息
            'household_province', 'household_city', 'household_district', 'household_address',
            'residence_province', 'residence_city', 'residence_district', 'residence_address',
            'contact_province', 'contact_city', 'contact_district', 'contact_address',
            
            // 其他信息
            'remarks',
            
            // 八、备注说明信息
            'other_notes'
        ]);
        
        // 新建员工时，合同流程状态默认为待签署，人员状态默认为在职
        $employeeData['contract_status'] = 'unsigned';
        if (empty($employeeData['personnel_status'])) {
            $employeeData['personnel_status'] = 'active';
        }

        // 为布尔字段设置默认值（防止NOT NULL约束错误）
        $booleanFields = ['is_disabled', 'is_martyr_family', 'is_elderly_alone', 'skip_form_filling', 'deduct_expense'];
        foreach ($booleanFields as $field) {
            if (!isset($employeeData[$field]) || $employeeData[$field] === null || $employeeData[$field] === '') {
                $employeeData[$field] = false;
            }
        }
        
        // 为数值字段设置默认值（防止NOT NULL约束错误）
        $numericDefaultFields = ['personal_investment_amount', 'personal_investment_ratio'];
        foreach ($numericDefaultFields as $field) {
            if (!isset($employeeData[$field]) || $employeeData[$field] === null || $employeeData[$field] === '') {
                $employeeData[$field] = 0;
            }
        }
        
        // 清理数值字段，移除千位分隔符
        $numericFields = ['basic_salary', 'social_security_base', 'medical_insurance_base', 'housing_fund_base', 'large_medical_base', 'large_medical_company_base', 'special_deduction'];
        foreach ($numericFields as $field) {
            if (isset($employeeData[$field]) && is_string($employeeData[$field])) {
                $employeeData[$field] = str_replace(',', '', $employeeData[$field]);
            }
        }
        
        // 转换日期格式
        $dateFields = [
            'birth_date', 'hire_date', 'contract_start_date', 'contract_end_date', 'probation_end_date',
            'id_card_valid_from', 'id_card_valid_until',
            'social_insurance_enrollment_date', 'provident_fund_enrollment_date',
            'medical_insurance_enrollment_date', 'large_medical_enrollment_date',
            'employment_date', 'resignation_date', 'first_entry_date', 'expected_departure_date'
        ];
        foreach ($dateFields as $field) {
            if (array_key_exists($field, $employeeData) && $employeeData[$field] === '') {
                $employeeData[$field] = null;
            }
            if (isset($employeeData[$field]) && $employeeData[$field] && (strpos((string) $employeeData[$field], 'T') !== false || strpos((string) $employeeData[$field], 'Z') !== false)) {
                $employeeData[$field] = date('Y-m-d', strtotime($employeeData[$field]));
            }
        }
        
        // 处理参保完成时间字段（datetime格式）
        if (isset($employeeData['insurance_completed_at']) && $employeeData['insurance_completed_at']) {
            if (strpos($employeeData['insurance_completed_at'], 'T') !== false) {
                $employeeData['insurance_completed_at'] = date('Y-m-d H:i:s', strtotime($employeeData['insurance_completed_at']));
            }
        }

        $this->syncLargeMedicalWithMedical($employeeData);
        $this->syncEmployeeOtherInsuranceSelection(
            $employeeData,
            $request->has('project_ids') && is_array($request->project_ids)
                ? ($request->project_ids[0] ?? null)
                : null
        );
        
        // 【账套关联】自动关联到当前账套
        $currentAccountSetId = $request->input('current_account_set_id');
        
        \Log::info('创建员工 - 账套ID检查', [
            'current_account_set_id' => $currentAccountSetId,
            'user_role' => $request->user()->role,
            'all_input' => $request->all()
        ]);
        
        if ($currentAccountSetId) {
            $employeeData['account_set_id'] = $currentAccountSetId;
            
            // 如果没有提供工号，则自动生成工号
            if (empty($employeeData['employee_number'])) {
                // 获取第一个项目ID（如果有）
                $projectId = null;
                if (!empty($request->project_ids) && is_array($request->project_ids)) {
                    $projectId = $request->project_ids[0];
                }
                
                $employeeData['employee_number'] = $this->generateEmployeeNumber($currentAccountSetId, $projectId);
                \Log::info('自动生成工号', [
                    'employee_number' => $employeeData['employee_number'],
                    'project_id' => $projectId
                ]);
            }
        } else {
            // 如果没有账套ID，非admin用户不能创建员工
            if ($request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => '未选择账套，无法创建员工'
                ], 422);
            }
        }

        try {
            \Log::info('开始创建员工数据', [
                'final_employee_data' => $employeeData
            ]);
            
            $employee = Employee::create($employeeData);
            
            \Log::info('员工创建成功', [
                'employee_id' => $employee->id,
                'account_set_id' => $employee->account_set_id,
                'created_employee' => $employee
            ]);
        } catch (\Exception $e) {
            \Log::error('员工创建失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'employee_data' => $employeeData
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '员工创建失败: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }

        // 关联项目（如果有项目关联）
        if ($request->has('project_ids') && is_array($request->project_ids)) {
            // 处理日期格式，确保是纯日期格式
            $startDate = $request->hire_date;
            if (strpos($startDate, 'T') !== false) {
                $startDate = date('Y-m-d', strtotime($startDate));
            }
            
            $employee->projects()->attach($request->project_ids, $this->withProjectBindingData([
                'start_date' => $startDate,
                'status' => 'active'
            ], $employee->employee_number, $resolvedDocumentSetId, true));
        }

        $employee->refresh();
        $employee->load('projects');
        $this->detectInsuranceRegionChanges($employee, [
            'social_security_region_id' => null,
            'medical_insurance_region_id' => null,
            'housing_fund_region_id' => null,
            'housing_fund_config_id' => null,
            'large_medical_insurance_config_id' => null,
            'other_insurance_policy_ids' => [],
            'social_security_base' => null,
            'medical_insurance_base' => null,
            'housing_fund_base' => null,
            'large_medical_base' => null,
            'large_medical_company_base' => null,
        ], $employeeData);

        return response()->json([
            'success' => true,
            'message' => '员工信息创建成功',
            'data' => $employee->load('projects')
        ]);
    }

    public function show($id)
    {
        // 人员档案查看权限（详情）
        if ($response = $this->checkPermission('employees.view')) {
            return $response;
        }
        
        $employee = Employee::with(['projects', 'activeProjects', 'socialSecurityRegion', 'medicalInsuranceRegion', 'housingFundRegion', 'housingFundConfig'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $employee
        ]);
    }

    public function update(Request $request, $id)
    {
        // 人员档案编辑权限
        if ($response = $this->checkPermission('employees.edit')) {
            return $response;
        }
        
        // 添加详细日志
        \Log::info('员工更新开始', [
            'employee_id' => $id,
            'request_data' => $request->all()
        ]);
        
        $employee = Employee::findOrFail($id);
        $originalPersonnelStatus = $this->resolveEmployeePersonnelStatus($employee);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'id_number' => 'sometimes|required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'gender' => 'sometimes|required|in:male,female',
            'birth_date' => 'sometimes|required|date',
            'hire_date' => 'sometimes|required|date',
            'contract_start_date' => 'sometimes|required|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'transfer_date' => 'nullable|date',
            'termination_reason' => 'nullable|string|max:255',
            'project_document_set_id' => 'nullable|integer',
            'remittance_remark' => 'nullable|string|max:255',
            'household_type' => 'nullable|in:agricultural,non_agricultural',
            'salary_items' => 'nullable|array',
            'salary_items.*.name' => 'required|string|max:50',
            'salary_items.*.amount' => 'required|numeric|min:0',
            'social_security_base' => 'nullable|numeric|min:0',
            'medical_insurance_base' => 'nullable|numeric|min:0',
            'housing_fund_base' => 'nullable|numeric|min:0',
            'social_security_region_id' => 'nullable|integer|exists:social_security_regions,id',
            'medical_insurance_region_id' => 'nullable|integer|exists:medical_insurance_regions,id',
            'housing_fund_region_id' => 'nullable|integer|exists:housing_fund_regions,id',
            'housing_fund_config_id' => 'nullable|integer|exists:housing_fund_configs,id',
            'social_insurance_enrollment_date' => 'nullable|date',
            'medical_insurance_enrollment_date' => 'nullable|date',
            'provident_fund_enrollment_date' => 'nullable|date',
            'other_insurance_policy_ids' => 'nullable|array',
            'other_insurance_policy_ids.*' => 'integer|exists:other_insurance_policies,id',
        ], [
            'social_security_region_id.required' => '请选择社保参保地区',
            'social_security_region_id.integer' => '社保参保地区格式不正确',
            'social_security_region_id.exists' => '所选社保参保地区不存在',
            'medical_insurance_region_id.required' => '请选择医保参保地区',
            'medical_insurance_region_id.integer' => '医保参保地区格式不正确',
            'medical_insurance_region_id.exists' => '所选医保参保地区不存在',
            'housing_fund_region_id.required' => '请选择公积金参保地区',
            'housing_fund_region_id.integer' => '公积金参保地区格式不正确',
            'housing_fund_region_id.exists' => '所选公积金参保地区不存在',
            'housing_fund_config_id.required' => '请选择公积金配置',
            'housing_fund_config_id.integer' => '公积金配置格式不正确',
            'housing_fund_config_id.exists' => '所选公积金配置不存在',
        ]);

        if ($validator->fails()) {
            \Log::error('员工更新验证失败', [
                'employee_id' => $id,
                'errors' => $validator->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 检查参保时间不可早于项目开始时间
        $projectIdsForCheck = $request->has('project_ids') && is_array($request->project_ids)
            ? $request->project_ids
            : $employee->project_ids;
        if (!empty($projectIdsForCheck)) {
            $employmentDateError = $this->checkEmploymentDateVsProjectStart($projectIdsForCheck, $request);
            if ($employmentDateError) {
                return response()->json([
                    'success' => false,
                    'message' => $employmentDateError
                ], 422);
            }

            $insuranceFieldError = $this->checkInsuranceFieldPairs($request);
            if ($insuranceFieldError) {
                return response()->json([
                    'success' => false,
                    'message' => $insuranceFieldError
                ], 422);
            }

            $insuranceRequiredError = $this->checkInsuranceRequiredFieldsByRegion($request);
            if ($insuranceRequiredError) {
                return response()->json([
                    'success' => false,
                    'message' => $insuranceRequiredError
                ], 422);
            }

            $insuranceDateError = $this->checkInsuranceDateVsProjectStart($projectIdsForCheck, $request);
            if ($insuranceDateError) {
                return response()->json([
                    'success' => false,
                    'message' => $insuranceDateError
                ], 422);
            }
        }

        // 处理日期字段格式 - 包含所有新增字段
        $updateData = $request->only([
            // 基础信息
            'name', 'employee_number', 'position', 'id_number', 'phone', 'email', 'gender', 'birth_date',
            'nationality', 'marital_status', 'education', 'address',
            'emergency_contact', 'emergency_phone', 'hire_date',
            'contract_start_date', 'contract_end_date', 'probation_end_date', 'contract_status', 'project_ids', 
            
            // 身份证有效期
            'id_card_valid_from', 'id_card_valid_until',
            
            // 工资卡信息
            'bank_name', 'bank_account', 'bank_account_holder', 'bank_branch', 'basic_salary', 'salary_items', 'remittance_remark',
            
            // 保险信息
            'social_security_base', 'medical_insurance_base', 'housing_fund_base', 
            'large_medical_base', 'large_medical_company_base',
            'special_deduction', 'is_annual_deduction',
            'social_security_region_id', 'medical_insurance_region_id', 
            'housing_fund_region_id', 'housing_fund_config_id', 
            'large_medical_insurance_config_id', 'other_insurance_enabled', 'other_insurance_policy_ids', 'insurance_completed_at',
            'social_insurance_enrollment_date', 'provident_fund_enrollment_date',
            'medical_insurance_enrollment_date', 'large_medical_enrollment_date',
            
            // 新增详细字段
            // 一、基础身份信息
            'country_region', 'chinese_name', 'birth_country', 'other_id_type', 'other_id_number',
            
            // 二、从业任职信息
            'personnel_status', 'employment_type', 'employment_date', 'resignation_date', 'termination_reason',
            'signing_location', 'household_type', 'annual_employment_status', 'job_title',
            
            // 三、特殊身份信息
            'is_disabled', 'disability_cert_type', 'disability_cert_number',
            'is_martyr_family', 'martyr_family_cert_number', 'is_elderly_alone',
            'skip_form_filling',
            
            // 四、涉税与投资信息
            'tax_matter', 'deduct_expense', 'personal_investment_amount', 'personal_investment_ratio',
            
            // 五、出入境信息
            'first_entry_date', 'expected_departure_date',
            
            // 六、联系方式与银行信息
            'email_address', 'bank_province',
            
            // 七、地址信息
            'household_province', 'household_city', 'household_district', 'household_address',
            'residence_province', 'residence_city', 'residence_district', 'residence_address',
            'contact_province', 'contact_city', 'contact_district', 'contact_address',
            
            // 其他信息
            'remarks',
            
            // 八、备注说明信息
            'other_notes'
        ]);
        
        \Log::info('员工更新数据处理后', [
            'employee_id' => $id,
            'update_data' => $updateData
        ]);
        
        // 过滤 contract_status：如果值是 'retired'，则移除该字段（因为数据库 ENUM 不支持 'retired'）
        // 'retired' 状态是通过 is_retired 字段来判断的，不应该直接保存到 contract_status
        if (isset($updateData['contract_status']) && $updateData['contract_status'] === 'retired') {
            unset($updateData['contract_status']);
            // 如果前端传递了 retired，说明应该设置 is_retired = true
            if (Schema::hasColumn('employees', 'is_retired')) {
                $updateData['is_retired'] = true;
            }
        }

        // 清理数值字段，移除千位分隔符
        $numericFields = ['basic_salary', 'social_security_base', 'medical_insurance_base', 'housing_fund_base', 'large_medical_base', 'large_medical_company_base', 'special_deduction'];
        foreach ($numericFields as $field) {
            if (isset($updateData[$field]) && is_string($updateData[$field])) {
                $updateData[$field] = str_replace(',', '', $updateData[$field]);
            }
        }
        
        // 转换日期格式
        $dateFields = [
            'birth_date', 'hire_date', 'contract_start_date', 'contract_end_date', 'probation_end_date',
            'id_card_valid_from', 'id_card_valid_until',
            'social_insurance_enrollment_date', 'provident_fund_enrollment_date',
            'medical_insurance_enrollment_date', 'large_medical_enrollment_date',
            'employment_date', 'resignation_date', 'first_entry_date', 'expected_departure_date'
        ];
        foreach ($dateFields as $field) {
            if (isset($updateData[$field]) && $updateData[$field]) {
                // 如果包含 T 或 Z，说明是 ISO 格式，需要转换
                if (strpos($updateData[$field], 'T') !== false || strpos($updateData[$field], 'Z') !== false) {
                    $updateData[$field] = date('Y-m-d', strtotime($updateData[$field]));
                }
                // 如果已经是 Y-m-d 格式，保持不变
            }
        }
        
        // 处理参保完成时间字段（datetime格式）
        if (isset($updateData['insurance_completed_at']) && $updateData['insurance_completed_at']) {
            if (strpos($updateData['insurance_completed_at'], 'T') !== false) {
                $updateData['insurance_completed_at'] = date('Y-m-d H:i:s', strtotime($updateData['insurance_completed_at']));
            }
        }

        $this->syncLargeMedicalWithMedical($updateData, $employee);
        $this->syncEmployeeOtherInsuranceSelection(
            $updateData,
            $request->has('project_ids') && is_array($request->project_ids)
                ? ($request->project_ids[0] ?? null)
                : $employee->getCurrentProject()?->id,
            true,
            $employee
        );

        // 记录项目变更前后的对比（单活跃项目）
        $originalInsuranceData = $employee->only([
            'social_security_region_id',
            'medical_insurance_region_id',
            'housing_fund_region_id',
            'housing_fund_config_id',
            'large_medical_insurance_config_id',
            'other_insurance_policy_ids',
            'social_security_base',
            'medical_insurance_base',
            'housing_fund_base',
            'large_medical_base',
            'large_medical_company_base',
        ]);
        $oldActiveProjectId = $employee->activeProjects()->pluck('projects.id')->first();
        $newProjectId = null;
        if ($request->has('project_ids') && is_array($request->project_ids)) {
            $newProjectId = $request->project_ids[0] ?? null;
        }
        $employeeNumberBeforeUpdate = $employee->employee_number;
        $targetProjectId = $newProjectId ?: $oldActiveProjectId;
        $resolvedDocumentSetId = null;

        if (($request->has('project_document_set_id') || ($newProjectId && $newProjectId != $oldActiveProjectId)) && $targetProjectId) {
            try {
                $resolvedDocumentSetId = $this->resolveProjectDocumentSetId(
                    (int) $targetProjectId,
                    $request->input('project_document_set_id')
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }
        }

        try {
            \Log::info('开始更新员工数据', [
                'employee_id' => $id,
                'final_update_data' => $updateData
            ]);
            
            $employee->update($updateData);

            // 如果项目发生变化，则同步 employee_projects（单活跃），并记录变更日志
            if ($newProjectId && $newProjectId != $oldActiveProjectId) {
                $transferDate = $request->filled('transfer_date')
                    ? Carbon::parse($request->input('transfer_date'))->toDateString()
                    : date('Y-m-d');
                $newEmployeeNumber = $this->generateEmployeeNumber($employee->account_set_id, $newProjectId);
                DB::beginTransaction();
                try {
                    // 结束当前所有 active 项目
                    $activeIds = $employee->projects()->wherePivot('status', 'active')->pluck('projects.id')->toArray();
                    foreach ($activeIds as $pid) {
                        $pivotData = [
                            'status' => 'inactive',
                            'end_date' => $transferDate,
                        ];

                        if ($this->hasProjectEmployeeNumberColumn()) {
                            $existingActivePivot = DB::table('employee_projects')
                                ->where('employee_id', $employee->id)
                                ->where('project_id', $pid)
                                ->first();
                            if ($existingActivePivot && empty($existingActivePivot->employee_number)) {
                                $pivotData['employee_number'] = $employeeNumberBeforeUpdate;
                            }
                        }

                        $employee->projects()->updateExistingPivot($pid, $pivotData);
                    }

                    // 激活/新增目标项目
                    $existing = DB::table('employee_projects')
                        ->where('employee_id', $employee->id)
                        ->where('project_id', $newProjectId)
                        ->first();

                    if ($existing) {
                        $employee->projects()->updateExistingPivot($newProjectId, $this->withProjectBindingData([
                            'status' => 'active',
                            'end_date' => null,
                            'start_date' => $transferDate,
                        ], $newEmployeeNumber, $resolvedDocumentSetId, true));
                    } else {
                        $employee->projects()->attach($newProjectId, $this->withProjectBindingData([
                            'status' => 'active',
                            'start_date' => $transferDate,
                        ], $newEmployeeNumber, $resolvedDocumentSetId, true));
                    }

                    // 同步 employees.project_ids 为单项目
                    $employee->project_ids = [$newProjectId];
                    $employee->employee_number = $newEmployeeNumber;
                    $employee->save();

                    // 写入变更日志
                    DB::table('employee_project_change_logs')->insert([
                        'employee_id' => $employee->id,
                        'from_project_id' => $oldActiveProjectId,
                        'to_project_id' => $newProjectId,
                        'changed_at' => Carbon::parse($transferDate)->startOfDay(),
                        'reason' => $request->input('transfer_reason'),
                        'operator_id' => optional($request->user())->id,
                        'account_set_id' => $employee->account_set_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::commit();
                } catch (\Throwable $te) {
                    DB::rollBack();
                    \Log::error('项目变更同步失败', [
                        'employee_id' => $id,
                        'error' => $te->getMessage(),
                    ]);
                }
            } elseif ($request->has('project_document_set_id') && $oldActiveProjectId) {
                $employee->projects()->updateExistingPivot($oldActiveProjectId, $this->withProjectBindingData(
                    [],
                    null,
                    $resolvedDocumentSetId,
                    true
                ));
                $this->syncActiveProjectEmployeeNumber($employee);
            } else {
                $this->syncActiveProjectEmployeeNumber($employee);
            }

            $employee->refresh();
            $employee->load('projects');
            $this->detectInsuranceRegionChanges($employee, $originalInsuranceData, $updateData);

            $shouldCreateResignationInsuranceTask = array_key_exists('personnel_status', $updateData)
                && in_array($updateData['personnel_status'], ['resigned', '离职'], true)
                && !in_array($originalPersonnelStatus, ['resigned', 'retired'], true);

            if ($shouldCreateResignationInsuranceTask) {
                app(\App\Services\ApprovalService::class)->createDecreaseInsuranceRecordForEmployee(
                    $employee,
                    'terminated',
                    $request->user()?->id,
                    $employee->termination_reason
                );
            }
            
            \Log::info('员工数据更新成功', [
                'employee_id' => $id,
                'updated_employee' => $employee->fresh()
            ]);

            return response()->json([
                'success' => true,
                'message' => '员工信息更新成功',
                'data' => $employee->load('projects')
            ]);
        } catch (\Exception $e) {
            \Log::error('员工数据更新失败', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'update_data' => $updateData
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '员工信息更新失败: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 专门用于调试员工数据的方法
    public function debugEmployeeData(Request $request)
    {
        \Log::info('=== 员工数据调试开始 ===', [
            'request_params' => $request->all(),
            'current_account_set_id' => $request->input('current_account_set_id')
        ]);

        try {
            // 查询员工数据
            $query = Employee::query();
            
            $currentAccountSetId = $request->input('current_account_set_id');
            if ($currentAccountSetId) {
                $query->where('account_set_id', $currentAccountSetId);
            }
            
            $employees = $query->get();
            
            \Log::info('员工数据查询完成', [
                'total_employees' => $employees->count(),
                'account_set_id' => $currentAccountSetId
            ]);
            
            // 记录每个员工的详细信息
            foreach ($employees as $index => $employee) {
                \Log::info("员工 #{$index}", [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'id_number' => $employee->id_number,
                    'country_region' => $employee->country_region,
                    'gender' => $employee->gender,
                    'birth_date' => $employee->birth_date,
                    'personnel_status' => $employee->personnel_status,
                    'employment_type' => $employee->employment_type,
                    'phone' => $employee->phone,
                    'all_attributes' => $employee->getAttributes()
                ]);
            }
            
            \Log::info('=== 员工数据调试结束 ===');
            
            return response()->json([
                'success' => true,
                'message' => '调试信息已记录到日志',
                'data' => [
                    'total_employees' => $employees->count(),
                    'employees' => $employees->toArray()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('员工数据调试失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '调试失败: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        // 人员档案删除权限
        if ($response = $this->checkPermission('employees.delete')) {
            return $response;
        }
        
        $employee = Employee::findOrFail($id);
        
        // 检查是否有待审批的删除申请
        $hasPendingApproval = \App\Models\ApprovalInstance::where('business_type', 'employee_deletion')
            ->where('business_id', $employee->id)
            ->where('status', 'pending')
            ->exists();
        
        if ($hasPendingApproval) {
            return response()->json([
                'success' => false,
                'message' => '该员工有待审批的删除申请，请等待审批完成'
            ], 403);
        }
        
        // 检查是否是在职员工
        $isActive = $employee->contract_status === 'active';
        
        if ($isActive) {
            // 检查是否是管理员
            $user = auth()->user();
            $isAdmin = $user->role === 'admin' || $user->role === 'super_admin';
            
            if (!$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工为在职状态，不能直接删除。请提交删除审批。'
                ], 403);
            }
            
            // 管理员可以直接删除在职员工
            \Log::info('管理员直接删除在职员工', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'admin_id' => $user->id,
                'admin_name' => $user->name
            ]);
        }
        
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => '员工信息删除成功'
        ]);
    }
    
    /**
     * 提交删除员工审批
     */
    public function submitDeleteApproval(Request $request)
    {
        // 移除权限检查，允许所有人提交删除审批
        // if ($response = $this->checkPermission('employees.delete')) {
        //     return $response;
        // }

        $validator = \Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = Employee::findOrFail($request->employee_id);

            // 检查是否是在职员工
            if ($employee->contract_status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => '该员工不是在职状态，可以直接删除，无需审批'
                ], 400);
            }

            // 检查是否已有待审批的删除申请（排除已驳回的）
            $existingApproval = \App\Models\ApprovalInstance::where('business_type', 'employee_deletion')
                ->where('business_id', $employee->id)
                ->where('status', 'pending')
                ->exists();

            if ($existingApproval) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工已有待审批的删除申请，请勿重复提交'
                ], 400);
            }

            $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');

            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择账套'
                ], 400);
            }

            // 创建审批流程
            $approvalService = app(\App\Services\ApprovalService::class);
            $stampMethod = $request->input('stamp_method', 'online'); // 盖章方式
            $stampOptions = $this->resolveApprovalStampOptions($request, $accountSetId);
            $instance = $approvalService->createApprovalInstance(
                $accountSetId,
                'employee_deletion', // 业务类型：员工删除
                $employee->id,
                $request->user()->id,
                [], // 无附件
                false,
                $stampMethod, // 盖章方式
                $stampOptions
            );

            // 保存删除原因到审批实例的备注中
            $instance->update(['remark' => $request->reason]);

            return response()->json([
                'success' => true,
                'message' => '删除审批已提交，请等待审批',
                'data' => $instance
            ]);

        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('提交删除员工审批失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交工资调整审批
     */
    public function submitSalaryAdjustmentApproval(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'basic_salary' => 'nullable|numeric|min:0',
            'salary_items' => 'nullable|array',
            'salary_items.*.name' => 'required|string|max:50',
            'salary_items.*.amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
            'stamp_method' => 'nullable|in:online,offline',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = Employee::findOrFail($request->employee_id);

            // 检查是否有审批中的工资调整
            $existingApproval = ApprovalInstance::where('business_type', 'employee_salary_adjustment')
                ->where('business_id', $employee->id)
                ->where('status', 'pending')
                ->exists();

            if ($existingApproval) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工已有待审批的工资调整申请，请勿重复提交'
                ], 400);
            }

            $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择账套'
                ], 400);
            }

            $newBasicSalary = $request->input('basic_salary');
            $newSalaryItems = $request->input('salary_items', []);
            $reason = $request->input('reason');

            // 先创建审批实例
            $approvalService = app(\App\Services\ApprovalService::class);
            $stampMethod = $request->input('stamp_method', 'online');
            $stampOptions = $this->resolveApprovalStampOptions($request, $accountSetId);
            $instance = $approvalService->createApprovalInstance(
                $accountSetId,
                'employee_salary_adjustment',
                $employee->id,
                $request->user()->id,
                [],
                false,
                $stampMethod,
                $stampOptions
            );

            // 将本次调薪内容写入审批实例扩展字段
            $instance->update([
                'old_basic_salary' => $employee->basic_salary,
                'old_salary_items' => $employee->salary_items,
                'new_basic_salary' => $newBasicSalary,
                'new_salary_items' => $newSalaryItems,
                'salary_adjustment_reason' => $reason,
            ]);

            return response()->json([
                'success' => true,
                'message' => '工资调整审批已提交，请等待审批',
                'data' => $instance
            ]);

        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('提交工资调整审批失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取员工登记表修改审批状态
     */
    public function getRegistrationFormUpdateStatus(Request $request, $id)
    {
        try {
            $employee = Employee::with(['projects'])->findOrFail($id);
            $formType = $this->resolveEmployeeRegistrationFormType($employee, $request->input('project_id'));

            if (!Schema::hasTable('employee_form_update_requests')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'form_type' => $formType,
                        'form_type_text' => $this->getRegistrationFormTypeText($formType),
                        'has_pending' => false,
                        'has_table' => false,
                        'message' => '请先执行 database/sql/2026_06_08_create_employee_form_update_requests.sql',
                    ],
                ]);
            }

            $pendingRequest = EmployeeFormUpdateRequest::with(['approvalInstance', 'creator'])
                ->where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'form_type' => $formType,
                    'form_type_text' => $this->getRegistrationFormTypeText($formType),
                    'has_pending' => (bool) $pendingRequest,
                    'has_table' => true,
                    'pending_request' => $pendingRequest ? [
                        'id' => $pendingRequest->id,
                        'reason' => $pendingRequest->reason,
                        'approval_instance_id' => $pendingRequest->approval_instance_id,
                        'form_type' => $pendingRequest->form_type,
                        'form_type_text' => $this->getRegistrationFormTypeText($pendingRequest->form_type),
                        'created_at' => optional($pendingRequest->created_at)->format('Y-m-d H:i:s'),
                        'creator_name' => optional($pendingRequest->creator)->name,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('获取登记表修改审批状态失败', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 提交员工登记表修改审批
     */
    public function submitRegistrationFormUpdateApproval(Request $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'form_type' => 'required|in:onboarding,registration',
            'form_data' => 'required|array',
            'reason' => 'nullable|string|max:500',
            'stamp_method' => 'nullable|in:online,offline',
            'project_id' => 'nullable|integer|exists:projects,id',
        ], [
            'form_type.required' => '登记表类型不能为空',
            'form_type.in' => '登记表类型不正确',
            'form_data.required' => '登记表修改内容不能为空',
            'form_data.array' => '登记表修改内容格式不正确',
            'reason.max' => '修改说明不能超过500个字符',
            'stamp_method.in' => '盖章方式不正确',
            'project_id.integer' => '项目ID格式不正确',
            'project_id.exists' => '项目不存在',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Schema::hasTable('employee_form_update_requests')) {
            return response()->json([
                'success' => false,
                'message' => '请先执行 database/sql/2026_06_08_create_employee_form_update_requests.sql 后再发起登记表修改审批',
            ], 400);
        }

        $requestRecord = null;

        try {
            $employee = Employee::with(['projects'])->findOrFail($id);
            $actualFormType = $this->resolveEmployeeRegistrationFormType($employee, $request->input('project_id'));
            $formType = $request->input('form_type');

            if ($formType !== $actualFormType) {
                return response()->json([
                    'success' => false,
                    'message' => '当前项目设置的登记表类型是' . $this->getRegistrationFormTypeText($actualFormType) . '，请重新打开后再提交',
                ], 400);
            }

            $form = $this->getEmployeeRegistrationFormModel($employee->id, $formType);
            if (!$form) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工尚未填写' . $this->getRegistrationFormTypeText($formType) . '，不能发起修改审批',
                ], 400);
            }

            $existingPending = EmployeeFormUpdateRequest::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->exists();

            if ($existingPending) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工的登记表修改正在审批中，请审批结束后再发起',
                ], 400);
            }

            $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id') ?: $employee->account_set_id;
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择账套',
                ], 400);
            }

            if ((int) $employee->account_set_id !== (int) $accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工不属于当前账套',
                ], 400);
            }

            $newData = $this->filterEmployeeFormUpdateData($formType, $request->input('form_data', []));
            if (empty($newData)) {
                return response()->json([
                    'success' => false,
                    'message' => '没有可提交的登记表修改内容',
                ], 400);
            }

            $requestRecord = EmployeeFormUpdateRequest::create([
                'account_set_id' => $accountSetId,
                'employee_id' => $employee->id,
                'form_type' => $formType,
                'original_data' => $this->buildEmployeeFormOriginalData($employee, $formType, $form),
                'new_data' => $newData,
                'reason' => $request->input('reason'),
                'status' => 'pending',
                'created_by' => optional($request->user())->id,
            ]);

            $approvalService = app(\App\Services\ApprovalService::class);
            $stampMethod = $request->input('stamp_method', 'online');
            $stampOptions = $this->resolveApprovalStampOptions($request, $accountSetId);
            $instance = $approvalService->createApprovalInstance(
                $accountSetId,
                'employee_registration_form_update',
                $requestRecord->id,
                optional($request->user())->id,
                [],
                false,
                $stampMethod,
                $stampOptions
            );

            $requestRecord->update([
                'approval_instance_id' => $instance->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '登记表修改审批已提交，请等待审批',
                'data' => [
                    'request_id' => $requestRecord->id,
                    'approval_instance_id' => $instance->id,
                ],
            ]);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            if ($requestRecord && !$requestRecord->approval_instance_id) {
                $requestRecord->delete();
            }
            throw $e;
        } catch (\Exception $e) {
            if ($requestRecord && !$requestRecord->approval_instance_id) {
                $requestRecord->delete();
            }

            \Log::error('提交登记表修改审批失败', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 上传登记表一寸照片（PC端登记表修改审批使用）
     */
    public function uploadRegistrationFormPhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:5120',
        ], [
            'photo.required' => '请上传照片',
            'photo.image' => '请上传图片文件',
            'photo.max' => '照片大小不能超过5MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('photo');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = uniqid('photo_', true) . '_' . time() . '.' . $extension;
        $dir = public_path('uploads/photos');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file->move($dir, $filename);

        $path = 'uploads/photos/' . $filename;
        $url = request()->getSchemeAndHttpHost() . '/' . $path;

        return response()->json([
            'success' => true,
            'message' => '照片上传成功',
            'data' => [
                'path' => $path,
                'url' => $url,
            ],
        ]);
    }

    private function resolveEmployeeRegistrationFormType(Employee $employee, $selectedProjectId = null): string
    {
        if ($selectedProjectId) {
            $project = null;

            if ($employee->relationLoaded('projects')) {
                $project = $employee->projects->first(function ($item) use ($selectedProjectId) {
                    return (int) $item->id === (int) $selectedProjectId;
                });
            }

            if (!$project) {
                $project = $employee->projects()
                    ->where('projects.id', $selectedProjectId)
                    ->first();
            }

            if ($project) {
                return $project->registration_form_type === 'registration'
                    ? 'registration'
                    : 'onboarding';
            }
        }

        $project = $employee->projects()
            ->wherePivot('status', 'active')
            ->first();

        if (!$project && !empty($employee->project_ids)) {
            $projectId = is_array($employee->project_ids)
                ? ($employee->project_ids[0] ?? null)
                : $employee->project_ids;
            $project = $projectId ? Project::find($projectId) : null;
        }

        if (!$project && $employee->relationLoaded('projects')) {
            $project = $employee->projects->first();
        }

        return ($project && $project->registration_form_type === 'registration')
            ? 'registration'
            : 'onboarding';
    }

    private function getRegistrationFormTypeText(string $formType): string
    {
        return $formType === 'registration' ? '从业人员登记表' : '员工入职登记表';
    }

    private function getEmployeeRegistrationFormModel(int $employeeId, string $formType)
    {
        return $formType === 'registration'
            ? EmployeeRegistrationForm::where('employee_id', $employeeId)->first()
            : OnboardingForm::where('employee_id', $employeeId)->first();
    }

    private function buildEmployeeFormOriginalData(Employee $employee, string $formType, $form): array
    {
        $data = $form ? $form->toArray() : [];

        if ($formType === 'onboarding') {
            $data['bank_account'] = $employee->bank_account ?? '';
            $data['bank_account_holder'] = $employee->bank_account_holder ?? '';
            $data['bank_name'] = $employee->bank_name ?? '';
            $data['bank_branch'] = $employee->bank_branch ?? '';
            $data['emergency_contact'] = $employee->emergency_contact ?? '';
            $data['emergency_phone'] = $employee->emergency_phone ?? '';
        }

        return $data;
    }

    private function filterEmployeeFormUpdateData(string $formType, array $input): array
    {
        $input = $this->normalizeEmployeeFormUpdateAliases($formType, $input);
        $model = $formType === 'registration'
            ? new EmployeeRegistrationForm()
            : new OnboardingForm();

        $table = $model->getTable();
        $columns = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        $allowedFields = array_values(array_intersect($model->getFillable(), $columns));

        if ($formType === 'onboarding') {
            $allowedFields = array_merge($allowedFields, [
                'bank_account',
                'bank_account_holder',
                'bank_name',
                'bank_branch',
                'emergency_contact',
                'emergency_phone',
                'id_card_valid_from',
                'id_card_valid_until',
            ]);
        }

        $blockedFields = [
            'id',
            'employee_id',
            'account_set_id',
            'signature',
            'created_at',
            'updated_at',
            'gender_text',
            'marital_status_text',
            'household_type_text',
        ];

        if ($formType !== 'onboarding') {
            $blockedFields[] = 'photo';
        }

        $allowedFields = array_values(array_diff(array_unique($allowedFields), $blockedFields));
        $arrayFields = [
            'education_background',
            'work_experience',
            'family_info',
            'language_skills',
            'engineering_skills',
            'hobbies',
            'education_history',
            'work_history',
            'family_members',
            'employment_documents',
        ];
        $dateFields = [
            'registration_date',
            'birth_date',
            'graduation_date',
            'fill_date',
            'entry_date',
            'signature_date',
            'id_card_valid_from',
            'id_card_valid_until',
        ];

        $filtered = [];
        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];
            if (in_array($field, $arrayFields, true) && is_string($value)) {
                $decoded = json_decode($value, true);
                $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            }

            if (in_array($field, $dateFields, true) && $value === '') {
                $value = null;
            }

            $filtered[$field] = $value;
        }

        return $filtered;
    }

    private function normalizeEmployeeFormUpdateAliases(string $formType, array $input): array
    {
        if ($formType !== 'registration') {
            return $input;
        }

        $aliases = [
            'position' => 'job_title',
            'document_delivery_address' => 'document_address',
            'disability_certificate' => 'disability_level',
            'engineering_certificates' => 'engineering_skills',
            'previous_company' => 'reference_company',
            'other_diseases' => 'other_illness',
            'hospitalization_record' => 'hospitalized_recently',
            'additional_notes' => 'remarks',
            'pregnancy_status' => 'is_pregnant',
        ];

        foreach ($aliases as $alias => $target) {
            if (array_key_exists($alias, $input) && !array_key_exists($target, $input)) {
                $input[$target] = $input[$alias];
            }
        }

        return $input;
    }

    public function getReminders()
    {
        $now = Carbon::now();
        
        // 合同到期提醒（一个月内）
        $contractExpiring = Employee::where('contract_end_date', '<=', $now->copy()->addMonth())
            ->where('contract_end_date', '>', $now)
            ->where('contract_status', 'active')
            ->get();

        // 退休提醒（两个月内和一个月内）
        $retiringSoon = Employee::where('retirement_date', '<=', $now->copy()->addMonths(2))
            ->where('retirement_date', '>', $now)
            ->where('is_retired', false)
            ->get();

        // 需要处理的合同和离职人员
        $needsAttention = Employee::where(function($query) {
            $query->where('contract_status', 'expired')
                  ->orWhere(function($q) {
                      $q->whereNotNull('termination_date')
                        ->where('contract_status', '!=', 'terminated');
                  });
        })->get();

        return response()->json([
            'success' => true,
            'data' => [
                'contract_expiring' => $contractExpiring,
                'retiring_soon' => $retiringSoon,
                'needs_attention' => $needsAttention
            ]
        ]);
    }

    public function generateCertificate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:income,employment,termination',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = Employee::findOrFail($request->employee_id);
        
        $data = [
            'employee' => $employee,
            'type' => $request->type,
            'generated_at' => now(),
        ];

        if ($request->type === 'income' && $request->start_date && $request->end_date) {
            // 计算平均收入
            $avgSalary = $employee->salaries()
                ->whereBetween('month', [$request->start_date, $request->end_date])
                ->avg('gross_salary');
            
            $data['average_salary'] = $avgSalary;
            $data['period'] = [
                'start' => $request->start_date,
                'end' => $request->end_date
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function sendContractSms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:contract,termination',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = Employee::findOrFail($request->employee_id);
        
        // 这里应该集成短信服务
        // 暂时返回成功响应
        return response()->json([
            'success' => true,
            'message' => '短信发送成功'
        ]);
    }

    /**
     * 获取员工项目变更日志（调动记录）
     */
    public function getProjectChangeLogs($employeeId)
    {
        // 确保员工存在
        $employee = Employee::findOrFail($employeeId);

        $logs = DB::table('employee_project_change_logs as l')
            ->leftJoin('projects as fp', 'fp.id', '=', 'l.from_project_id')
            ->leftJoin('projects as tp', 'tp.id', '=', 'l.to_project_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.operator_id')
            ->where('l.employee_id', $employeeId)
            ->orderBy('l.changed_at', 'desc')
            ->select(
                'l.id',
                'l.employee_id',
                'l.from_project_id',
                'l.to_project_id',
                'l.changed_at',
                'l.reason',
                'l.operator_id',
                'l.account_set_id',
                'fp.name as from_project_name',
                'tp.name as to_project_name',
                'u.name as operator_name'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * 获取项目的社保地区列表（用于员工参保选择）
     */
    public function getProjectSocialSecurityRegions(Request $request, $projectId)
    {
        $project = Project::find($projectId);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        // 移除权限检查 - 任何人都可以获取项目的保险地区信息
        $regionIds = $project->social_security_regions ?? [];
        
        if (empty($regionIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => '该项目尚未设置社保地区'
            ]);
        }

        $regions = \App\Models\SocialSecurityRegion::whereIn('id', $regionIds)
            ->with(['socialSecurityTypes'])
            ->get();

        // 格式化数据，确保前端能正确显示
        $formattedRegions = $regions->map(function ($region) {
            return [
                'id' => $region->id,
                'region_name' => $region->name, // 将name映射为region_name
                'name' => $region->name,
                'account_set_id' => $region->account_set_id,
                'adjustment_base' => $region->adjustment_base,
                'effective_date' => $region->effective_date,
                'social_security_types' => $region->socialSecurityTypes
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedRegions
        ]);
    }

    /**
     * 获取项目的公积金地区列表（用于员工参保选择）
     */
    public function getProjectHousingFundRegions(Request $request, $projectId)
    {
        $project = Project::find($projectId);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        // 移除权限检查 - 任何人都可以获取项目的保险地区信息
        $regionIds = $project->housing_fund_regions ?? [];
        
        if (empty($regionIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => '该项目尚未设置公积金地区'
            ]);
        }

        $regions = \App\Models\HousingFundRegion::whereIn('id', $regionIds)->get();

        // 格式化数据，确保前端能正确显示
        $formattedRegions = $regions->map(function ($region) {
            return [
                'id' => $region->id,
                'region_name' => $region->region_name, // 使用正确的字段名
                'name' => $region->region_name,
                'account_set_id' => $region->account_set_id,
                'base_amount' => $region->base_amount,
                'employee_ratio' => $region->employee_ratio,
                'company_ratio' => $region->company_ratio
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedRegions
        ]);
    }

    /**
     * 获取项目的医保地区列表（用于员工参保选择）
     */
    public function getProjectMedicalInsuranceRegions(Request $request, $projectId)
    {
        $project = Project::find($projectId);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        // 移除权限检查 - 任何人都可以获取项目的保险地区信息

        $regionIds = $project->medical_insurance_regions ?? [];
        
        // 添加调试信息
        \Log::info('医保地区查询调试', [
            'project_id' => $projectId,
            'project_name' => $project->name,
            'medical_insurance_regions' => $regionIds,
            'region_ids_type' => gettype($regionIds),
            'region_ids_empty' => empty($regionIds)
        ]);
        
        if (empty($regionIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => '该项目尚未设置医保地区',
                'debug' => [
                    'project_id' => $projectId,
                    'medical_insurance_regions' => $regionIds
                ]
            ]);
        }

        $regions = \App\Models\MedicalInsuranceRegion::whereIn('id', $regionIds)
            ->with(['medicalInsuranceTypes'])
            ->get();
        
        // 格式化数据，确保前端能正确显示
        $formattedRegions = $regions->map(function ($region) {
            // 计算基本医保和补充医保的比例
            $basicMedicalRatio = 0;
            $supplementaryMedicalRatio = 0;
            
            if ($region->medicalInsuranceTypes) {
                foreach ($region->medicalInsuranceTypes as $type) {
                    if (strpos($type->name, '基本') !== false || strpos($type->name, '基础') !== false) {
                        $basicMedicalRatio += $type->employee_ratio + $type->company_ratio;
                    } elseif (strpos($type->name, '补充') !== false) {
                        $supplementaryMedicalRatio += $type->employee_ratio + $type->company_ratio;
                    }
                }
            }
            
            return [
                'id' => $region->id,
                'region_name' => $region->name, // 将name映射为region_name
                'name' => $region->name,
                'account_set_id' => $region->account_set_id,
                'basic_medical_ratio' => $basicMedicalRatio,
                'supplementary_medical_ratio' => $supplementaryMedicalRatio,
                'medical_insurance_types' => $region->medicalInsuranceTypes
            ];
        });
        
        \Log::info('医保地区查询结果', [
            'region_ids' => $regionIds,
            'regions_count' => $regions->count(),
            'formatted_regions' => $formattedRegions->toArray()
        ]);

        return response()->json([
            'success' => true,
            'data' => $formattedRegions,
            'debug' => [
                'region_ids' => $regionIds,
                'regions_count' => $regions->count()
            ]
        ]);
    }

    /**
     * 获取项目绑定的其他保险保单（用于员工信息显示）
     */
    public function getProjectOtherInsurancePolicies(Request $request, $projectId)
    {
        $project = Project::find($projectId);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        // 移除权限检查 - 任何人都可以获取项目的保险地区信息

        $policies = $project->otherInsurancePolicies()->with('type')->get();
        
        if ($policies->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => '该项目尚未绑定其他保险保单'
            ]);
        }

        // 格式化数据，确保前端能正确显示
        $formattedPolicies = $policies->map(function ($policy) {
            return [
                'id' => $policy->id,
                'name' => $policy->policy_name,
                'type' => $policy->type ? $policy->type->name : '未知类型',
                'coverage' => $policy->description ?: '暂无描述',
                'employee_per_capita_cost' => $policy->employee_per_capita_cost,
                'contact_name' => $policy->contact_name,
                'contact_phone' => $policy->contact_phone,
                'insurance_company' => $policy->insurance_company,
                'status' => $policy->status,
                'start_date' => $policy->start_date,
                'end_date' => $policy->end_date
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedPolicies
        ]);
    }

    /**
     * 获取项目的大额医疗保险配置列表（用于员工档案）
     */
    public function getProjectLargeMedicalInsuranceConfigs(Request $request, $projectId)
    {
        $project = Project::find($projectId);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        $configs = $project->getResolvedLargeMedicalInsuranceConfigs();
        
        if ($configs->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => '该项目尚未绑定大额医疗保险配置'
            ]);
        }

        // 格式化数据，确保前端能正确显示
        $formattedConfigs = $configs->map(function ($config) {
            return [
                'id' => $config->id,
                'region_name' => $config->region_name,
                'calculation_type' => $config->calculation_type,
                'calculation_type_text' => $config->calculation_type_text,
                'base_source' => $config->base_source, // 特殊地区标识：config=特殊地区，employee=普通地区
                'base_amount' => $config->base_amount, // 公司基数（特殊地区用）
                'employee_base_amount' => $config->employee_base_amount, // 个人基数（特殊地区用）
                'company_ratio' => $config->company_ratio,
                'employee_ratio' => $config->employee_ratio,
                'company_amount' => $config->company_amount,
                'employee_amount' => $config->employee_amount,
                'payment_cycle' => $config->payment_cycle,
                'annual_payment_month' => $config->annual_payment_month,
                'payment_cycle_text' => $config->payment_cycle_text,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedConfigs
        ]);
    }

    /**
     * 生成员工工号 API
     */
    public function generateEmployeeNumberApi(Request $request)
    {
        try {
            $accountSetId = $request->get('account_set_id');
            $projectId = $request->get('project_id'); // 获取项目ID
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '账套ID不能为空'
                ], 400);
            }
            
            // 生成完整的员工工号
            $employeeNumber = $this->generateEmployeeNumber($accountSetId, $projectId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'employee_number' => $employeeNumber
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '生成工号失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检测员工保险配置/基数变更，自动创建增减记录
     */
    private function detectInsuranceRegionChanges($employee, array $originalData, array $updateData)
    {
        try {
            $detectionService = app(\App\Services\InsuranceChangeDetectionService::class);

            $categoryMap = [
                'social_security' => [
                    'social_security_region_id' => 'region_id',
                    'social_security_base' => 'employee_social_security_base',
                ],
                'medical_insurance' => [
                    'medical_insurance_region_id' => 'region_id',
                    'medical_insurance_base' => 'employee_medical_insurance_base',
                ],
                'housing_fund' => [
                    'housing_fund_region_id' => 'region_id',
                    'housing_fund_config_id' => 'config_id',
                    'housing_fund_base' => 'employee_housing_fund_base',
                ],
                'large_medical_insurance' => [
                    'large_medical_insurance_config_id' => 'config_id',
                    'large_medical_base' => 'employee_large_medical_base',
                    'large_medical_company_base' => 'employee_large_medical_company_base',
                ],
                'other_insurance' => [
                    'other_insurance_policies' => 'policies',
                ],
            ];

            foreach ($categoryMap as $changeType => $fieldMap) {
                if ($changeType === 'other_insurance') {
                    $oldPayload = $this->buildOtherInsuranceChangePayload(
                        $originalData['other_insurance_policy_ids'] ?? []
                    );
                    $newPayload = $this->buildOtherInsuranceChangePayload(
                        $employee->other_insurance_policy_ids ?? []
                    );

                    if ($this->isSameInsuranceChangeValue($oldPayload['policies'] ?? [], $newPayload['policies'] ?? [])) {
                        continue;
                    }

                    \Log::info('检测到员工保险信息变更', [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'change_type' => $changeType,
                        'old_data' => $oldPayload,
                        'new_data' => $newPayload,
                    ]);

                    $detectionService->triggerChange([
                        'scope' => \App\Services\InsuranceChangeDetectionService::SCOPE_EMPLOYEE,
                        'change_type' => $changeType,
                        'employee' => $employee,
                        'old_data' => $oldPayload,
                        'new_data' => $newPayload,
                        'source' => 'employee_insurance_profile_change',
                    ]);
                    continue;
                }

                $hasChangedField = false;
                foreach ($fieldMap as $field => $payloadKey) {
                    if (!array_key_exists($field, $updateData)) {
                        continue;
                    }

                    $oldValue = $originalData[$field] ?? null;
                    $newValue = $employee->{$field};
                    if (!$this->isSameInsuranceChangeValue($oldValue, $newValue)) {
                        $hasChangedField = true;
                        break;
                    }
                }

                if (!$hasChangedField) {
                    continue;
                }

                $oldPayload = [];
                $newPayload = [];
                foreach ($fieldMap as $field => $payloadKey) {
                    $oldPayload[$payloadKey] = $originalData[$field] ?? null;
                    $newPayload[$payloadKey] = $employee->{$field};
                }

                \Log::info('检测到员工保险信息变更', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'change_type' => $changeType,
                    'old_data' => $oldPayload,
                    'new_data' => $newPayload,
                ]);

                $detectionService->triggerChange([
                    'scope' => \App\Services\InsuranceChangeDetectionService::SCOPE_EMPLOYEE,
                    'change_type' => $changeType,
                    'employee' => $employee,
                    'old_data' => $oldPayload,
                    'new_data' => $newPayload,
                    'source' => 'employee_insurance_profile_change',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('检测员工保险地区变更失败', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function isSameInsuranceChangeValue($oldValue, $newValue): bool
    {
        if (is_array($oldValue) || is_array($newValue)) {
            $normalize = function ($value): array {
                $items = is_array($value) ? $value : [$value];
                $items = array_values(array_filter(array_map(function ($item) {
                    return is_numeric($item) ? (int) $item : null;
                }, $items)));
                sort($items);

                return $items;
            };

            return $normalize($oldValue) === $normalize($newValue);
        }

        if (($oldValue === null || $oldValue === '') && ($newValue === null || $newValue === '')) {
            return true;
        }

        if (is_numeric($oldValue) || is_numeric($newValue)) {
            return (float) $oldValue == (float) $newValue;
        }

        return $oldValue == $newValue;
    }

    private function supportsEmployeeOtherInsurancePolicySelection(): bool
    {
        static $supports = null;

        if ($supports !== null) {
            return $supports;
        }

        $supports = Schema::hasColumn('employees', 'other_insurance_policy_ids');

        return $supports;
    }

    private function normalizeOtherInsurancePolicyIds($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return [];
        }

        $policyIds = array_values(array_unique(array_filter(array_map(function ($id) {
            return is_numeric($id) ? (int) $id : null;
        }, $value))));
        sort($policyIds);

        return $policyIds;
    }

    private function syncEmployeeOtherInsuranceSelection(
        array &$employeeData,
        $projectId,
        bool $preserveWhenMissing = false,
        ?Employee $employee = null
    ): void
    {
        $hasIncomingSelection = array_key_exists('other_insurance_policy_ids', $employeeData);
        if ($preserveWhenMissing && !$hasIncomingSelection && $employee) {
            $selectedPolicyIds = $this->normalizeOtherInsurancePolicyIds($employee->other_insurance_policy_ids ?? []);
        } else {
            $selectedPolicyIds = $this->normalizeOtherInsurancePolicyIds($employeeData['other_insurance_policy_ids'] ?? []);
        }

        $projectPolicyIds = $this->getProjectOtherInsurancePolicyIds($projectId);

        if (!empty($projectPolicyIds)) {
            $selectedPolicyIds = array_values(array_intersect($selectedPolicyIds, $projectPolicyIds));
        } else {
            $selectedPolicyIds = [];
        }

        if ($this->supportsEmployeeOtherInsurancePolicySelection()) {
            $employeeData['other_insurance_policy_ids'] = $selectedPolicyIds;
        } else {
            unset($employeeData['other_insurance_policy_ids']);
        }

        if (Schema::hasColumn('employees', 'other_insurance_enabled')) {
            $employeeData['other_insurance_enabled'] = !empty($selectedPolicyIds);
        } else {
            unset($employeeData['other_insurance_enabled']);
        }
    }

    private function getProjectOtherInsurancePolicyIds($projectId): array
    {
        if (!$projectId) {
            return [];
        }

        $project = Project::with('otherInsurancePolicies:id')->find($projectId);
        if (!$project || !$project->otherInsurancePolicies) {
            return [];
        }

        return $project->otherInsurancePolicies
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();
    }

    private function buildOtherInsuranceChangePayload($policyIds): array
    {
        return [
            'policies' => $this->normalizeOtherInsurancePolicyIds($policyIds),
        ];
    }

    /**
     * 获取员工的入职登记表
     */
    public function getOnboardingForm($id)
    {
        try {
            $employee = Employee::findOrFail($id);

            \Log::info('=== 获取员工入职登记表 ===');
            \Log::info('员工ID: ' . $id);
            \Log::info('员工信息: ' . json_encode($employee->toArray()));

            $form = OnboardingForm::where('employee_id', $employee->id)->first();

            \Log::info('入职登记表查询结果: ' . ($form ? json_encode($form->toArray()) : 'null'));

            if ($form) {
                // 确保JSON字段被正确解析
                $formData = $form->toArray();
                $host = request()->getSchemeAndHttpHost();
                
                // 将签名路径转换为完整URL
                if (!empty($formData['signature'])) {
                    if (strpos($formData['signature'], 'http') === 0) {
                        // 已经是完整URL，不处理
                    } elseif (strpos($formData['signature'], 'uploads/') === 0) {
                        $formData['signature'] = $host . '/' . $formData['signature'];
                    } else {
                        $formData['signature'] = $host . '/storage/' . $formData['signature'];
                    }
                }
                
                // 将寸照路径转换为完整URL
                if (!empty($formData['photo'])) {
                    if (strpos($formData['photo'], 'http') === 0) {
                        // 已经是完整URL，不处理
                    } elseif (strpos($formData['photo'], 'uploads/') === 0) {
                        $formData['photo'] = $host . '/' . $formData['photo'];
                    } else {
                        $formData['photo'] = $host . '/storage/' . $formData['photo'];
                    }
                }
                $formData['bank_account'] = $employee->bank_account ?? '';
                $formData['bank_account_holder'] = $employee->bank_account_holder ?? '';
                $formData['bank_name'] = $employee->bank_name ?? '';
                $formData['bank_branch'] = $employee->bank_branch ?? '';
                $formData['emergency_contact'] = $employee->emergency_contact ?? '';
                $formData['emergency_phone'] = $employee->emergency_phone ?? '';
                
                \Log::info('入职登记表数据: ' . json_encode($formData));
                
                return response()->json([
                    'success' => true,
                    'data' => $formData
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => null
            ]);
        } catch (\Exception $e) {
            \Log::error('获取入职登记表失败: ' . $e->getMessage());
            \Log::error('错误堆栈: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取员工查看详情（一次性返回所有需要的数据）
     */
    public function getViewDetails(Request $request, $id)
    {
        try {
            $employee = Employee::with(['projects', 'socialSecurityRegion', 'medicalInsuranceRegion', 'housingFundRegion', 'housingFundConfig'])
                ->findOrFail($id);

            $result = [
                'employee' => $employee->toArray(),
                'project_regions' => [],
                'document_sets' => [],
                'current_document_set_id' => null,
                'housing_fund_configs' => [],
                'other_insurance_policies' => [],
                'large_medical_insurance_configs' => [],
                'onboarding_form' => null,
                'registration_form_type' => 'onboarding'  // 默认入职登记表
            ];

            // 获取活跃项目ID（只取状态为active的项目）
            $activeProject = $employee->projects()->wherePivot('status', 'active')->first();
            $projectId = $activeProject ? $activeProject->id : null;
            
            // 如果没有活跃项目，尝试使用 project_ids 字段
            if (!$projectId && !empty($employee->project_ids)) {
                $projectId = is_array($employee->project_ids) ? ($employee->project_ids[0] ?? null) : $employee->project_ids;
            }
            
            if ($projectId) {
                $project = Project::find($projectId);

                if ($project) {
                    $projectRelation = $employee->projects->first(function ($item) use ($projectId) {
                        return (int) $item->id === (int) $projectId;
                    });

                    if (Schema::hasTable('project_document_sets')) {
                        $documentSets = $project->documentSets()->get(['id', 'project_id', 'set_name', 'sort_order', 'is_default']);
                        $currentDocumentSetId = null;
                        if ($projectRelation && $projectRelation->pivot && isset($projectRelation->pivot->document_set_id) && $projectRelation->pivot->document_set_id) {
                            $currentDocumentSetId = (int) $projectRelation->pivot->document_set_id;
                        }
                        if (!$currentDocumentSetId) {
                            $currentDocumentSetId = optional($documentSets->firstWhere('is_default', true))->id
                                ?: optional($documentSets->first())->id;
                        }

                        $result['document_sets'] = $documentSets->toArray();
                        $result['current_document_set_id'] = $currentDocumentSetId;
                        $result['employee']['project_document_set_id'] = $currentDocumentSetId;
                    }

                    // 获取项目的登记表类型设置
                    $result['registration_form_type'] = $project->registration_form_type ?? 'onboarding';
                    
                    // 1. 获取社保地区
                    $socialSecurityRegionIds = $project->social_security_regions ?? [];
                    if (!empty($socialSecurityRegionIds)) {
                        $regions = \App\Models\SocialSecurityRegion::whereIn('id', $socialSecurityRegionIds)
                            ->with(['socialSecurityTypes'])
                            ->get();
                        $result['project_regions']['social_security'] = $regions->map(function ($region) {
                            return [
                                'id' => $region->id,
                                'region_name' => $region->name,
                                'name' => $region->name,
                                'account_set_id' => $region->account_set_id,
                                'adjustment_base' => $region->adjustment_base,
                                'effective_date' => $region->effective_date,
                                'social_security_types' => $region->socialSecurityTypes
                            ];
                        })->toArray();
                    } else {
                        $result['project_regions']['social_security'] = [];
                    }

                    // 2. 获取医保地区
                    $medicalInsuranceRegionIds = $project->medical_insurance_regions ?? [];
                    if (!empty($medicalInsuranceRegionIds)) {
                        $regions = \App\Models\MedicalInsuranceRegion::whereIn('id', $medicalInsuranceRegionIds)
                            ->with(['medicalInsuranceTypes'])
                            ->get();
                        $result['project_regions']['medical_insurance'] = $regions->map(function ($region) {
                            $basicMedicalRatio = 0;
                            $supplementaryMedicalRatio = 0;
                            
                            if ($region->medicalInsuranceTypes) {
                                foreach ($region->medicalInsuranceTypes as $type) {
                                    if (strpos($type->name, '基本') !== false || strpos($type->name, '基础') !== false) {
                                        $basicMedicalRatio += $type->employee_ratio + $type->company_ratio;
                                    } elseif (strpos($type->name, '补充') !== false) {
                                        $supplementaryMedicalRatio += $type->employee_ratio + $type->company_ratio;
                                    }
                                }
                            }
                            
                            return [
                                'id' => $region->id,
                                'region_name' => $region->name,
                                'name' => $region->name,
                                'account_set_id' => $region->account_set_id,
                                'basic_medical_ratio' => $basicMedicalRatio,
                                'supplementary_medical_ratio' => $supplementaryMedicalRatio,
                                'medical_insurance_types' => $region->medicalInsuranceTypes
                            ];
                        })->toArray();
                    } else {
                        $result['project_regions']['medical_insurance'] = [];
                    }

                    // 3. 获取公积金地区
                    $housingFundRegionIds = $project->housing_fund_regions ?? [];
                    if (!empty($housingFundRegionIds)) {
                        $regions = \App\Models\HousingFundRegion::whereIn('id', $housingFundRegionIds)->get();
                        $result['project_regions']['housing_fund'] = $regions->map(function ($region) {
                            return [
                                'id' => $region->id,
                                'region_name' => $region->region_name,
                                'name' => $region->region_name,
                                'account_set_id' => $region->account_set_id,
                                'base_amount' => $region->base_amount,
                                'employee_ratio' => $region->employee_ratio,
                                'company_ratio' => $region->company_ratio
                            ];
                        })->toArray();

                        // 如果员工已选择公积金地区，获取该地区的配置
                        if ($employee->housing_fund_region_id) {
                            $housingFundRegion = \App\Models\HousingFundRegion::find($employee->housing_fund_region_id);
                            if ($housingFundRegion) {
                                $configs = $housingFundRegion->configs()->with('creator')
                                    ->orderBy('is_default', 'desc')
                                    ->orderBy('created_at', 'asc')
                                    ->get();
                                $result['housing_fund_configs'] = $configs->toArray();
                            }
                        }
                    } else {
                        $result['project_regions']['housing_fund'] = [];
                    }

                    // 4. 获取其他保险政策
                    $policies = $project->otherInsurancePolicies()->with('type')->get();
                    $result['other_insurance_policies'] = $policies->map(function ($policy) {
                        return [
                            'id' => $policy->id,
                            'name' => $policy->policy_name,
                            'type' => $policy->type ? $policy->type->name : '未知类型',
                            'coverage' => $policy->description ?: '暂无描述',
                            'employee_per_capita_cost' => $policy->employee_per_capita_cost,
                            'contact_name' => $policy->contact_name,
                            'contact_phone' => $policy->contact_phone,
                            'insurance_company' => $policy->insurance_company,
                            'status' => $policy->status,
                            'start_date' => $policy->start_date,
                            'end_date' => $policy->end_date
                        ];
                    })->toArray();

                    // 5. 获取大额医疗保险配置
                    $configs = $project->getResolvedLargeMedicalInsuranceConfigs();
                    $result['large_medical_insurance_configs'] = $configs->map(function ($config) {
                        return [
                            'id' => $config->id,
                            'region_name' => $config->region_name,
                            'calculation_type' => $config->calculation_type,
                            'calculation_type_text' => $config->calculation_type_text,
                            'base_source' => $config->base_source, // 特殊地区标识：config=特殊地区，employee=普通地区
                            'base_amount' => $config->base_amount, // 公司基数（特殊地区用）
                            'employee_base_amount' => $config->employee_base_amount, // 个人基数（特殊地区用）
                            'employee_ratio' => $config->employee_ratio,
                            'company_ratio' => $config->company_ratio,
                            'employee_amount' => $config->employee_amount,
                            'company_amount' => $config->company_amount,
                            'payment_cycle' => $config->payment_cycle,
                            'annual_payment_month' => $config->annual_payment_month,
                            'payment_cycle_text' => $config->payment_cycle_text,
                            'effective_date' => $config->effective_date,
                            'account_set_id' => $config->account_set_id
                        ];
                    })->toArray();
                }
            }

            // 6. 获取入职登记表
            $form = OnboardingForm::where('employee_id', $employee->id)->first();
            if ($form) {
                $formData = $form->toArray();
                $host = request()->getSchemeAndHttpHost();

                // 将签名路径转换为完整URL
                if (!empty($formData['signature'])) {
                    if (strpos($formData['signature'], 'http') === 0) {
                        // 已经是完整URL，不处理
                    } elseif (strpos($formData['signature'], 'uploads/') === 0) {
                        $formData['signature'] = $host . '/' . $formData['signature'];
                    } else {
                        $formData['signature'] = $host . '/storage/' . $formData['signature'];
                    }
                }
                // 将寸照路径转换为完整URL
                if (!empty($formData['photo'])) {
                    if (strpos($formData['photo'], 'http') === 0) {
                        // 已经是完整URL，不处理
                    } elseif (strpos($formData['photo'], 'uploads/') === 0) {
                        $formData['photo'] = $host . '/' . $formData['photo'];
                    } else {
                        $formData['photo'] = $host . '/storage/' . $formData['photo'];
                    }
                }
                $formData['bank_account'] = $employee->bank_account ?? '';
                $formData['bank_account_holder'] = $employee->bank_account_holder ?? '';
                $formData['bank_name'] = $employee->bank_name ?? '';
                $formData['bank_branch'] = $employee->bank_branch ?? '';
                $result['onboarding_form'] = $formData;
            }

            // 7. 获取审核中的工资调整数据
            $pendingSalaryAdjustment = ApprovalInstance::where('business_type', 'employee_salary_adjustment')
                ->where('business_id', $employee->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            if ($pendingSalaryAdjustment) {
                $result['pending_salary_adjustment'] = [
                    'id' => $pendingSalaryAdjustment->id,
                    'basic_salary' => $pendingSalaryAdjustment->new_basic_salary,
                    'salary_items' => $pendingSalaryAdjustment->new_salary_items,
                    'reason' => $pendingSalaryAdjustment->salary_adjustment_reason,
                    'created_at' => $pendingSalaryAdjustment->created_at,
                ];
            } else {
                $result['pending_salary_adjustment'] = null;
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \Log::error('获取员工查看详情失败: ' . $e->getMessage());
            \Log::error('错误堆栈: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 测试生成入职登记表PDF格式
     */
    public function testOnboardingFormPdf()
    {
        try {
            // 创建测试数据对象
            $testForm = new \stdClass();
            
            // 基本信息
            $testForm->registration_date = '2025-10-31';
            $testForm->name = '张三';
            $testForm->gender = 'male';
            $testForm->ethnicity = '汉族';
            $testForm->political_status = '群众';
            $testForm->place_of_origin = '广东深圳';
            $testForm->birth_date = '1990-04-20';
            $testForm->graduated_school = '深圳大学';
            $testForm->graduation_date = '2012-05-21';
            $testForm->education_level = '本科';
            $testForm->major = '计算机科学与技术';
            $testForm->degree = '学士';
            $testForm->technical_title = '中级工程师';
            $testForm->health_status = '良好';
            $testForm->height = 175;
            $testForm->weight = 70.5;
            $testForm->marital_status = '已婚';
            $testForm->id_number = '430281200401096273';
            $testForm->current_residence = '深圳市南山区科技园';
            $testForm->household_registration = '湖南省长沙市';
            
            // 学习简历
            $testForm->education_background = [
                [
                    'start_date' => '2006.09',
                    'end_date' => '2009.06',
                    'school' => '深圳市第一中学',
                    'level' => '高中',
                    'reference' => '李老师'
                ],
                [
                    'start_date' => '2009.09',
                    'end_date' => '2012.06',
                    'school' => '深圳大学',
                    'level' => '本科',
                    'reference' => '王教授'
                ]
            ];
            
            // 工作经历
            $testForm->work_experience = [
                [
                    'start_date' => '2012.07',
                    'end_date' => '2018.06',
                    'employer' => '深圳科技有限公司',
                    'job_content' => '软件开发',
                    'certifier' => '张经理'
                ],
                [
                    'start_date' => '2018.07',
                    'end_date' => '2024.12',
                    'employer' => '腾讯科技有限公司',
                    'job_content' => '高级工程师',
                    'certifier' => '李总监'
                ]
            ];
            
            // 家庭情况
            $testForm->family_info = [
                [
                    'name' => '李四',
                    'relationship' => '配偶',
                    'employer' => '深圳市人民医院',
                    'phone' => '13900139000'
                ],
                [
                    'name' => '张五',
                    'relationship' => '父亲',
                    'employer' => '退休',
                    'phone' => '13900139001'
                ],
                [
                    'name' => '王六',
                    'relationship' => '母亲',
                    'employer' => '退休',
                    'phone' => '13900139002'
                ]
            ];
            
            // 其他信息
            $testForm->position = '软件工程师';
            $testForm->desired_location = '深圳';
            $testForm->accept_assignment = true;
            $testForm->contact_address = '深圳市南山区科技园南路100号';
            $testForm->contact_phone = '13800138000';
            $testForm->remarks = '熟悉Java、Python等编程语言';
            $testForm->signature = null;
            
            // 使用项目标准模板生成PDF
            $html = view('pdf.onboarding_form', ['form' => $testForm])->render();
            
            // 使用mpdf生成PDF（支持中文）
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'margin_right' => 15,
            ]);
            
            // 设置自动字体替换
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            
            $mpdf->WriteHTML($html);
            
            // 记录PDF生成信息用于动态计算签名位置
            $signatureTextY = $mpdf->y;
            $pageHeight = $mpdf->h;
            $fromBottom = $pageHeight - $signatureTextY;
            
            // 输出PDF
            $pdfContent = $mpdf->Output('', 'S');  // 'S'返回字符串
            
            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="测试入职登记表.pdf"');
                
        } catch (\Exception $e) {
            \Log::error('生成测试PDF失败: ' . $e->getMessage());
            \Log::error('错误堆栈: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => '生成失败: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    /**
     * 获取不带签名的PDF供前端合成
     */
    public function getPdfForMerge()
    {
        try {
            // 创建测试数据（与testOnboardingFormPdf相同的数据）
            $testForm = new \stdClass();
            
            $testForm->registration_date = '2025-10-31';
            $testForm->name = '张三';
            $testForm->gender = 'male';
            $testForm->ethnicity = '汉';
            $testForm->political_status = '群众';
            $testForm->place_of_origin = '广东深圳';
            $testForm->birth_date = '1990-04-20';
            $testForm->graduated_school = '深圳大学';
            $testForm->graduation_date = '2012-05-21';
            $testForm->education_level = '本科';
            $testForm->major = '计算机科学与技术';
            $testForm->degree = '学士';
            $testForm->technical_title = '工程师';
            $testForm->health_status = '健康';
            $testForm->height = 175;
            $testForm->weight = 70.5;
            $testForm->marital_status = '已婚';
            $testForm->id_number = '440306199004201234';
            $testForm->current_address = '深圳市福田区彩田路100号';
            $testForm->household_register_address = '广东省深圳市';
            
            $testForm->education_background = [
                ['start_time' => '2006-09', 'end_time' => '2009-06', 'school' => '深圳市第一中学', 'level' => '高中', 'witness' => '张老师'],
                ['start_time' => '2009-09', 'end_time' => '2012-06', 'school' => '深圳大学', 'level' => '本科', 'witness' => '李教授']
            ];
            
            $testForm->work_experience = [
                ['start_time' => '2012-07', 'end_time' => '2018-06', 'company' => '某某科技有限公司', 'job_content' => '负责公司核心产品的开发和维护。', 'witness' => '赵经理'],
                ['start_time' => '2018-07', 'end_time' => '2024-12', 'company' => '某某互联网公司', 'job_content' => '担任技术负责人，负责团队管理。', 'witness' => '钱总监']
            ];
            
            $testForm->family_info = [
                ['name' => '王五', 'relationship' => '父亲', 'employer' => '某某企业公司', 'phone' => '13900139001'],
                ['name' => '王六', 'relationship' => '母亲', 'employer' => '退休', 'phone' => '13900139002']
            ];
            
            $testForm->position = '软件工程师';
            $testForm->desired_location = '深圳';
            $testForm->accept_assignment = true;
            $testForm->contact_address = '深圳市南山区科技园南路100号';
            $testForm->contact_phone = '13800138000';
            $testForm->remarks = '熟悉Java、Python等编程语言';
            
            // 关键：不设置签名，生成不带签名的PDF
            $testForm->signature = null;
            
            // 生成PDF
            $html = view('pdf.onboarding_form', ['form' => $testForm])->render();
            
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'margin_right' => 15,
            ]);
            
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            $mpdf->WriteHTML($html);
            
            // 计算"本人签名："距底部的距离用于动态签名位置
            $signatureTextY = $mpdf->y;
            $pageHeight = $mpdf->h;
            $fromBottom = $pageHeight - $signatureTextY;
            
            // 获取PDF内容并转为base64
            $pdfContent = $mpdf->Output('', 'S');
            $pdfBase64 = base64_encode($pdfContent);
            
            // 读取签名图片并转为base64（避免前端CORS问题）
            $signaturePath = public_path('storage/signatures/69131df2d123e_1762860530.png');
            $signatureBase64 = null;
            
            if (file_exists($signaturePath)) {
                $signatureData = file_get_contents($signaturePath);
                $signatureBase64 = base64_encode($signatureData);
                \Log::info('✅ 签名图片已读取，base64长度: ' . strlen($signatureBase64));
            } else {
                \Log::warning('⚠️ 签名图片不存在: ' . $signaturePath);
            }
            
            // 使用记录的位置计算签名坐标（将mm转换为点：1mm ≈ 2.83点）
            $signatureYFromBottom = $fromBottom * 2.83;
            
            $signaturePosition = [
                'x' => 115,
                'y' => round($signatureYFromBottom),
                'width' => 50,
                'height' => 20,
                'from_bottom' => true,
            ];
            
            // 返回JSON数据
            return response()->json([
                'success' => true,
                'data' => [
                    'pdf_base64' => $pdfBase64,
                    'signature_base64' => $signatureBase64,
                    'employee_name' => $testForm->name,
                    'signature_position' => $signaturePosition,  // 新增：签名位置信息
                ],
                'message' => 'PDF数据获取成功'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('获取PDF数据失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 批量获取员工登记表PDF数据（供前端合成签名）
     * 智能选择入职登记表或从业人员登记表
     */
    public function getBatchPdfsForMerge(Request $request)
    {
        try {
            $employeeIds = $request->input('employee_ids', []);
            $requestedFormType = $request->input('form_type');
            
            if (empty($employeeIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '未提供员工ID'
                ], 400);
            }
            
            $results = [];
            $onboardingPdfService = new \App\Services\OnboardingFormPdfService();
            $registrationPdfService = new \App\Services\RegistrationFormPdfService();
            
            foreach ($employeeIds as $employeeId) {
                try {
                    $employee = Employee::with(['onboardingForm', 'registrationForm', 'projects'])->findOrFail($employeeId);
                    
                    // 获取登记表类型：前端明确指定时优先，其次按项目配置，最后按已有表单兜底。
                    $formType = in_array($requestedFormType, ['onboarding', 'registration'], true)
                        ? $requestedFormType
                        : 'onboarding';

                    if (!in_array($requestedFormType, ['onboarding', 'registration'], true)) {
                        $activeProject = $employee->projects()->wherePivot('status', 'active')->first();
                        if ($activeProject) {
                            $formType = $activeProject->registration_form_type ?? 'onboarding';
                        } elseif ($employee->projects && $employee->projects->count() > 0) {
                            $project = $employee->projects->first();
                            $formType = $project->registration_form_type ?? 'onboarding';
                        } elseif ($employee->registrationForm && !$employee->onboardingForm) {
                            $formType = 'registration';
                        }
                    }
                    
                    // 根据类型选择PDF服务和表单数据
                    if ($formType === 'registration') {
                        $pdfData = $registrationPdfService->generatePdf($employeeId);
                        $form = $employee->registrationForm;
                    } else {
                        $pdfData = $onboardingPdfService->generatePdf($employeeId);
                        $form = $employee->onboardingForm;
                    }
                    
                    $pdfBase64 = base64_encode($pdfData['pdf_content']);
                    $signaturePosition = $pdfData['signature_position'] ?? [
                        'x' => 115,
                        'y' => 142,
                        'width' => 50,
                        'height' => 20,
                        'from_bottom' => true,
                    ];
                    
                    // 获取签名图片的base64（兼容多种历史路径格式）
                    $signatureBase64 = null;
                    if ($form && $form->signature) {
                        $rawSignature = trim($form->signature);

                        // 兼容直接存了 data:image/base64 的历史数据
                        if (preg_match('/^data:image\/\w+;base64,/', $rawSignature)) {
                            $signatureBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $rawSignature);
                        } else {
                            // 兼容完整URL（提取 path 部分后按本地路径解析）
                            if (preg_match('/^https?:\/\//i', $rawSignature)) {
                                $urlPath = parse_url($rawSignature, PHP_URL_PATH);
                                if (!empty($urlPath)) {
                                    $rawSignature = $urlPath;
                                }
                            }

                            $normalizedSignature = ltrim($rawSignature, '/');
                            $signatureCandidates = array_values(array_filter(array_unique([
                                public_path($normalizedSignature),                 // uploads/signatures/xxx.png
                                public_path('storage/' . $normalizedSignature),    // signatures/xxx.png
                                storage_path('app/public/' . $normalizedSignature), // 兜底：storage/app/public
                            ])));

                            foreach ($signatureCandidates as $signaturePath) {
                                if (is_file($signaturePath)) {
                                    $signatureData = file_get_contents($signaturePath);
                                    $signatureBase64 = base64_encode($signatureData);
                                    break;
                                }
                            }

                            if (!$signatureBase64) {
                                \Log::warning('签名文件不存在，前端将无法合成签名', [
                                    'employee_id' => $employeeId,
                                    'signature' => $form->signature,
                                    'candidates' => $signatureCandidates,
                                ]);
                            }
                        }
                    }
                    
                    // 获取寸照图片的base64（仅入职登记表有，兼容URL/相对路径）
                    $photoBase64 = null;
                    if ($formType === 'onboarding' && $form && $form->photo) {
                        $rawPhoto = trim($form->photo);
                        if (preg_match('/^https?:\/\//i', $rawPhoto)) {
                            $urlPath = parse_url($rawPhoto, PHP_URL_PATH);
                            if (!empty($urlPath)) {
                                $rawPhoto = $urlPath;
                            }
                        }

                        $normalizedPhoto = ltrim($rawPhoto, '/');
                        $photoCandidates = array_values(array_filter(array_unique([
                            public_path($normalizedPhoto),                 // uploads/photos/xxx.jpg
                            public_path('storage/' . $normalizedPhoto),    // photos/xxx.jpg
                            storage_path('app/public/' . $normalizedPhoto), // 兜底
                        ])));

                        foreach ($photoCandidates as $photoPath) {
                            if (is_file($photoPath)) {
                                $photoData = file_get_contents($photoPath);
                                $photoBase64 = base64_encode($photoData);
                                break;
                            }
                        }
                        
                        if (!$photoBase64) {
                            \Log::warning('寸照文件不存在，前端将跳过寸照合成', [
                                'employee_id' => $employeeId,
                                'photo' => $form->photo,
                                'candidates' => $photoCandidates,
                            ]);
                        }
                    }
                    
                    $results[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->name,
                        'form_type' => $formType,
                        'pdf_base64' => $pdfBase64,
                        'signature_base64' => $signatureBase64,
                        'signature_position' => $signaturePosition,
                        'photo_base64' => $photoBase64,
                        'photo_position' => [
                            'x' => 483,  // 右侧表格内，往左移一点
                            'y' => 595,  // 往上移一点
                            'width' => 70,
                            'height' => 95,
                        ],
                    ];
                    
                } catch (\Exception $e) {
                    \Log::error("获取员工 {$employeeId} 的PDF数据失败: " . $e->getMessage());
                    $results[] = [
                        'employee_id' => $employeeId,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            \Log::error('批量获取PDF数据失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 原始的测试生成入职登记表PDF格式
     */
    public function testOnboardingFormPdfFull()
    {
        try {
            // 创建测试数据
            $testForm = new \stdClass();
            
            // 基本信息
            $testForm->registration_date = '2025-10-31';
            $testForm->name = '张三';
            $testForm->gender = 'male';
            $testForm->ethnicity = '汉';
            $testForm->political_status = '群众';
            $testForm->place_of_origin = '广东深圳';
            $testForm->birth_date = '1990-04-20';
            $testForm->graduated_school = '深圳大学';
            $testForm->graduation_date = '2012-05-21';
            $testForm->education_level = '本科';
            $testForm->major = '计算机科学与技术';
            $testForm->degree = '学士';
            $testForm->technical_title = '中级工程师';
            $testForm->health_status = '良好';
            $testForm->height = 175;
            $testForm->weight = 70.5;
            $testForm->marital_status = '已婚';
            $testForm->id_number = '430281200401096273';
            $testForm->current_residence = '深圳市南山区科技园';
            $testForm->household_registration = '湖南省长沙市';
            
            // 学习简历
            $testForm->education_background = [
                [
                    'start_date' => '2006.09',
                    'end_date' => '2009.06',
                    'school' => '深圳市第一中学',
                    'level' => '高中',
                    'reference' => '李老师'
                ],
                [
                    'start_date' => '2009.09',
                    'end_date' => '2012.06',
                    'school' => '深圳大学',
                    'level' => '本科',
                    'reference' => '王教授'
                ]
            ];
            
            // 工作经历
            $testForm->work_experience = [
                [
                    'start_date' => '2012.07',
                    'end_date' => '2018.06',
                    'employer' => '深圳科技有限公司',
                    'job_content' => '软件开发',
                    'certifier' => '张经理'
                ],
                [
                    'start_date' => '2018.07',
                    'end_date' => '2024.12',
                    'employer' => '腾讯科技有限公司',
                    'job_content' => '高级工程师',
                    'certifier' => '李总监'
                ]
            ];
            
            // 家庭情况
            $testForm->family_info = [
                [
                    'name' => '李四',
                    'relationship' => '配偶',
                    'employer' => '深圳市人民医院',
                    'phone' => '13900139000'
                ],
                [
                    'name' => '张五',
                    'relationship' => '父亲',
                    'employer' => '退休',
                    'phone' => '13900139001'
                ],
                [
                    'name' => '王六',
                    'relationship' => '母亲',
                    'employer' => '退休',
                    'phone' => '13900139002'
                ]
            ];
            
            // 其他信息
            $testForm->position = '软件工程师';
            $testForm->desired_location = '深圳';
            $testForm->accept_assignment = true;
            $testForm->contact_address = '深圳市南山区科技园南路100号';
            $testForm->contact_phone = '13800138000';
            $testForm->remarks = '熟悉Java、Python等编程语言';
            $testForm->signature = null; // 暂时没有签名
            
            // 生成PDF（使用测试模板）
            $html = view('pdf.test_onboarding_form', ['form' => $testForm])->render();
            
            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('chroot', [
                storage_path('app/public'),
                public_path(),
            ]);
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            return response($dompdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="测试入职登记表.pdf"');
                
        } catch (\Exception $e) {
            \Log::error('生成测试PDF失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '生成失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量导出入职登记表PDF
     */
    public function exportOnboardingFormsPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '参数错误',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pdfService = new \App\Services\OnboardingFormPdfService();
            
            if (count($request->employee_ids) === 1) {
                // 单个员工直接下载PDF
                $employeeId = $request->employee_ids[0];
                $employee = Employee::findOrFail($employeeId);
                $pdfContent = $pdfService->generatePdf($employeeId);
                
                $fileName = $employee->name . '_入职登记表.pdf';
                
                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . urlencode($fileName) . '"',
                ]);
            } else {
                // 多个员工打包成ZIP
                $result = $pdfService->generateMultiplePdfs($request->employee_ids);
                
                return response()->download($result['path'])->deleteFileAfterSend(true);
            }
        } catch (\Exception $e) {
            \Log::error('导出入职登记表PDF失败: ' . $e->getMessage());
            \Log::error('错误堆栈: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量下载员工资料 - 返回JSON格式数据，前端用JSZip打包
     */
    public function batchDownloadDocuments(Request $request)
    {
        try {
            $employeeIds = $request->input('employee_ids', []);
            
            if (empty($employeeIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择要下载资料的员工'
                ], 400);
            }
            
            $result = [];
            
            // 遍历每个员工，收集他们的资料
            foreach ($employeeIds as $employeeId) {
                $employee = Employee::find($employeeId);
                if (!$employee) continue;
                
                // 查找该员工的所有资料
                $documents = \App\Models\EmployeeDocument::where('employee_id', $employeeId)->get();
                
                if ($documents->isEmpty()) continue;
                
                $employeeFiles = [];
                
                // 读取文件内容并转为base64
                foreach ($documents as $doc) {
                    $sourcePath = public_path($doc->file_path);
                    
                    if (file_exists($sourcePath)) {
                        $fileContent = file_get_contents($sourcePath);
                        $extension = pathinfo($doc->file_path, PATHINFO_EXTENSION);
                        
                        $employeeFiles[] = [
                            'name' => $doc->document_name . '.' . $extension,
                            'content' => base64_encode($fileContent),
                            'original_name' => $doc->original_filename
                        ];
                    }
                }
                
                if (!empty($employeeFiles)) {
                    $result[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->name,
                        'employee_number' => $employee->employee_number,
                        'files' => $employeeFiles
                    ];
                }
            }
            
            if (empty($result)) {
                return response()->json([
                    'success' => false,
                    'message' => '所选员工没有上传的资料'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            \Log::error('批量下载员工资料失败: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => '下载失败: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadAllDocuments(Request $request)
    {
        if ($response = $this->checkPermission('employees.view')) {
            return $response;
        }

        try {
            $currentAccountSetId = (int) $request->input('current_account_set_id');
            if (!$currentAccountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 422);
            }

            @set_time_limit(0);

            $query = Employee::query()
                ->select(['id', 'name', 'employee_number', 'account_set_id'])
                ->with(['documents' => function ($documentQuery) {
                    $documentQuery->select(['id', 'employee_id', 'document_name', 'original_filename', 'file_path']);
                }])
                ->where('account_set_id', $currentAccountSetId);

            if ($request->filled('project_id')) {
                $projectId = (int) $request->input('project_id');
                $query->whereHas('projects', function ($projectQuery) use ($projectId) {
                    $projectQuery->where('projects.id', $projectId);
                });
            }

            $personnelStatus = $request->input('personnel_status', $request->input('contract_status'));
            $this->applyPersonnelStatusFilter($query, $personnelStatus);

            $contractSignStatus = $request->input('contract_sign_status');
            $this->applyLaborContractSignFilter($query, $contractSignStatus);

            if ($request->filled('search')) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('id_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $employees = $query
                ->whereHas('documents')
                ->orderBy('employee_number', 'desc')
                ->get();

            if ($employees->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '当前条件下没有可下载的员工资料'
                ], 404);
            }

            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'employee-documents-' . uniqid('', true) . '.zip';
            $zip = new \ZipArchive();
            $openResult = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if ($openResult !== true) {
                throw new \RuntimeException('压缩包创建失败');
            }

            $fileCount = 0;

            foreach ($employees as $employee) {
                $employeeFolder = $this->sanitizeZipPathSegment($employee->name, '未命名员工')
                    . '_'
                    . $this->sanitizeZipPathSegment($employee->employee_number, 'ID' . $employee->id);

                foreach ($employee->documents as $document) {
                    if (empty($document->file_path)) {
                        continue;
                    }

                    $sourcePath = public_path($document->file_path);
                    if (!is_file($sourcePath)) {
                        continue;
                    }

                    $documentFolder = $this->sanitizeZipPathSegment($document->document_name, '未分类资料');
                    $originalFilename = $this->sanitizeZipPathSegment(
                        $document->original_filename ?: basename($document->file_path),
                        '未命名文件'
                    );

                    $zip->addFile(
                        $sourcePath,
                        $employeeFolder . '/' . $documentFolder . '/' . $document->id . '_' . $originalFilename
                    );
                    $fileCount++;
                }
            }

            $zip->close();

            if ($fileCount === 0) {
                @unlink($zipPath);

                return response()->json([
                    'success' => false,
                    'message' => '当前条件下没有可下载的员工资料'
                ], 404);
            }

            $downloadName = '员工资料_' . now()->format('YmdHis') . '.zip';
            $fileSize = @filesize($zipPath) ?: null;

            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
            }

            return response()->streamDownload(function () use ($zipPath) {
                $handle = fopen($zipPath, 'rb');
                if ($handle) {
                    while (!feof($handle)) {
                        echo fread($handle, 8192);
                        flush();
                    }
                    fclose($handle);
                }

                @unlink($zipPath);
            }, $downloadName, array_filter([
                'Content-Type' => 'application/zip',
                'Content-Length' => $fileSize,
                'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]));
        } catch (\Exception $e) {
            if (!empty($zipPath) && is_file($zipPath)) {
                @unlink($zipPath);
            }

            \Log::error('一键下载员工资料失败: ' . $e->getMessage(), [
                'request_params' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '下载失败: ' . $e->getMessage()
            ], 500);
        }
    }

    private function sanitizeZipPathSegment($value, string $fallback): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }

        $value = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value, ". \t\n\r\0\x0B");

        return $value !== '' ? $value : $fallback;
    }
    
    /**
     * 递归添加文件到ZIP
     */
    private function addFilesToZip($zip, $dir, $zipDir)
    {
        if (!is_dir($dir)) return;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $dir . '/' . $file;
            $zipPath = $zipDir ? $zipDir . '/' . $file : $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipPath);
                $this->addFilesToZip($zip, $filePath, $zipPath);
            } else {
                $zip->addFile($filePath, $zipPath);
            }
        }
    }
    
    /**
     * 递归删除目录
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) return;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($dir);
    }

    /**
     * 获取员工的从业人员登记表
     */
    public function getRegistrationForm($id)
    {
        try {
            $employee = Employee::findOrFail($id);
            $form = \App\Models\EmployeeRegistrationForm::where('employee_id', $employee->id)->first();

            if ($form) {
                $formData = $form->toArray();
                $host = request()->getSchemeAndHttpHost();
                
                // 将签名路径转换为完整URL
                if (!empty($formData['signature'])) {
                    if (strpos($formData['signature'], 'http') === 0) {
                        // 已经是完整URL，不处理
                    } elseif (strpos($formData['signature'], 'uploads/') === 0) {
                        $formData['signature'] = $host . '/' . $formData['signature'];
                    } else {
                        $formData['signature'] = $host . '/storage/' . $formData['signature'];
                    }
                }

                if (!empty($formData['photo'])) {
                    if (strpos($formData['photo'], 'http') === 0) {
                        // 已经是完整URL，不处理
                    } elseif (strpos($formData['photo'], 'uploads/') === 0) {
                        $formData['photo'] = $host . '/' . $formData['photo'];
                    } else {
                        $formData['photo'] = $host . '/storage/' . $formData['photo'];
                    }
                }

                $formData['bank_account'] = $formData['bank_account'] ?? $employee->bank_account;
                $formData['bank_account_holder'] = $formData['bank_account_holder'] ?? $employee->bank_account_holder;
                $formData['bank_name'] = $formData['bank_name'] ?? $employee->bank_name;
                $formData['bank_branch'] = $formData['bank_branch'] ?? $employee->bank_branch;
                
                return response()->json([
                    'success' => true,
                    'data' => $formData
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => null
            ]);
        } catch (\Exception $e) {
            \Log::error('获取从业人员登记表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出从业人员登记表PDF
     */
    public function exportRegistrationFormPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '参数错误',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pdfService = new \App\Services\RegistrationFormPdfService();
            
            if (count($request->employee_ids) === 1) {
                $employeeId = $request->employee_ids[0];
                $pdfData = $pdfService->generatePdf($employeeId);
                
                return response($pdfData['pdf_content'], 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . urlencode($pdfData['filename']) . '"',
                ]);
            } else {
                $result = $pdfService->generateMultiplePdfs($request->employee_ids);
                return response()->download($result['path'])->deleteFileAfterSend(true);
            }
        } catch (\Exception $e) {
            \Log::error('导出从业人员登记表PDF失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 测试从业人员登记表PDF（使用示例数据）
     */
    public function testRegistrationFormPdf()
    {
        try {
            $pdfService = new \App\Services\RegistrationFormPdfService();
            $pdfData = $pdfService->generateTestPdf();
            
            return response($pdfData['pdf_content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . urlencode($pdfData['filename']) . '"',
            ]);
        } catch (\Exception $e) {
            \Log::error('测试从业人员登记表PDF失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '生成失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 智能批量导出登记表PDF（根据员工所属项目自动选择入职登记表或从业人员登记表）
     */
    public function exportSmartRegistrationPdfs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '参数错误',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $zip = new \ZipArchive();
            $zipFileName = '登记表_' . date('YmdHis') . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);
            
            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
            
            // 如果只有一个员工，直接返回PDF
            if (count($request->employee_ids) === 1) {
                $employeeId = $request->employee_ids[0];
                $employee = Employee::with('projects')->findOrFail($employeeId);
                
                // 获取员工所属项目的登记表类型（优先使用活跃项目）
                $formType = 'onboarding';
                $activeProject = $employee->projects()->wherePivot('status', 'active')->first();
                if ($activeProject) {
                    $formType = $activeProject->registration_form_type ?? 'onboarding';
                } elseif ($employee->projects && $employee->projects->count() > 0) {
                    $project = $employee->projects->first();
                    $formType = $project->registration_form_type ?? 'onboarding';
                }
                
                if ($formType === 'registration') {
                    $pdfService = new \App\Services\RegistrationFormPdfService();
                    $pdfData = $pdfService->generatePdf($employeeId);
                } else {
                    $pdfService = new \App\Services\OnboardingFormPdfService();
                    $pdfData = $pdfService->generatePdf($employeeId);
                }
                
                return response($pdfData['pdf_content'], 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . urlencode($pdfData['filename']) . '"',
                ]);
            }
            
            // 多个员工，打包成ZIP
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('无法创建ZIP文件');
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($request->employee_ids as $employeeId) {
                try {
                    $employee = Employee::with('projects')->findOrFail($employeeId);
                    
                    // 获取员工所属项目的登记表类型（优先使用活跃项目）
                    $formType = 'onboarding';
                    $activeProject = $employee->projects()->wherePivot('status', 'active')->first();
                    if ($activeProject) {
                        $formType = $activeProject->registration_form_type ?? 'onboarding';
                    } elseif ($employee->projects && $employee->projects->count() > 0) {
                        $project = $employee->projects->first();
                        $formType = $project->registration_form_type ?? 'onboarding';
                    }
                    
                    if ($formType === 'registration') {
                        $pdfService = new \App\Services\RegistrationFormPdfService();
                    } else {
                        $pdfService = new \App\Services\OnboardingFormPdfService();
                    }
                    
                    $pdfData = $pdfService->generatePdf($employeeId);
                    $zip->addFromString($pdfData['filename'], $pdfData['pdf_content']);
                    $successCount++;
                } catch (\Exception $e) {
                    \Log::error("生成登记表PDF失败: 员工ID {$employeeId}", [
                        'error' => $e->getMessage()
                    ]);
                    $errorCount++;
                }
            }
            
            $zip->close();
            
            if ($successCount === 0) {
                throw new \Exception('所有员工的PDF生成均失败');
            }
            
            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            \Log::error('智能批量导出登记表PDF失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取员工的大额医疗保险状态
     * 用于判断是否显示"开启大额"按钮
     */
    public function getLargeMedicalStatus($id)
    {
        try {
            $employee = Employee::with([
                'largeMedicalInsuranceConfigRelation',
                'projects'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $this->buildLargeMedicalStatusData($employee)
            ]);

        } catch (\Exception $e) {
            \Log::error('获取大额医疗保险状态失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取状态失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 开启大额医疗保险
     * 创建一条新的增减任务，大额开关默认开启
     */
    public function enableLargeMedical(Request $request, $id)
    {
        try {
            $employee = Employee::with([
                'largeMedicalInsuranceConfigRelation',
                'socialSecurityRegion.socialSecurityTypes',
                'medicalInsuranceRegion.medicalInsuranceTypes',
                'housingFundConfig',
                'projects'
            ])->findOrFail($id);

            // 验证员工是否有大额医疗保险配置
            if (!$employee->large_medical_insurance_config_id) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工未配置大额医疗保险'
                ], 400);
            }

            // 检查是否已经参保
            $insurancePersonnel = \App\Models\InsurancePersonnel::where('employee_id', $employee->id)
                ->where('large_medical_insurance_enabled', true)
                ->where('status', 'active')
                ->first();

            if ($insurancePersonnel) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工已参保大额医疗保险'
                ], 400);
            }

            // 检查是否有待处理的"开启大额医疗保险"任务（无论开关状态）
            $pendingTask = $this->findPendingLargeMedicalTask($employee);

            if ($pendingTask) {
                return response()->json([
                    'success' => false,
                    'message' => '已有待处理的大额医疗保险任务'
                ], 400);
            }

            // 检查是否已入职
            $completedTask = \App\Models\InsuranceChange::where('employee_id', $employee->id)
                ->where('status', 'completed')
                ->where('fully_confirmed', true)
                ->first();

            if (!$completedTask) {
                return response()->json([
                    'success' => false,
                    'message' => '员工尚未完成入职流程，无法开启大额医疗保险'
                ], 400);
            }

            // 获取员工的第一个活跃项目
            $activeProject = $employee->projects()
                ->wherePivot('status', 'active')
                ->first();

            if (!$activeProject) {
                return response()->json([
                    'success' => false,
                    'message' => '员工没有活跃的项目'
                ], 400);
            }

            // 获取账套ID
            $accountSetId = $employee->account_set_id;

            DB::beginTransaction();

            // 创建新的增减任务
            // 转换性别：male -> 1, female -> 2
            $genderValue = $employee->gender === 'male' ? 1 : ($employee->gender === 'female' ? 2 : null);
            
            $change = \App\Models\InsuranceChange::create([
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_id_number' => $employee->id_number,
                'employee_gender' => $genderValue,
                'employee_birth_date' => $employee->birth_date,
                'employee_phone' => $employee->phone,
                'employee_status' => null,  // 避免类型错误
                'project_id' => $activeProject->id,
                'account_set_id' => $accountSetId,
                'change_type' => 'increase',  // 新增参保
                'status' => 'pending',
                'fully_confirmed' => false,
                // 保险地区和配置ID
                'social_security_region_id' => $employee->social_security_region_id,
                'medical_insurance_region_id' => $employee->medical_insurance_region_id,
                'housing_fund_region_id' => $employee->housing_fund_region_id,
                'housing_fund_config_id' => $employee->housing_fund_config_id,
                'large_medical_insurance_config_id' => $employee->large_medical_insurance_config_id,
                'large_medical_insurance_enabled' => false,  // 默认关闭，需要手动开启
                // 员工基数
                'employee_social_security_base' => $employee->social_security_base,
                'employee_medical_insurance_base' => $employee->medical_insurance_base,
                'employee_housing_fund_base' => $employee->housing_fund_base,
                'employee_large_medical_base' => $employee->large_medical_base,
                'employee_large_medical_company_base' => $employee->large_medical_company_base,
                // 变更摘要
                'change_summary' => '开启大额医疗保险',
                'change_details' => json_encode([
                    [
                        'category' => 'large_medical_insurance',
                        'action' => 'enabled',
                        'item' => '大额医疗保险',
                        'description' => '开启大额医疗保险参保'
                    ]
                ]),
                'created_by' => $request->user() ? $request->user()->id : null,
            ]);

            // 保存保险配置快照
            $change->saveCompleteInsuranceConfig();

            DB::commit();

            \Log::info('开启大额医疗保险任务创建成功', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'insurance_change_id' => $change->id
            ]);

            return response()->json([
                'success' => true,
                'message' => '已创建大额医疗保险开启任务，请在增减管理中确认处理',
                'data' => [
                    'insurance_change_id' => $change->id
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('开启大额医疗保险失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '开启失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 关闭大额医疗保险
     * 创建一条新的减保任务，待增减管理确认后正式退保
     */
    public function disableLargeMedical(Request $request, $id)
    {
        try {
            $employee = Employee::with([
                'largeMedicalInsuranceConfigRelation',
                'socialSecurityRegion.socialSecurityTypes',
                'medicalInsuranceRegion.medicalInsuranceTypes',
                'housingFundConfig',
                'projects'
            ])->findOrFail($id);

            if (!$employee->large_medical_insurance_config_id) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工未配置大额医疗保险'
                ], 400);
            }

            $insurancePersonnel = \App\Models\InsurancePersonnel::where('employee_id', $employee->id)
                ->where('large_medical_insurance_enabled', true)
                ->where('status', 'active')
                ->first();

            if (!$insurancePersonnel) {
                return response()->json([
                    'success' => false,
                    'message' => '该员工当前未参保大额医疗保险'
                ], 400);
            }

            $pendingTask = $this->findPendingLargeMedicalTask($employee);

            if ($pendingTask) {
                return response()->json([
                    'success' => false,
                    'message' => '已有待处理的大额医疗保险任务'
                ], 400);
            }

            $activeProject = $employee->projects()
                ->wherePivot('status', 'active')
                ->first();

            if (!$activeProject) {
                return response()->json([
                    'success' => false,
                    'message' => '员工没有活跃的项目'
                ], 400);
            }

            $accountSetId = $employee->account_set_id;

            DB::beginTransaction();

            $genderValue = $employee->gender === 'male' ? 1 : ($employee->gender === 'female' ? 2 : null);

            $change = \App\Models\InsuranceChange::create([
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_id_number' => $employee->id_number,
                'employee_gender' => $genderValue,
                'employee_birth_date' => $employee->birth_date,
                'employee_phone' => $employee->phone,
                'employee_status' => null,
                'project_id' => $activeProject->id,
                'account_set_id' => $accountSetId,
                'change_type' => 'decrease',
                'status' => 'pending',
                'fully_confirmed' => false,
                'social_security_region_id' => $employee->social_security_region_id,
                'medical_insurance_region_id' => $employee->medical_insurance_region_id,
                'housing_fund_region_id' => $employee->housing_fund_region_id,
                'housing_fund_config_id' => $employee->housing_fund_config_id,
                'large_medical_insurance_config_id' => $employee->large_medical_insurance_config_id,
                'large_medical_insurance_enabled' => false,
                'employee_social_security_base' => $employee->social_security_base,
                'employee_medical_insurance_base' => $employee->medical_insurance_base,
                'employee_housing_fund_base' => $employee->housing_fund_base,
                'employee_large_medical_base' => $employee->large_medical_base,
                'employee_large_medical_company_base' => $employee->large_medical_company_base,
                'change_summary' => '关闭大额医疗保险',
                'change_details' => json_encode([
                    [
                        'category' => 'large_medical_insurance',
                        'action' => 'disabled',
                        'item' => '大额医疗保险',
                        'description' => '关闭大额医疗保险参保'
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'created_by' => $request->user() ? $request->user()->id : null,
            ]);

            $change->saveCompleteInsuranceConfig();

            DB::commit();

            \Log::info('关闭大额医疗保险任务创建成功', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'insurance_change_id' => $change->id
            ]);

            return response()->json([
                'success' => true,
                'message' => '已创建大额医疗保险退保任务，请在增减管理中确认处理',
                'data' => [
                    'insurance_change_id' => $change->id
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('关闭大额医疗保险失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '关闭失败: ' . $e->getMessage()
            ], 500);
        }
    }

    private function insuranceChangeContainsLargeMedical(\App\Models\InsuranceChange $change): bool
    {
        if (in_array($change->change_summary, ['开启大额医疗保险', '关闭大额医疗保险'], true)) {
            return true;
        }

        $details = $change->change_details;
        if (is_string($details)) {
            $details = json_decode($details, true);
        }

        if (!is_array($details)) {
            return false;
        }

        $changes = $details['changes'] ?? $details;
        if (!is_array($changes)) {
            return false;
        }

        foreach ($changes as $item) {
            if (($item['category'] ?? null) === 'large_medical_insurance') {
                return true;
            }
        }

        return false;
    }

    private function findPendingLargeMedicalTask(Employee $employee): ?\App\Models\InsuranceChange
    {
        return \App\Models\InsuranceChange::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'submitted'])
            ->latest('id')
            ->get()
            ->first(function ($change) {
                return $this->insuranceChangeContainsLargeMedical($change);
            });
    }

    private function buildLargeMedicalStatusData(Employee $employee): array
    {
        if (!$employee->large_medical_insurance_config_id) {
            return [
                'has_config' => false,
                'is_enrolled' => false,
                'has_pending_task' => false,
                'can_enable' => false,
                'can_disable' => false,
                'status' => 'no_config',
                'status_text' => '未配置',
            ];
        }

        $insurancePersonnel = \App\Models\InsurancePersonnel::where('employee_id', $employee->id)
            ->where('large_medical_insurance_enabled', true)
            ->where('status', 'active')
            ->first();

        $pendingTask = $this->findPendingLargeMedicalTask($employee);

        if ($pendingTask) {
            $isDisableTask = $pendingTask->change_summary === '关闭大额医疗保险'
                || $pendingTask->change_type === 'decrease';

            return [
                'has_config' => true,
                'is_enrolled' => (bool) $insurancePersonnel,
                'has_pending_task' => true,
                'can_enable' => false,
                'can_disable' => false,
                'status' => $isDisableTask ? 'pending_disable' : 'pending_enable',
                'status_text' => $isDisableTask ? '待退保' : '待开启',
            ];
        }

        if ($insurancePersonnel) {
            return [
                'has_config' => true,
                'is_enrolled' => true,
                'has_pending_task' => false,
                'can_enable' => false,
                'can_disable' => true,
                'status' => 'enrolled',
                'status_text' => '已参保',
            ];
        }

        $completedTask = \App\Models\InsuranceChange::where('employee_id', $employee->id)
            ->where('status', 'completed')
            ->where('fully_confirmed', true)
            ->first();

        if (!$completedTask) {
            return [
                'has_config' => true,
                'is_enrolled' => false,
                'has_pending_task' => false,
                'can_enable' => false,
                'can_disable' => false,
                'status' => 'not_onboarded',
                'status_text' => '未入职',
            ];
        }

        return [
            'has_config' => true,
            'is_enrolled' => false,
            'has_pending_task' => false,
            'can_enable' => true,
            'can_disable' => false,
            'status' => 'can_enable',
            'status_text' => '未参保',
        ];
    }
    
    /**
     * 获取员工统计数据
     */
    protected function getEmployeeStats($accountSetId = null, Request $request = null)
    {
        $query = Employee::query();
        
        if ($accountSetId) {
            $query->where('account_set_id', $accountSetId);
        } elseif ($request && optional($request->user())->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }

        if ($request && $request->filled('project_id')) {
            $query->whereHas('activeProjects', function($q) use ($request) {
                $q->where('projects.id', $request->input('project_id'));
            });
        }

        if ($request && $request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        
        $today = Carbon::today();
        
        $activeQuery = clone $query;
        $this->applyActivePersonnelFilter($activeQuery);
        $active = $activeQuery->count();

        $resignedQuery = clone $query;
        $this->applyResignedPersonnelFilter($resignedQuery);
        $resigned = $resignedQuery->count();

        $probationQuery = clone $query;
        $this->applyActivePersonnelFilter($probationQuery);
        $probation = $probationQuery
            ->whereNotNull('probation_end_date')
            ->where('probation_end_date', '>=', $today)
            ->count();
        
        // 合同到期未签解除/退休协议人数
        $contractExpiredQuery = clone $query;
        $this->applyActivePersonnelFilter($contractExpiredQuery);
        $contractExpired = $contractExpiredQuery
            ->whereNotNull('contract_end_date')
            ->where('contract_end_date', '<', $today)
            ->whereDoesntHave('latestEmployeeContract', function ($contractQuery) {
                $contractQuery->whereIn('contract_type', ['termination', 'retirement'])
                    ->whereIn('status', ['employee_signed', 'in_approval', 'completed']);
            })
            ->count();
        
        return [
            'active' => $active,
            'resigned' => $resigned,
            'probation' => $probation,
            'contractExpired' => $contractExpired
        ];
    }

    private function resolveEmployeePageAccountSetId(Request $request): ?int
    {
        $accountSetId = $request->header('X-Account-Set-Id')
            ?: $request->input('current_account_set_id')
            ?: $request->user()?->current_account_set_id
            ?: $request->user()?->account_set_id;

        return $accountSetId ? (int) $accountSetId : null;
    }

    private function buildEmployeePageProjectOptions(int $accountSetId): array
    {
        return Project::where('account_set_id', $accountSetId)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'code',
                'status',
                'start_date',
                'end_date',
                'social_security_regions',
                'medical_insurance_regions',
                'housing_fund_regions',
            ])
            ->map(function (Project $project) {
                return [
                    'id' => (int) $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'status' => $project->status,
                    'start_date' => $project->getRawOriginal('start_date'),
                    'end_date' => $project->getRawOriginal('end_date'),
                    'social_security_regions' => $project->social_security_regions ?: [],
                    'medical_insurance_regions' => $project->medical_insurance_regions ?: [],
                    'housing_fund_regions' => $project->housing_fund_regions ?: [],
                ];
            })
            ->values()
            ->all();
    }

    private function buildExpiredIdCardReminderData(int $accountSetId): array
    {
        $today = Carbon::today();
        $warningDate = $today->copy()->addMonth();

        $expiredEmployees = Employee::where('account_set_id', $accountSetId)
            ->whereNotNull('id_card_valid_until')
            ->whereDate('id_card_valid_until', '<=', $warningDate->toDateString());

        $this->applyActivePersonnelFilter($expiredEmployees);

        $expiredEmployees = $expiredEmployees
            ->select('id', 'name', 'employee_number', 'id_number', 'id_card_valid_until')
            ->orderBy('id_card_valid_until', 'asc')
            ->get()
            ->map(function ($employee) use ($today) {
                $expiryDate = Carbon::parse($employee->id_card_valid_until)->startOfDay();
                $isExpired = $expiryDate->lt($today);
                $isToday = $expiryDate->equalTo($today);

                if ($isExpired) {
                    $expiredDays = $expiryDate->diffInDays($today);
                    $statusType = 'expired';
                    $statusLabel = '已过期';
                    $daysLabel = '已过期 ' . $expiredDays . ' 天';
                } elseif ($isToday) {
                    $expiredDays = 0;
                    $statusType = 'expiring_soon';
                    $statusLabel = '今日到期';
                    $daysLabel = '今日到期';
                } else {
                    $expiredDays = $today->diffInDays($expiryDate);
                    $statusType = 'expiring_soon';
                    $statusLabel = '即将到期';
                    $daysLabel = '剩余 ' . $expiredDays . ' 天';
                }

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_number' => $employee->employee_number,
                    'id_number' => $employee->id_number,
                    'id_card_valid_until' => $employee->id_card_valid_until,
                    'expired_days' => $expiredDays,
                    'status_type' => $statusType,
                    'status_label' => $statusLabel,
                    'days_label' => $daysLabel,
                ];
            });

        return [
            'data' => $expiredEmployees->values()->all(),
            'count' => $expiredEmployees->count(),
            'expired_count' => $expiredEmployees->where('status_type', 'expired')->count(),
            'expiring_soon_count' => $expiredEmployees->where('status_type', 'expiring_soon')->count(),
        ];
    }

    private function buildPendingContractUploadData(int $accountSetId): array
    {
        return Employee::where('account_set_id', $accountSetId)
            ->where('is_offline_onboarding', true)
            ->where('contract_uploaded', false)
            ->where('contract_upload_deadline', '<', Carbon::now())
            ->where('contract_status', 'active')
            ->with('projects:id,name')
            ->get()
            ->map(function (Employee $employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'id_number' => $employee->id_number,
                    'phone' => $employee->phone,
                    'offline_onboarding_date' => $employee->offline_onboarding_date,
                    'contract_upload_deadline' => $employee->contract_upload_deadline,
                    'overdue_days' => Carbon::parse($employee->contract_upload_deadline)->diffInDays(Carbon::now()),
                    'projects' => $employee->projects->pluck('name')->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildArchiveReminderCounts(int $accountSetId): array
    {
        $today = Carbon::today();
        $warningDate = $today->copy()->addDays(30);

        $documentEmployees = Employee::where('account_set_id', $accountSetId)
            ->select(['id', 'account_set_id'])
            ->with([
                'projects:id,name',
                'documents:id,employee_id,document_config_id',
            ])
            ->get();
        $this->appendMissingRequiredDocumentData($documentEmployees);

        $activeQuery = Employee::where('account_set_id', $accountSetId);
        $this->applyActivePersonnelFilter($activeQuery);

        $unsignedContractQuery = clone $activeQuery;
        $this->applyLaborContractSignFilter($unsignedContractQuery, 'unsigned');

        $pendingStampQuery = clone $activeQuery;
        $this->applyLaborContractSignFilter($pendingStampQuery, 'pending_stamp');

        $pendingResignationAgreementQuery = Employee::where('account_set_id', $accountSetId);
        $this->applyResignedPersonnelFilter($pendingResignationAgreementQuery);
        $pendingResignationAgreementQuery->whereDoesntHave('latestTerminationContract', function ($contractQuery) {
            $contractQuery->where('status', 'completed');
        });

        $retiredQuery = Employee::where('account_set_id', $accountSetId);
        $this->applyPersonnelStatusFilter($retiredQuery, 'retired');

        $contractExpiredQuery = clone $activeQuery;
        $contractExpiredQuery
            ->whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '<', $today->toDateString())
            ->whereDoesntHave('latestEmployeeContract', function ($contractQuery) {
                $contractQuery->whereIn('contract_type', ['termination', 'retirement'])
                    ->whereIn('status', ['employee_signed', 'in_approval', 'completed']);
            });

        return [
            'missing_documents' => $documentEmployees->filter(function (Employee $employee) {
                return (bool) $employee->has_missing_required_documents;
            })->count(),
            'unsigned_contract' => $unsignedContractQuery->count(),
            'offline_contract_upload' => Employee::where('account_set_id', $accountSetId)
                ->where('is_offline_onboarding', true)
                ->where(function ($query) {
                    $query->whereNull('contract_uploaded')->orWhere('contract_uploaded', false);
                })
                ->count(),
            'pending_stamp' => $pendingStampQuery->count(),
            'pending_resignation_agreement' => $pendingResignationAgreementQuery->count(),
            'retired' => $retiredQuery->count(),
            'contract_expiring' => (clone $activeQuery)
                ->whereNotNull('contract_end_date')
                ->whereDate('contract_end_date', '>=', $today->toDateString())
                ->whereDate('contract_end_date', '<=', $warningDate->toDateString())
                ->count(),
            'contract_expired' => $contractExpiredQuery->count(),
        ];
    }

    public function getPageBootstrap(Request $request)
    {
        if ($response = $this->checkPermission('employees.view')) {
            return $response;
        }

        $accountSetId = $this->resolveEmployeePageAccountSetId($request);
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套',
            ], 400);
        }

        $warnings = [];

        try {
            $projects = $this->buildEmployeePageProjectOptions($accountSetId);
        } catch (\Throwable $e) {
            $projects = [];
            $warnings[] = '项目选项加载失败';
            \Log::error('人员档案页面项目选项加载失败', ['error' => $e->getMessage()]);
        }

        try {
            $idCardReminders = $this->buildExpiredIdCardReminderData($accountSetId);
        } catch (\Throwable $e) {
            $idCardReminders = ['data' => [], 'count' => 0, 'expired_count' => 0, 'expiring_soon_count' => 0];
            $warnings[] = '身份证提醒加载失败';
            \Log::error('人员档案页面身份证提醒加载失败', ['error' => $e->getMessage()]);
        }

        try {
            $pendingContractUpload = $this->buildPendingContractUploadData($accountSetId);
        } catch (\Throwable $e) {
            $pendingContractUpload = [];
            $warnings[] = '待上传合同加载失败';
            \Log::error('人员档案页面待上传合同加载失败', ['error' => $e->getMessage()]);
        }

        try {
            $archiveReminderCounts = $this->buildArchiveReminderCounts($accountSetId);
        } catch (\Throwable $e) {
            $archiveReminderCounts = [
                'missing_documents' => 0,
                'unsigned_contract' => 0,
                'offline_contract_upload' => 0,
                'pending_stamp' => 0,
                'pending_resignation_agreement' => 0,
                'retired' => 0,
                'contract_expiring' => 0,
                'contract_expired' => 0,
            ];
            $warnings[] = '档案提醒统计加载失败';
            \Log::error('人员档案页面档案提醒统计加载失败', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'projects' => $projects,
                'id_card_reminders' => $idCardReminders,
                'pending_contract_upload' => [
                    'data' => $pendingContractUpload,
                    'count' => count($pendingContractUpload),
                ],
                'archive_reminder_counts' => $archiveReminderCounts,
            ],
            'warnings' => $warnings,
        ]);
    }

    /**
     * 获取身份证已过期或一个月内即将到期的在职员工列表
     */
    public function getExpiredIdCards(Request $request)
    {
        try {
            // 【账套过滤】从请求参数获取当前账套ID
            $accountSetId = $request->input('current_account_set_id');
            if (!$accountSetId) {
                $accountSetId = $request->user()->account_set_id;
            }

            $reminderData = $this->buildExpiredIdCardReminderData((int) $accountSetId);

            return response()->json([
                'success' => true,
                'data' => $reminderData['data'],
                'count' => $reminderData['count'],
                'expired_count' => $reminderData['expired_count'],
                'expiring_soon_count' => $reminderData['expiring_soon_count'],
            ]);
        } catch (\Exception $e) {
            \Log::error('获取过期身份证列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取过期身份证列表失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取员工变更历史记录
     */
    public function getChangeHistory($id, Request $request)
    {
        try {
            $employee = Employee::findOrFail($id);
            
            // 权限检查：只能查看自己账套的员工
            $accountSetId = $request->input('current_account_set_id');
            if ($employee->account_set_id != $accountSetId && $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => '无权查看该员工的变更历史'
                ], 403);
            }
            
            // 查询该员工的所有变更记录
            $logs = OperationLog::where('model_type', Employee::class)
                ->where('model_id', $id)
                ->where('action', 'updated') // 只查询更新操作
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));
            
            // 格式化返回数据
            $logs->getCollection()->transform(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user_name,
                    'description' => $log->description,
                    'old_values' => $log->old_values, // 已经通过 $casts 转换为数组
                    'new_values' => $log->new_values, // 已经通过 $casts 转换为数组
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'total' => $logs->total(),
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'last_page' => $logs->lastPage(),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('获取员工变更历史失败', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取变更历史失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 下载员工批量导入模板
     */
    public function downloadImportTemplate(Request $request)
    {
        // 人员档案查看权限
        if ($response = $this->checkPermission('employees.view')) {
            return $response;
        }

        try {
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择账套'
                ], 400);
            }

            // 获取当前账套的所有项目
            $projects = Project::where('account_set_id', $accountSetId)
                ->orderBy('name')
                ->get();

            if ($projects->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '当前账套没有项目，请先创建项目'
                ], 400);
            }

            \Log::info('下载模板 - 项目数据', [
                'account_set_id' => $accountSetId,
                'projects_count' => $projects->count(),
                'projects' => $projects->pluck('name')->toArray()
            ]);

            // 获取所有地区数据
            $socialSecurityRegions = \App\Models\SocialSecurityRegion::orderBy('name')->get();
            $medicalInsuranceRegions = \App\Models\MedicalInsuranceRegion::orderBy('name')->get();
            $housingFundRegions = \App\Models\HousingFundRegion::orderBy('region_name')->get();
            $largeMedicalConfigs = \App\Models\LargeMedicalInsuranceConfig::orderBy('region_name')->get();

            // 测试：直接返回项目列表看看
            if ($request->has('debug')) {
                return response()->json([
                    'projects' => $projects->pluck('name')->toArray(),
                    'count' => $projects->count()
                ]);
            }

            // 使用 PhpSpreadsheet 生成 Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('员工信息');

            // 设置表头 - 根据新增员工表单的字段
            $headers = [
                // 基本信息
                'A' => '姓名*',
                'B' => '身份证号*',
                'C' => '性别*',
                'D' => '出生日期*',
                'E' => '手机号',
                'F' => '岗位',
                // 合同信息
                'G' => '入职日期*',
                'H' => '合同开始日期*',
                'I' => '合同结束日期',
                'J' => '试用期结束日期',
                'K' => '签署地',
                'L' => '户口类型',
                // 项目信息
                'M' => '项目名称*',
                // 保险地区（社保、医保、公积金必填，大额医疗可选）
                'N' => '社保地区*',
                'O' => '医保地区*',
                'P' => '公积金地区*',
                'Q' => '大额医疗地区',
                // 保险基数（社保、医保、公积金必填，大额医疗可选）
                'R' => '社保基数*',
                'S' => '医保基数*',
                'T' => '公积金基数*',
                'U' => '大额医疗基数',
                // 参保日期
                'V' => '社保参保日期',
                'W' => '医保参保日期',
                'X' => '公积金参保日期',
                'Y' => '大额医疗参保日期',
                // 银行信息
                'Z' => '银行账号',
                'AA' => '户名',
                'AB' => '开户行',
                'AC' => '开户地',
                'AD' => '汇款备注',
                // 工资信息
                'AE' => '基本工资',
            ];

            foreach ($headers as $col => $header) {
                $sheet->setCellValue($col . '1', $header);
            }

            // 设置表头样式
            $sheet->getStyle('A1:AE1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);

            // 设置列宽
            foreach (range('A', 'Z') as $col) {
                $sheet->getColumnDimension($col)->setWidth(15);
            }
            foreach (['AA', 'AB', 'AC', 'AD', 'AE'] as $col) {
                $sheet->getColumnDimension($col)->setWidth(15);
            }

            // 添加示例数据行（第2行）
            $sheet->setCellValue('A2', '张三');
            $sheet->setCellValue('B2', '110101199001011234');
            $sheet->setCellValue('C2', '男');
            $sheet->setCellValue('D2', '1990-01-01');
            $sheet->setCellValue('E2', '13800138000');
            $sheet->setCellValue('F2', '软件工程师');
            $sheet->setCellValue('G2', '2024-01-01');
            $sheet->setCellValue('H2', '2024-01-01');
            $sheet->setCellValue('I2', '2026-12-31');
            $sheet->setCellValue('J2', '2024-03-31');
            $sheet->setCellValue('K2', '北京');
            $sheet->setCellValue('L2', '非农业');
            $sheet->setCellValue('M2', $projects->first()->name);
            $sheet->setCellValue('N2', '北京');
            $sheet->setCellValue('O2', '北京');
            $sheet->setCellValue('P2', '北京');
            $sheet->setCellValue('Q2', '');
            $sheet->setCellValue('R2', '5000');
            $sheet->setCellValue('S2', '5000');
            $sheet->setCellValue('T2', '5000');
            $sheet->setCellValue('U2', '');
            $sheet->setCellValue('V2', '2024-01-01');
            $sheet->setCellValue('W2', '2024-01-01');
            $sheet->setCellValue('X2', '2024-01-01');
            $sheet->setCellValue('Y2', '');
            $sheet->setCellValue('Z2', '6214850212345678');
            $sheet->setCellValue('AA2', '张三');
            $sheet->setCellValue('AB2', '招商银行北京分行');
            $sheet->setCellValue('AC2', '北京');
            $sheet->setCellValue('AD2', '');
            $sheet->setCellValue('AE2', '8000');

            // 设置示例行样式（浅灰色背景）
            // 删除示例数据行，从第2行开始就是空白数据行

            // 添加性别下拉列表（C列，从第2行开始到第500行）
            for ($row = 2; $row <= 500; $row++) {
                $validation = $sheet->getCell('C' . $row)->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('输入错误');
                $validation->setError('请从下拉列表中选择性别');
                $validation->setPromptTitle('性别');
                $validation->setPrompt('请选择：男 或 女');
                $validation->setFormula1('"男,女"');
            }

            // 添加户口类型下拉列表（L列，从第2行开始到第500行）
            for ($row = 2; $row <= 500; $row++) {
                $validation = $sheet->getCell('L' . $row)->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('输入提示');
                $validation->setError('建议从下拉列表中选择户口类型');
                $validation->setPromptTitle('户口类型');
                $validation->setPrompt('请选择：农业 或 非农业（可选）');
                $validation->setFormula1('"农业,非农业"');
            }

            // 添加说明信息（第4行开始）
            $sheet->setCellValue('A4', '填写说明：');
            $sheet->setCellValue('A5', '1. 带*号的列为必填项');
            $sheet->setCellValue('A6', '2. 性别和户口类型支持下拉选择');
            $sheet->setCellValue('A7', '3. 日期格式：YYYY-MM-DD，例如：2024-01-01');
            $sheet->setCellValue('A8', '4. 项目名称、保险地区名称必须与系统中完全一致');
            $sheet->setCellValue('A9', '5. 社保地区、医保地区、公积金地区为必填项');
            $sheet->setCellValue('A10', '6. 社保基数、医保基数、公积金基数为必填项');
            $sheet->setCellValue('A11', '7. 大额医疗地区和基数为可选项');
            $sheet->setCellValue('A12', '8. 导入时会根据名称自动匹配项目和保险地区');

            // 生成文件名
            $fileName = '员工批量导入模板_' . date('YmdHis') . '.xlsx';
            
            // 清空输出缓冲区
            if (ob_get_length()) {
                ob_end_clean();
            }
            
            // 创建Writer
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            // 直接输出到浏览器
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . urlencode($fileName) . '"');
            header('Cache-Control: max-age=0');
            
            // 输出到浏览器
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            \Log::error('下载员工导入模板失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '下载模板失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量导入员工
     */
    public function importEmployees(Request $request)
    {
        // 人员档案新增权限
        if ($response = $this->checkPermission('employees.create')) {
            return $response;
        }

        try {
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择账套'
                ], 400);
            }

            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => '请上传Excel文件'
                ], 400);
            }

            $file = $request->file('file');
            
            // 验证文件类型
            $extension = $file->getClientOriginalExtension();
            if (!in_array($extension, ['xlsx', 'xls'])) {
                return response()->json([
                    'success' => false,
                    'message' => '只支持Excel文件（.xlsx或.xls）'
                ], 400);
            }

            // 读取Excel文件
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // 移除表头行和填写说明行
            // PhpSpreadsheet toArray() 返回的数组：索引0 = Excel第1行（表头）
            $dataRows = [];
            $currentExcelRow = 0; // toArray() 索引从0开始

            foreach ($rows as $row) {
                $currentExcelRow++; // 第1次循环=1，处理Excel第1行
                $firstCell = trim($row[0] ?? '');

                // 跳过空行
                if (empty(array_filter($row))) {
                    continue;
                }

                // 第1行是表头，直接跳过
                if ($currentExcelRow === 1) {
                    continue;
                }

                // 剩余的是数据行，记录原始行号
                $row['__excel_row'] = $currentExcelRow;
                $dataRows[] = $row;
            }

            // 调试：返回读取到的行数
            if (empty($dataRows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel文件中没有数据 - 读取到' . count($rows) . '行，跳过表头后剩余' . count($dataRows) . '行',
                    'debug' => [
                        'total_rows' => count($rows),
                        'first_row' => $rows[0] ?? null,
                    ]
                ], 400);
            }

            $rows = $dataRows;

            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel文件中没有数据'
                ], 400);
            }

            $successCount = 0;
            $failCount = 0;
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($rows as $row) {
                    $rowNumber = $row['__excel_row'] ?? 0;

                    // 跳过空行
                    $rowWithoutMeta = array_diff_key($row, ['__excel_row' => '']);
                    if (empty(array_filter($rowWithoutMeta))) {
                        continue;
                    }

                    // 解析数据 - 按照新模板的列顺序
                    $name = trim($row[0] ?? '');  // A: 姓名*
                    $idNumber = trim($row[1] ?? '');  // B: 身份证号*
                    $gender = trim($row[2] ?? '');  // C: 性别*
                    $birthDate = trim($row[3] ?? '');  // D: 出生日期*
                    $phone = trim($row[4] ?? '');  // E: 手机号
                    $position = trim($row[5] ?? '');  // F: 岗位
                    $hireDate = trim($row[6] ?? '');  // G: 入职日期*
                    $contractStartDate = trim($row[7] ?? '');  // H: 合同开始日期*
                    $contractEndDate = trim($row[8] ?? '');  // I: 合同结束日期
                    $probationEndDate = trim($row[9] ?? '');  // J: 试用期结束日期
                    $signingLocation = trim($row[10] ?? '');  // K: 签署地
                    $householdType = trim($row[11] ?? '');  // L: 户口类型
                    $projectName = trim($row[12] ?? '');  // M: 项目名称*
                    $socialSecurityRegionName = trim($row[13] ?? '');  // N: 社保地区*
                    $medicalInsuranceRegionName = trim($row[14] ?? '');  // O: 医保地区*
                    $housingFundRegionName = trim($row[15] ?? '');  // P: 公积金地区*
                    $largeMedicalRegionName = trim($row[16] ?? '');  // Q: 大额医疗地区
                    $socialSecurityBase = trim($row[17] ?? '');  // R: 社保基数*
                    $medicalInsuranceBase = trim($row[18] ?? '');  // S: 医保基数*
                    $housingFundBase = trim($row[19] ?? '');  // T: 公积金基数*
                    $largeMedicalBase = trim($row[20] ?? '');  // U: 大额医疗基数
                    $socialInsuranceEnrollmentDate = trim($row[21] ?? '');  // V: 社保参保日期
                    $medicalInsuranceEnrollmentDate = trim($row[22] ?? '');  // W: 医保参保日期
                    $providentFundEnrollmentDate = trim($row[23] ?? '');  // X: 公积金参保日期
                    $largeMedicalEnrollmentDate = trim($row[24] ?? '');  // Y: 大额医疗参保日期
                    $bankAccount = trim($row[25] ?? '');  // Z: 银行账号
                    $bankAccountHolder = trim($row[26] ?? '');  // AA: 户名
                    $bankBranch = trim($row[27] ?? '');  // AB: 开户行
                    $bankProvince = trim($row[28] ?? '');  // AC: 开户地
                    $remittanceRemark = trim($row[29] ?? '');  // AD: 汇款备注
                    $basicSalary = trim($row[30] ?? '');  // AE: 基本工资

                    // 修复科学计数法问题（Excel导出的长数字会被转成科学计数法如1.1010119900101E+17）
                    if (preg_match('/^\d+\.?\d*[eE]\+?\d+$/', $idNumber)) {
                        $idNumber = strval(intval(floatval($idNumber)));
                    }
                    if (preg_match('/^\d+\.?\d*[eE]\+?\d+$/', $bankAccount)) {
                        $bankAccount = strval(intval(floatval($bankAccount)));
                    }

                    // 验证必填字段
                    if (empty($name)) {
                        $errors[] = "第{$rowNumber}行：姓名不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($idNumber)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：身份证号不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($gender)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：性别不能为空";
                        $failCount++;
                        continue;
                    }

                    // 验证性别格式
                    if (!in_array($gender, ['男', '女'])) {
                        $errors[] = "第{$rowNumber}行（{$name}）：性别只能填写'男'或'女'";
                        $failCount++;
                        continue;
                    }
                    $gender = $gender === '男' ? 'male' : 'female';

                    if (empty($birthDate)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：出生日期不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($hireDate)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：入职日期不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($contractStartDate)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：合同开始日期不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($projectName)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：项目名称不能为空";
                        $failCount++;
                        continue;
                    }

                    // 验证保险地区必填（社保、医保、公积金）
                    if (empty($socialSecurityRegionName)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：社保地区不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($medicalInsuranceRegionName)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：医保地区不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($housingFundRegionName)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：公积金地区不能为空";
                        $failCount++;
                        continue;
                    }

                    // 验证保险基数必填（社保、医保、公积金）
                    if (empty($socialSecurityBase)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：社保基数不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($medicalInsuranceBase)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：医保基数不能为空";
                        $failCount++;
                        continue;
                    }

                    if (empty($housingFundBase)) {
                        $errors[] = "第{$rowNumber}行（{$name}）：公积金基数不能为空";
                        $failCount++;
                        continue;
                    }

                    // 检查身份证号是否已存在
                    $existingEmployee = Employee::where('id_number', $idNumber)
                        ->where('account_set_id', $accountSetId)
                        ->first();
                    
                    if ($existingEmployee) {
                        $errors[] = "第{$rowNumber}行（{$name}）：身份证号{$idNumber}已存在";
                        $failCount++;
                        continue;
                    }

                    // 检查项目是否存在
                    $project = Project::where('name', $projectName)
                        ->where('account_set_id', $accountSetId)
                        ->first();
                    
                    if (!$project) {
                        $errors[] = "第{$rowNumber}行（{$name}）：项目'{$projectName}'不存在";
                        $failCount++;
                        continue;
                    }

                    // 验证社保地区（必填）
                    $socialSecurityRegionId = null;
                    $region = \App\Models\SocialSecurityRegion::where('name', $socialSecurityRegionName)->first();
                    if (!$region) {
                        $errors[] = "第{$rowNumber}行（{$name}）：社保地区'{$socialSecurityRegionName}'不存在";
                        $failCount++;
                        continue;
                    }
                    $socialSecurityRegionId = $region->id;

                    // 验证医保地区（必填）
                    $medicalInsuranceRegionId = null;
                    $region = \App\Models\MedicalInsuranceRegion::where('name', $medicalInsuranceRegionName)->first();
                    if (!$region) {
                        $errors[] = "第{$rowNumber}行（{$name}）：医保地区'{$medicalInsuranceRegionName}'不存在";
                        $failCount++;
                        continue;
                    }
                    $medicalInsuranceRegionId = $region->id;

                    // 验证公积金地区（必填）
                    $housingFundRegionId = null;
                    $region = \App\Models\HousingFundRegion::where('region_name', $housingFundRegionName)->first();
                    if (!$region) {
                        $errors[] = "第{$rowNumber}行（{$name}）：公积金地区'{$housingFundRegionName}'不存在";
                        $failCount++;
                        continue;
                    }
                    $housingFundRegionId = $region->id;

                    // 验证大额医疗地区（可选）
                    $largeMedicalConfigId = null;
                    if (!empty($largeMedicalRegionName)) {
                        $config = \App\Models\LargeMedicalInsuranceConfig::where('region_name', $largeMedicalRegionName)->first();
                        if (!$config) {
                            $errors[] = "第{$rowNumber}行（{$name}）：大额医疗地区'{$largeMedicalRegionName}'不存在";
                            $failCount++;
                            continue;
                        }
                        $largeMedicalConfigId = $config->id;
                    }

                    // 处理户口类型
                    $householdTypeValue = null;
                    if (!empty($householdType)) {
                        if ($householdType === '农业') {
                            $householdTypeValue = 'agricultural';
                        } elseif ($householdType === '非农业') {
                            $householdTypeValue = 'non_agricultural';
                        }
                    }

                    // 创建员工数据
                    $employeeData = [
                        'account_set_id' => $accountSetId,
                        'name' => $name,
                        'id_number' => $idNumber,
                        'gender' => $gender,
                        'birth_date' => $birthDate,
                        'phone' => $phone,
                        'position' => $position,
                        'hire_date' => $hireDate,
                        'contract_start_date' => $contractStartDate,
                        'contract_end_date' => !empty($contractEndDate) ? $contractEndDate : null,
                        'probation_end_date' => !empty($probationEndDate) ? $probationEndDate : null,
                        'signing_location' => $signingLocation,
                        'household_type' => $householdTypeValue,
                        'contract_status' => 'unsigned',
                        'personnel_status' => 'active',
                        // 保险地区
                        'social_security_region_id' => $socialSecurityRegionId,
                        'medical_insurance_region_id' => $medicalInsuranceRegionId,
                        'housing_fund_region_id' => $housingFundRegionId,
                        'large_medical_insurance_config_id' => $largeMedicalConfigId,
                        // 保险基数
                        'social_security_base' => floatval($socialSecurityBase),
                        'medical_insurance_base' => floatval($medicalInsuranceBase),
                        'housing_fund_base' => floatval($housingFundBase),
                        'large_medical_base' => !empty($largeMedicalBase) ? floatval($largeMedicalBase) : null,
                        // 参保日期
                        'social_insurance_enrollment_date' => !empty($socialInsuranceEnrollmentDate) ? $socialInsuranceEnrollmentDate : null,
                        'medical_insurance_enrollment_date' => !empty($medicalInsuranceEnrollmentDate) ? $medicalInsuranceEnrollmentDate : null,
                        'provident_fund_enrollment_date' => !empty($providentFundEnrollmentDate) ? $providentFundEnrollmentDate : null,
                        'large_medical_enrollment_date' => !empty($largeMedicalEnrollmentDate) ? $largeMedicalEnrollmentDate : null,
                        // 银行信息
                        'bank_account' => $bankAccount,
                        'bank_account_holder' => $bankAccountHolder,
                        'bank_branch' => $bankBranch,
                        'bank_province' => $bankProvince,
                        'remittance_remark' => $remittanceRemark,
                        // 工资信息
                        'basic_salary' => !empty($basicSalary) ? floatval($basicSalary) : null,
                    ];

                    // 生成工号
                    $employeeData['employee_number'] = $this->generateEmployeeNumber($accountSetId, $project->id);

                    // 创建员工
                    $employee = Employee::create($employeeData);

                    // 关联项目
                    $employee->projects()->attach($project->id, $this->withProjectBindingData([
                        'start_date' => $hireDate,
                        'status' => 'active'
                    ], $employee->employee_number, $this->resolveProjectDocumentSetId((int) $project->id), true));

                    $successCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "导入完成：成功{$successCount}条，失败{$failCount}条",
                    'data' => [
                        'success_count' => $successCount,
                        'fail_count' => $failCount,
                        'errors' => $errors
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            \Log::error('批量导入员工失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '导入失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
