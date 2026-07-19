<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DynamicScheduledTaskService;
use App\Services\ProjectRoleUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Overtrue\Pinyin\Pinyin;
use App\Traits\ChecksPermission;

class ProjectController extends Controller
{
    use ChecksPermission;

    /**
     * 检查项目访问权限
     */
    private function checkProjectAccess(Request $request, Project $project)
    {
        $user = $request->user();
        
        // 管理员可以访问所有项目
        if ($user->role === 'admin') {
            return;
        }
        
        // 检查用户是否有权限访问该项目的账套
        $hasAccess = $user->accountSets()
            ->where('account_set_id', $project->account_set_id)
            ->exists();
        
        if (!$hasAccess) {
            abort(403, '没有权限访问该项目');
        }
    }

    private function getCurrentAccountSetId(Request $request)
    {
        return $request->header('X-Account-Set-Id')
            ?: $request->input('current_account_set_id')
            ?: $request->user()?->account_set_id;
    }

    private function calculateProjectStatusByEndDate($endDate): string
    {
        if (!$endDate) {
            return 'active';
        }

        return Carbon::parse($endDate)->lt(Carbon::today('Asia/Shanghai')) ? 'completed' : 'active';
    }

    private function resolveProjectStatus(array $projectData, $fallbackEndDate = null): string
    {
        $explicitStatus = $projectData['status'] ?? null;

        if ($explicitStatus === 'terminated' || $explicitStatus === 'inactive') {
            return 'terminated';
        }

        if ($explicitStatus === 'completed') {
            return $explicitStatus;
        }

        return $this->calculateProjectStatusByEndDate($projectData['end_date'] ?? $fallbackEndDate);
    }

    private function applyProjectBaseScope($query, Request $request): void
    {
        $currentAccountSetId = $this->getCurrentAccountSetId($request);

        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }

        $responsibilityRoleType = $request->input('responsibility_role_type');
        if ($currentAccountSetId && $responsibilityRoleType) {
            app(ProjectRoleUserService::class)->applyManagedProjectFilter(
                $query,
                'id',
                (int) $currentAccountSetId,
                $request->user(),
                (string) $responsibilityRoleType
            );
        }
    }

    private function applyProjectIndexFilters($query, Request $request): void
    {
        $this->applyProjectBaseScope($query, $request);

        if ($request->has('status') && $request->status) {
            $status = (string) $request->status;
            $today = Carbon::today('Asia/Shanghai')->toDateString();

            if ($status === 'terminated' || $status === 'inactive') {
                $query->whereIn('status', ['terminated', 'inactive']);
            } elseif ($status === 'completed') {
                $query->where(function ($statusQuery) use ($today) {
                    $statusQuery->where('status', 'completed')
                        ->orWhere(function ($endedQuery) use ($today) {
                            $endedQuery->whereNotIn('status', ['terminated', 'inactive'])
                                ->whereDate('end_date', '<', $today);
                        });
                });
            } elseif ($status === 'active') {
                $query->whereNotIn('status', ['completed', 'terminated', 'inactive'])
                    ->where(function ($activeQuery) use ($today) {
                        $activeQuery->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    });
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
    }

    private function buildProjectPayload(array $requestData, ?Project $project = null): array
    {
        if (array_key_exists('code', $requestData) && is_string($requestData['code'])) {
            $requestData['code'] = trim($requestData['code']);
        }

        foreach ([
            'social_security_regions',
            'medical_insurance_regions',
            'housing_fund_regions',
            'other_insurance_policies',
            'large_medical_insurance_configs',
        ] as $field) {
            if (array_key_exists($field, $requestData)) {
                $requestData[$field] = $this->normalizeProjectRelationIds($requestData[$field]);
            }
        }

        $hasInvoiceInfos = array_key_exists('invoice_infos', $requestData);
        $legacyInvoiceFields = [
            'invoice_company_name',
            'invoice_tax_number',
            'invoice_company_address',
            'invoice_company_phone',
            'invoice_bank_name',
            'invoice_bank_account',
            'invoice_bank_code',
        ];
        $hasLegacyInvoiceFields = false;

        foreach ($legacyInvoiceFields as $field) {
            if (array_key_exists($field, $requestData)) {
                $hasLegacyInvoiceFields = true;
                break;
            }
        }

        $invoiceInfos = Project::resolveInvoiceInfos(
            $hasInvoiceInfos ? ($requestData['invoice_infos'] ?? null) : null,
            ($hasInvoiceInfos || $hasLegacyInvoiceFields || !$project)
                ? $requestData
                : $project->getAttributes()
        );

        $requestData['invoice_infos'] = $invoiceInfos;
        Project::syncLegacyInvoiceFields($requestData, $invoiceInfos);

        return $requestData;
    }

    private function normalizeProjectRelationIds($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item['id'] ?? null;
                }

                if (is_object($item)) {
                    return $item->id ?? null;
                }

                return $item;
            })
            ->filter(fn ($item) => is_numeric($item))
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $item) => $item > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function projectValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,completed,inactive,terminated',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'social_security_location' => 'nullable|string',
            'insurance_types' => 'nullable|array',
            'salary_payment_date' => 'nullable|integer|min:1|max:31',
            'salary_payment_month' => 'nullable|in:current,next',
            'insurance_import_month' => 'required|in:current,next,none',
            'requires_attendance' => 'boolean',
            'require_attendance' => 'boolean',
            'delivery_frequency' => 'required|in:monthly,quarterly,semiannual,annual',
            'delivery_method' => 'required|in:express,electronic',
            'registration_form_type' => 'required|in:onboarding,registration',
            'invoice_infos' => 'required|array|min:1',
            'invoice_infos.*.remark' => 'required|string|max:255',
            'invoice_infos.*.company_name' => 'required|string|max:255',
            'invoice_infos.*.tax_number' => 'required|string|max:100',
            'invoice_infos.*.company_address' => 'required|string|max:255',
            'invoice_infos.*.company_phone' => 'required|string|max:50',
            'invoice_infos.*.bank_name' => 'required|string|max:255',
            'invoice_infos.*.bank_account' => 'required|string|max:100',
            'invoice_infos.*.bank_code' => 'required|string|max:100',
            'invoice_company_name' => 'nullable|string|max:255',
            'invoice_tax_number' => 'nullable|string|max:100',
            'invoice_company_address' => 'nullable|string|max:255',
            'invoice_company_phone' => 'nullable|string|max:50',
            'invoice_bank_name' => 'nullable|string|max:255',
            'invoice_bank_account' => 'nullable|string|max:100',
            'invoice_bank_code' => 'nullable|string|max:100',
            'social_security_regions' => 'nullable|array',
            'social_security_regions.*' => 'exists:social_security_regions,id',
            'medical_insurance_regions' => 'nullable|array',
            'medical_insurance_regions.*' => 'exists:medical_insurance_regions,id',
            'housing_fund_regions' => 'nullable|array',
            'housing_fund_regions.*' => 'exists:housing_fund_regions,id',
            'other_insurance_policies' => 'nullable|array',
            'other_insurance_policies.*' => 'exists:other_insurance_policies,id',
            'large_medical_insurance_configs' => 'nullable|array',
            'large_medical_insurance_configs.*' => 'exists:large_medical_insurance_configs,id',
        ];
    }

    private function projectValidationMessages(): array
    {
        return [
            'required' => ':attribute不能为空',
            'string' => ':attribute格式不正确',
            'integer' => ':attribute必须为整数',
            'numeric' => ':attribute必须为数字',
            'array' => ':attribute格式不正确',
            'boolean' => ':attribute格式不正确',
            'date' => ':attribute不是有效的日期',
            'exists' => '所选的:attribute不存在或已失效',
            'in' => ':attribute的选项无效',
            'min.array' => ':attribute至少需要选择:min项',
            'min.numeric' => ':attribute不能小于:min',
            'max.string' => ':attribute不能超过:max个字符',
            'max.numeric' => ':attribute不能大于:max',
            'present' => ':attribute不能为空',
            'name.required' => '请输入项目名称',
            'start_date.required' => '请选择开始时间',
            'end_date.required' => '请选择结束时间',
            'end_date.after_or_equal' => '结束时间不能早于开始时间',
            'salary_payment_date.required' => '请选择工资发放日期',
            'salary_payment_date.min' => '工资发放日期必须在 1 到 31 之间',
            'salary_payment_date.max' => '工资发放日期必须在 1 到 31 之间',
            'salary_payment_month.required' => '请选择工资发放设置',
            'salary_payment_month.in' => '工资发放设置无效',
            'insurance_import_month.required' => '请选择保险导入设置',
            'insurance_import_month.in' => '保险导入设置无效',
            'delivery_frequency.required' => '请选择交付频率',
            'delivery_frequency.in' => '交付频率无效',
            'delivery_method.required' => '请选择交付方式',
            'delivery_method.in' => '交付方式无效',
            'registration_form_type.required' => '请选择员工登记表类型',
            'registration_form_type.in' => '员工登记表类型无效',
            'invoice_infos.required' => '请至少填写一组开票信息',
            'invoice_infos.array' => '开票信息格式不正确',
            'invoice_infos.min' => '请至少填写一组开票信息',
            'invoice_infos.*.remark.required' => '开票信息备注不能为空',
            'invoice_infos.*.company_name.required' => '开票信息企业名称不能为空',
            'invoice_infos.*.tax_number.required' => '开票信息企业税号不能为空',
            'invoice_infos.*.company_address.required' => '开票信息企业地址不能为空',
            'invoice_infos.*.company_phone.required' => '开票信息企业电话不能为空',
            'invoice_infos.*.bank_name.required' => '开票信息开户银行不能为空',
            'invoice_infos.*.bank_account.required' => '开票信息银行账户不能为空',
            'invoice_infos.*.bank_code.required' => '开票信息行号不能为空',
        ];
    }

    private function projectValidationAttributes(): array
    {
        return [
            'name' => '项目名称',
            'code' => '项目编号',
            'description' => '项目描述',
            'status' => '项目状态',
            'start_date' => '开始时间',
            'end_date' => '结束时间',
            'social_security_location' => '社保缴纳地',
            'insurance_types' => '保险类型',
            'salary_payment_date' => '工资发放日期',
            'salary_payment_month' => '工资发放设置',
            'insurance_import_month' => '保险导入设置',
            'requires_attendance' => '是否需要考勤',
            'require_attendance' => '是否需要考勤',
            'delivery_frequency' => '交付频率',
            'delivery_method' => '交付方式',
            'registration_form_type' => '员工登记表类型',
            'invoice_infos' => '开票信息',
            'invoice_infos.*.remark' => '开票信息备注',
            'invoice_infos.*.company_name' => '开票信息企业名称',
            'invoice_infos.*.tax_number' => '开票信息企业税号',
            'invoice_infos.*.company_address' => '开票信息企业地址',
            'invoice_infos.*.company_phone' => '开票信息企业电话',
            'invoice_infos.*.bank_name' => '开票信息开户银行',
            'invoice_infos.*.bank_account' => '开票信息银行账户',
            'invoice_infos.*.bank_code' => '开票信息行号',
            'invoice_company_name' => '企业名称',
            'invoice_tax_number' => '企业税号',
            'invoice_company_address' => '企业地址',
            'invoice_company_phone' => '企业电话',
            'invoice_bank_name' => '开户银行',
            'invoice_bank_account' => '银行账户',
            'invoice_bank_code' => '行号',
            'social_security_regions' => '社保地区',
            'social_security_regions.*' => '社保地区',
            'medical_insurance_regions' => '医保地区',
            'medical_insurance_regions.*' => '医保地区',
            'housing_fund_regions' => '公积金地区',
            'housing_fund_regions.*' => '公积金地区',
            'other_insurance_policies' => '其他保险保单',
            'other_insurance_policies.*' => '其他保险保单',
            'large_medical_insurance_configs' => '大额医疗保险配置',
            'large_medical_insurance_configs.*' => '大额医疗保险配置',
            'region_ids' => '地区列表',
            'region_ids.*' => '地区',
            'policy_ids' => '保单列表',
            'policy_ids.*' => '保单',
            'config_ids' => '大额医疗保险配置',
            'config_ids.*' => '大额医疗保险配置',
            'notice_file_ids' => '须知文件列表',
            'notice_file_ids.*' => '须知文件',
            'notice_file_id' => '须知文件',
            'notice_placeholder_positions' => '须知占位符位置',
            'placeholder_fields' => '占位符字段',
            'placeholder_fields.*.key' => '占位符字段标识',
            'placeholder_fields.*.label' => '占位符字段名称',
        ];
    }

    private function makeProjectValidator(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        return Validator::make(
            $data,
            $rules,
            array_merge($this->projectValidationMessages(), $messages),
            array_merge($this->projectValidationAttributes(), $attributes)
        );
    }

    private function validationErrorResponse($validator)
    {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first() ?: '验证失败',
            'errors' => $validator->errors()
        ], 422);
    }

    private function attachProjectInvoiceInfoValidation($validator, array $invoiceInfos): void
    {
        $validator->after(function ($validator) use ($invoiceInfos) {
            $remarks = [];

            foreach ($invoiceInfos as $index => $invoiceInfo) {
                $remark = trim((string) ($invoiceInfo['remark'] ?? ''));
                if ($remark === '') {
                    continue;
                }

                if (in_array($remark, $remarks, true)) {
                    $validator->errors()->add(
                        "invoice_infos.{$index}.remark",
                        '同一个项目中的开票信息备注不能重复'
                    );
                    continue;
                }

                $remarks[] = $remark;
            }
        });
    }

    private function formatProjectRoleUsers(Project $project): array
    {
        $service = app(ProjectRoleUserService::class);
        $roles = [];
        $loadedAssignments = $project->relationLoaded('roleAssignments')
            ? $project->roleAssignments->filter(fn ($assignment) => $assignment->user)
            : null;

        foreach (ProjectRoleUserService::roleLabels() as $roleType => $label) {
            if ($loadedAssignments !== null) {
                $users = $loadedAssignments
                    ->where('role_type', $roleType)
                    ->map(fn ($assignment) => [
                        'id' => $assignment->user->id,
                        'name' => $assignment->user->name,
                    ])
                    ->values()
                    ->all();
            } else {
                $users = $service->getProjectRoleUsers($project, $roleType)
                    ->map(fn ($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ])
                    ->values()
                    ->all();
            }

            $roles[$roleType] = [
                'label' => $label,
                'user_ids' => array_values(array_map(fn ($item) => (int) $item['id'], $users)),
                'users' => $users,
            ];
        }

        return $roles;
    }

    private function currentUserCanManageProjectRoleUsers(Request $request, Project $project): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            return true;
        }

        $managerUserIds = app(ProjectRoleUserService::class)->getProjectRoleUserIds(
            (int) $project->account_set_id,
            (int) $project->id,
            ProjectRoleUserService::ROLE_ROLE_MANAGER
        );

        return in_array((int) $user->id, $managerUserIds, true);
    }

    private function appendProjectRoleManagementPermissions($projects, Request $request)
    {
        $projects->each(function (Project $project) use ($request) {
            $project->can_manage_role_users = $this->currentUserCanManageProjectRoleUsers($request, $project);
            $project->setAttribute('role_users', $this->formatProjectRoleUsers($project));
        });

        return $projects;
    }

    public function index(Request $request)
    {
        if ($response = $this->checkPermission('projects.view')) {
            return $response;
        }

        
        $query = Project::query();
        $this->applyProjectIndexFilters($query, $request);
        $statsQuery = clone $query;

        $query->withCount([
                'employees',
                'employees as active_employees_count' => function ($query) {
                    $query->where('employee_projects.status', 'active');
                },
                'employees as inactive_employees_count' => function ($query) {
                    $query->where('employee_projects.status', 'inactive');
                },
            ])
            ->with(['medicalInsuranceRegions', 'otherInsurancePolicies.type', 'largeMedicalInsuranceConfigs', 'roleAssignments.user:id,name']);

        $today = Carbon::today('Asia/Shanghai')->toDateString();
        $stats = $statsQuery->selectRaw("
                COUNT(*) as total_count,
                SUM(CASE WHEN status NOT IN ('completed', 'inactive', 'terminated') AND (end_date IS NULL OR end_date >= '{$today}') THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'completed' OR (status NOT IN ('inactive', 'terminated') AND end_date < '{$today}') THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status IN ('inactive', 'terminated') THEN 1 ELSE 0 END) as terminated_count
            ")
            ->first();

        $query->orderBy('created_at', 'desc');
        if ($request->boolean('all') || (!$request->has('page') && !$request->has('per_page'))) {
            $allProjects = $query->get();
            $this->appendProjectRoleManagementPermissions($allProjects, $request);
            $projects = [
                'current_page' => 1,
                'data' => $allProjects,
                'first_page_url' => null,
                'from' => $allProjects->isEmpty() ? null : 1,
                'last_page' => 1,
                'last_page_url' => null,
                'links' => [],
                'next_page_url' => null,
                'path' => $request->url(),
                'per_page' => $allProjects->count(),
                'prev_page_url' => null,
                'to' => $allProjects->count(),
                'total' => $allProjects->count(),
            ];
        } else {
            $perPage = (int) $request->input('per_page', 10);
            $perPage = max(1, min($perPage, 1000));
            $projects = $query->paginate($perPage);
            $this->appendProjectRoleManagementPermissions($projects->getCollection(), $request);
        }

        $filterOptions = null;
        if ($request->boolean('include_filter_options')) {
            $filterOptionsQuery = Project::query();
            $this->applyProjectBaseScope($filterOptionsQuery, $request);
            $filterOptions = $filterOptionsQuery
                ->select('id', 'name', 'code')
                ->orderBy('created_at', 'desc')
                ->get();
        }
        
        $response = [
            'success' => true,
            'data' => $projects,
            'stats' => [
                'total' => intval($stats->total_count ?? 0),
                'active' => intval($stats->active_count ?? 0),
                'completed' => intval($stats->completed_count ?? 0),
                'inactive' => intval($stats->terminated_count ?? 0),
                'terminated' => intval($stats->terminated_count ?? 0),
            ]
        ];

        if ($filterOptions !== null) {
            $response['filter_options'] = $filterOptions;
        }

        return response()->json($response);
    }

    public function getCreateOptions(Request $request)
    {
        $accountSetId = $this->getCurrentAccountSetId($request);
        $user = $request->user();

        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()
                ->where('account_sets.id', $accountSetId)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        $socialSecurityRegions = \App\Models\SocialSecurityRegion::where('account_set_id', $accountSetId)
            ->with(['socialSecurityTypes'])
            ->get();
        $housingFundRegions = \App\Models\HousingFundRegion::where('account_set_id', $accountSetId)
            ->get();
        $medicalInsuranceRegions = \App\Models\MedicalInsuranceRegion::where('account_set_id', $accountSetId)
            ->with(['medicalInsuranceTypes'])
            ->get();
        $otherInsurancePolicies = \App\Models\OtherInsurancePolicy::where('account_set_id', $accountSetId)
            ->where('status', 'active')
            ->with(['type'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'social_security_regions' => $socialSecurityRegions,
                'housing_fund_regions' => $housingFundRegions,
                'medical_insurance_regions' => $medicalInsuranceRegions,
                'other_insurance_policies' => $otherInsurancePolicies,
            ],
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->checkPermission('projects.create')) {
            return $response;
        }

        $requestData = $this->buildProjectPayload($request->all());

        $validator = $this->makeProjectValidator($requestData, $this->projectValidationRules());
        $this->attachProjectInvoiceInfoValidation($validator, $requestData['invoice_infos'] ?? []);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        // 【账套关联】自动关联到当前账套
        $projectData = $requestData;
        $projectData['status'] = $this->resolveProjectStatus($projectData);
        if ($request->has('requires_attendance')) {
            $projectData['require_attendance'] = $request->input('requires_attendance');
        }
        if ($request->has('require_attendance')) {
            $projectData['requires_attendance'] = $request->input('require_attendance');
        }
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $projectData['account_set_id'] = $currentAccountSetId;
        }

        // 自动生成项目编号（如果没有提供）
        if (empty($projectData['code'])) {
            $projectName = $projectData['name'] ?? '';
            $projectData['code'] = $this->generateProjectCode($projectName);
        }

        if ($duplicateResponse = $this->validateProjectCodeUnique($currentAccountSetId, $projectData['code'])) {
            return $duplicateResponse;
        }

        $project = Project::create($projectData);

        return response()->json([
            'success' => true,
            'message' => '项目创建成功',
            'data' => $project
        ]);
    }

    public function generateCodePreview(Request $request)
    {
        if ($response = $this->checkPermission('projects.view')) {
            return $response;
        }

        $validator = $this->makeProjectValidator($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $this->generateProjectCode($request->input('name'))
            ]
        ]);
    }

    /**
     * 生成项目编号
     * 格式：项目名称拼音首字母（如：LRGY）
     *
     * @param string $projectName 项目名称
     * @return string 生成的项目编号
     */
    protected function generateProjectCode($projectName = '')
    {
        $prefix = $this->getPinYinPrefix($projectName);

        return $prefix ?: 'XM';
    }

    /**
     * 获取中文拼音首字母缩写
     *
     * @param string $chinese 中文文字
     * @return string 首字母缩写（大写）
     */
    protected function getPinYinPrefix($chinese)
    {
        if (empty($chinese)) {
            return '';
        }
        $result = strtoupper(Pinyin::abbr($chinese)->join(''));

        return $result ?: 'XM';
    }

    protected function validateProjectCodeUnique($accountSetId, $code, $ignoreProjectId = null)
    {
        $query = Project::where('account_set_id', $accountSetId)
            ->where('code', $code);

        if ($ignoreProjectId) {
            $query->where('id', '<>', $ignoreProjectId);
        }

        if (!$query->exists()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => '已有相同编号，请修改后再保存',
            'errors' => ['code' => ['项目编号 "' . $code . '" 已存在']]
        ], 422);
    }

    public function show($id)
    {
        if ($response = $this->checkPermission('projects.view')) {
            return $response;
        }
        
        $project = Project::with(['employees', 'activeEmployees'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $project
        ]);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('projects.update')) {
            return $response;
        }
        
        $project = Project::findOrFail($id);
        $previousSalaryPaymentMonth = $project->salary_payment_month;
        
        $requestData = $this->buildProjectPayload($request->all(), $project);

        $validator = $this->makeProjectValidator($requestData, $this->projectValidationRules());
        $this->attachProjectInvoiceInfoValidation($validator, $requestData['invoice_infos'] ?? []);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        // 同步 requires_attendance 和 require_attendance 字段
        $updateData = $requestData;
        $updateData['status'] = $this->resolveProjectStatus($updateData, $project->end_date);
        if ($request->has('requires_attendance')) {
            $updateData['require_attendance'] = $request->input('requires_attendance');
        }
        if ($request->has('require_attendance')) {
            $updateData['requires_attendance'] = $request->input('require_attendance');
        }

        if (array_key_exists('code', $requestData) && empty($requestData['code'])) {
            $projectName = $requestData['name'] ?? $project->name;
            $updateData['code'] = $this->generateProjectCode($projectName);
        }

        if (array_key_exists('code', $updateData) && is_string($updateData['code'])) {
            $updateData['code'] = trim($updateData['code']);
        }

        $incomingCode = $updateData['code'] ?? $project->code;
        $currentCode = is_string($project->code) ? trim($project->code) : $project->code;
        if ($incomingCode !== $currentCode) {
            if ($duplicateResponse = $this->validateProjectCodeUnique($project->account_set_id, $incomingCode, $project->id)) {
                return $duplicateResponse;
            }
        }

        DB::transaction(function () use ($project, $updateData, $previousSalaryPaymentMonth) {
            $project->update($updateData);

            if ($project->wasChanged([
                'salary_payment_month',
                'requires_salary_basis',
                'requires_attendance_basis',
                'requires_attendance',
                'require_attendance',
                'start_date',
                'end_date',
                'status',
            ])) {
                app(DynamicScheduledTaskService::class)->reconcileProjectTasksForReferenceMonth(
                    $project->fresh(),
                    $previousSalaryPaymentMonth,
                    Carbon::now('Asia/Shanghai')->format('Y-m')
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => '项目更新成功',
            'data' => $project
        ]);
    }

    public function terminate(Request $request, $id)
    {
        if ($response = $this->checkPermission('projects.update')) {
            return $response;
        }

        $project = Project::findOrFail($id);
        $this->checkProjectAccess($request, $project);

        $project->update([
            'status' => 'terminated',
        ]);

        return response()->json([
            'success' => true,
            'message' => '项目已终止',
            'data' => $project->fresh(),
        ]);
    }

    public function destroy($id)
    {
        if ($response = $this->checkPermission('projects.delete')) {
            return $response;
        }
        
        $project = Project::findOrFail($id);
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => '项目删除成功'
        ]);
    }

    /**
     * 设置项目的劳动合同须知文件（支持多个）
     */
    public function setContractNotices(Request $request, $id)
    {
        $validator = $this->makeProjectValidator($request->all(), [
            'notice_file_ids' => 'sometimes|array',
            'notice_file_ids.*' => 'integer|exists:shared_files,id',
            'notice_file_id' => 'nullable|integer|exists:shared_files,id',
            'notice_placeholder_positions' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $project = Project::findOrFail($id);

        $noticeFileIds = [];
        if ($request->has('notice_file_ids')) {
            $noticeFileIds = $request->input('notice_file_ids', []);
        } elseif ($request->filled('notice_file_id')) {
            $noticeFileIds = [$request->input('notice_file_id')];
        }

        $noticeFileIds = array_values(array_unique(array_filter(array_map('intval', $noticeFileIds))));

        if (!empty($noticeFileIds)) {
            $validNoticeFiles = \App\Models\SharedFile::whereIn('id', $noticeFileIds)
                ->where('file_category', 'notice')
                ->where('account_set_id', $project->account_set_id)
                ->get();

            if ($validNoticeFiles->count() !== count($noticeFileIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '部分文件不是当前账套的须知文件'
                ], 422);
            }
        }

        \Log::info('💾 保存项目须知文件设置', [
            'project_id' => $id,
            'notice_file_ids' => $noticeFileIds
        ]);

        $firstNoticeFileId = $noticeFileIds[0] ?? null;

        $rawNoticePositions = $request->has('notice_placeholder_positions')
            ? $request->input('notice_placeholder_positions', [])
            : ($project->notice_placeholder_positions ?? []);

        $noticePlaceholderPositions = [];
        if (is_array($rawNoticePositions)) {
            foreach ($rawNoticePositions as $fileId => $positions) {
                $fileIdInt = (int) $fileId;
                if ($fileIdInt <= 0 || !in_array($fileIdInt, $noticeFileIds, true)) {
                    continue;
                }
                $noticePlaceholderPositions[$fileIdInt] = is_array($positions) ? array_values($positions) : [];
            }
        }

        $project->update([
            'contract_notice_file_id' => $firstNoticeFileId,
            'contract_notice_files' => empty($noticeFileIds) ? null : implode(',', $noticeFileIds),
            'notice_placeholder_positions' => empty($noticeFileIds) ? null : $noticePlaceholderPositions,
        ]);

        $noticeFiles = collect();
        if (!empty($noticeFileIds)) {
            $filesMap = \App\Models\SharedFile::with('uploader')
                ->whereIn('id', $noticeFileIds)
                ->where('file_category', 'notice')
                ->where('account_set_id', $project->account_set_id)
                ->get()
                ->keyBy('id');

            $ordered = [];
            foreach ($noticeFileIds as $noticeFileId) {
                if (isset($filesMap[$noticeFileId])) {
                    $ordered[] = $filesMap[$noticeFileId];
                }
            }
            $noticeFiles = collect($ordered);
        }

        \Log::info('✅ 保存后读取项目数据', [
            'contract_notice_file_id' => $project->fresh()->contract_notice_file_id,
            'notice_file_ids' => $noticeFiles->pluck('id')->toArray(),
        ]);

        $savedPlaceholderPositions = is_array($project->notice_placeholder_positions)
            ? $project->notice_placeholder_positions
            : [];

        return response()->json([
            'success' => true,
            'message' => !empty($noticeFileIds) ? '须知文件设置成功' : '须知文件已清除',
            'data' => [
                'notice_file_ids' => $noticeFiles->pluck('id')->toArray(),
                'notice_files' => $noticeFiles,
                'notice_file' => $noticeFiles->first(),
                'notice_placeholder_positions' => $savedPlaceholderPositions,
            ]
        ]);
    }

    /**
     * 获取项目的须知文件（支持多个）
     */
    public function getContractNotices($id)
    {
        $project = Project::findOrFail($id);

        $noticeFiles = collect();
        if (!empty($project->contract_notice_files)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $project->contract_notice_files)))));
            if (!empty($ids)) {
                $filesMap = \App\Models\SharedFile::with('uploader')
                    ->whereIn('id', $ids)
                    ->where('file_category', 'notice')
                    ->where('account_set_id', $project->account_set_id)
                    ->get()
                    ->keyBy('id');

                $ordered = [];
                foreach ($ids as $idItem) {
                    if (isset($filesMap[$idItem])) {
                        $ordered[] = $filesMap[$idItem];
                    }
                }
                $noticeFiles = collect($ordered);
            }
        }

        if ($noticeFiles->isEmpty() && !empty($project->contract_notice_file_id)) {
            $legacyNoticeFile = \App\Models\SharedFile::with('uploader')
                ->where('id', $project->contract_notice_file_id)
                ->where('file_category', 'notice')
                ->where('account_set_id', $project->account_set_id)
                ->first();

            if ($legacyNoticeFile) {
                $noticeFiles = collect([$legacyNoticeFile]);
            }
        }

        \Log::info('📋 读取项目须知文件', [
            'project_id' => $id,
            'notice_file_ids' => $noticeFiles->pluck('id')->toArray(),
            'contract_notice_file_id' => $project->contract_notice_file_id,
            'contract_notice_files' => $project->contract_notice_files,
        ]);

        $noticePlaceholderPositions = is_array($project->notice_placeholder_positions)
            ? $project->notice_placeholder_positions
            : [];

        return response()->json([
            'success' => true,
            'data' => [
                'notice_file_ids' => $noticeFiles->pluck('id')->toArray(),
                'notice_files' => $noticeFiles->values(),
                'notice_file' => $noticeFiles->first(),
                'notice_placeholder_positions' => $noticePlaceholderPositions,
            ]
        ]);
    }

    public function getStatistics($id)
    {
        $project = Project::findOrFail($id);
        
        $statistics = [
            'total_employees' => $project->employees()->count(),
            'active_employees' => $project->activeEmployees()->count(),
            'attendance_sheets' => $project->attendanceSheets()->count(),
            'pending_approvals' => $project->attendanceSheets()->where('status', 'submitted')->count(),
            'total_salaries' => $project->salaries()->sum('gross_salary'),
            'pending_payments' => $project->payments()->where('status', 'submitted')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    public function getRoleUsers(Request $request, $id)
    {
        if ($response = $this->checkPermission('projects.view')) {
            return $response;
        }

        $project = Project::findOrFail($id);
        $this->checkProjectAccess($request, $project);

        if (!$this->currentUserCanManageProjectRoleUsers($request, $project)) {
            return response()->json([
                'success' => false,
                'message' => '只有管理员或负责人设置人可以维护项目负责人'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'roles' => $this->formatProjectRoleUsers($project),
            ]
        ]);
    }

    public function saveRoleUsers(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $this->checkProjectAccess($request, $project);

        if (!$this->currentUserCanManageProjectRoleUsers($request, $project)) {
            return response()->json([
                'success' => false,
                'message' => '只有管理员或负责人设置人可以维护项目负责人'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'insurance_user_ids' => 'nullable|array',
            'insurance_user_ids.*' => 'integer',
            'salary_user_ids' => 'nullable|array',
            'salary_user_ids.*' => 'integer',
            'delivery_user_ids' => 'nullable|array',
            'delivery_user_ids.*' => 'integer',
            'role_manager_user_ids' => 'nullable|array',
            'role_manager_user_ids.*' => 'integer',
        ], [
            'insurance_user_ids.array' => '保险负责人格式不正确',
            'insurance_user_ids.*.integer' => '保险负责人格式不正确',
            'salary_user_ids.array' => '薪资员格式不正确',
            'salary_user_ids.*.integer' => '薪资员格式不正确',
            'delivery_user_ids.array' => '交付员格式不正确',
            'delivery_user_ids.*.integer' => '交付员格式不正确',
            'role_manager_user_ids.array' => '负责人设置人格式不正确',
            'role_manager_user_ids.*.integer' => '负责人设置人格式不正确',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $service = app(ProjectRoleUserService::class);
        $roleUserMap = [
            ProjectRoleUserService::ROLE_INSURANCE => $service->normalizeUserIds($request->input('insurance_user_ids', [])),
            ProjectRoleUserService::ROLE_SALARY => $service->normalizeUserIds($request->input('salary_user_ids', [])),
            ProjectRoleUserService::ROLE_DELIVERY => $service->normalizeUserIds($request->input('delivery_user_ids', [])),
            ProjectRoleUserService::ROLE_ROLE_MANAGER => $service->normalizeUserIds($request->input('role_manager_user_ids', [])),
        ];

        $allUserIds = collect($roleUserMap)->flatten()->unique()->values()->all();
        if (!empty($allUserIds)) {
            $validUserIds = DB::table('account_set_users')
                ->where('account_set_id', $project->account_set_id)
                ->whereIn('user_id', $allUserIds)
                ->pluck('user_id')
                ->map(fn ($userId) => (int) $userId)
                ->values()
                ->all();

            $invalidUserIds = array_values(array_diff($allUserIds, $validUserIds));
            if (!empty($invalidUserIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '存在不属于当前账套的人员，无法保存',
                    'errors' => [
                        'user_ids' => ['存在不属于当前账套的人员，无法保存']
                    ]
                ], 422);
            }
        }

        foreach ($roleUserMap as $roleType => $userIds) {
            $service->syncProjectRoleUsers($project, $roleType, $userIds);
        }

        return response()->json([
            'success' => true,
            'message' => '项目负责人设置已保存',
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'roles' => $this->formatProjectRoleUsers($project),
            ]
        ]);
    }

    /**
     * 获取项目的社保地区列表
     */
    public function getSocialSecurityRegions(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $regions = \App\Models\SocialSecurityRegion::whereIn('id', $project->social_security_regions ?? [])
            ->with(['socialSecurityTypes'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取项目的公积金地区列表
     */
    public function getHousingFundRegions(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $regions = \App\Models\HousingFundRegion::whereIn('id', $project->housing_fund_regions ?? [])->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取项目的医保地区
     */
    public function getMedicalInsuranceRegions(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $regions = $project->medicalInsuranceRegions()->with(['medicalInsuranceTypes'])->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 设置项目的社保地区
     */
    public function setSocialSecurityRegions(Request $request, $id)
    {
        $validator = $this->makeProjectValidator($request->all(), [
            'region_ids' => 'present|array',
            'region_ids.*' => 'exists:social_security_regions,id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证地区是否属于当前账套
        $validRegions = \App\Models\SocialSecurityRegion::where('account_set_id', $project->account_set_id)
            ->whereIn('id', $request->region_ids)
            ->pluck('id')
            ->toArray();

        if (count($validRegions) !== count($request->region_ids)) {
            return response()->json([
                'success' => false,
                'message' => '部分社保地区不属于当前账套'
            ], 422);
        }

        $project->update([
            'social_security_regions' => $validRegions
        ]);
        $this->logProjectInsuranceSelectionChange($project, 'social_security', $validRegions);

        return response()->json([
            'success' => true,
            'message' => '社保地区设置成功',
            'data' => $project->social_security_regions
        ]);
    }

    /**
     * 设置项目的公积金地区
     */
    public function setHousingFundRegions(Request $request, $id)
    {
        $validator = $this->makeProjectValidator($request->all(), [
            'region_ids' => 'present|array',
            'region_ids.*' => 'exists:housing_fund_regions,id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证地区是否属于当前账套
        $validRegions = \App\Models\HousingFundRegion::where('account_set_id', $project->account_set_id)
            ->whereIn('id', $request->region_ids)
            ->pluck('id')
            ->toArray();

        if (count($validRegions) !== count($request->region_ids)) {
            return response()->json([
                'success' => false,
                'message' => '部分公积金地区不属于当前账套'
            ], 422);
        }

        $project->update([
            'housing_fund_regions' => $validRegions
        ]);
        $this->logProjectInsuranceSelectionChange($project, 'housing_fund', $validRegions);

        return response()->json([
            'success' => true,
            'message' => '公积金地区设置成功',
            'data' => $project->housing_fund_regions
        ]);
    }

    /**
     * 设置项目的医保地区
     */
    public function setMedicalInsuranceRegions(Request $request, $id)
    {
        $validator = $this->makeProjectValidator($request->all(), [
            'region_ids' => 'present|array',
            'region_ids.*' => 'exists:medical_insurance_regions,id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证地区是否属于当前账套
        $validRegions = \App\Models\MedicalInsuranceRegion::where('account_set_id', $project->account_set_id)
            ->whereIn('id', $request->region_ids)
            ->pluck('id')
            ->toArray();

        if (count($validRegions) !== count($request->region_ids)) {
            return response()->json([
                'success' => false,
                'message' => '部分医保地区不属于当前账套'
            ], 422);
        }

        DB::transaction(function () use ($project, $validRegions) {
            $syncData = [];
            foreach ($validRegions as $regionId) {
                $syncData[$regionId] = ['account_set_id' => $project->account_set_id];
            }

            $project->medicalInsuranceRegions()->sync($syncData);
            $this->syncLargeMedicalInsuranceConfigsFromMedicalRegions($project);
        });

        $this->logProjectInsuranceSelectionChange($project, 'medical_insurance', $validRegions);

        return response()->json([
            'success' => true,
            'message' => '医保地区设置成功',
            'data' => $validRegions
        ]);
    }

    /**
     * 获取项目绑定的其他保险保单
     */
    public function getOtherInsurancePolicies(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $policies = $project->otherInsurancePolicies()->get();

        return response()->json([
            'success' => true,
            'data' => $policies
        ]);
    }

    /**
     * 设置项目绑定的其他保险保单
     */
    public function setOtherInsurancePolicies(Request $request, $id)
    {
        $validator = $this->makeProjectValidator($request->all(), [
            'policy_ids' => 'nullable|array',
            'policy_ids.*' => 'exists:other_insurance_policies,id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证保单是否属于当前账套
        $validPolicies = \App\Models\OtherInsurancePolicy::where('account_set_id', $project->account_set_id)
            ->whereIn('id', $request->policy_ids)
            ->get();

        if ($validPolicies->count() !== count($request->policy_ids)) {
            return response()->json([
                'success' => false,
                'message' => '部分保单不属于当前账套'
            ], 422);
        }

        // 检查每种保险类型只能绑定一个保单
        $typeIds = $validPolicies->pluck('type_id')->toArray();
        if (count($typeIds) !== count(array_unique($typeIds))) {
            return response()->json([
                'success' => false,
                'message' => '每种保险类型只能绑定一个保单'
            ], 422);
        }

        // 获取旧的保单列表（变更前）
        $oldPolicyIds = DB::table('project_other_insurance_policies')
            ->where('project_id', $project->id)
            ->pluck('policy_id')
            ->toArray();
        
        // 同步关联关系，并设置account_set_id
        $syncData = [];
        foreach ($request->policy_ids as $policyId) {
            $syncData[$policyId] = ['account_set_id' => $project->account_set_id];
        }
        $project->otherInsurancePolicies()->sync($syncData);

        // ✅ 新增：检测保单变更，自动创建增减记录
        $newPolicyIds = $request->policy_ids;
        
        // 比较变更
        $addedPolicies = array_diff($newPolicyIds, $oldPolicyIds);    // 新增的保单
        $removedPolicies = array_diff($oldPolicyIds, $newPolicyIds);  // 删除的保单
        
        if (!empty($addedPolicies) || !empty($removedPolicies)) {
            // 有变更，触发自动导入
            $this->triggerOtherInsuranceChangeForProject($project, $oldPolicyIds, $newPolicyIds);
            
            \Log::info('检测到项目其他保险保单变更', [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'old_policies' => $oldPolicyIds,
                'new_policies' => $newPolicyIds,
                'added' => $addedPolicies,
                'removed' => $removedPolicies
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '其他保险保单设置成功',
            'data' => $request->policy_ids
        ]);
    }

    /**
     * 获取项目绑定的大额医疗保险配置
     */
    public function getLargeMedicalInsuranceConfigs(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $configs = $project->getResolvedLargeMedicalInsuranceConfigs();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * 设置项目绑定的大额医疗保险配置
     */
    public function setLargeMedicalInsuranceConfigs(Request $request, $id)
    {
        $validator = $this->makeProjectValidator($request->all(), [
            'config_ids' => 'present|array',
            'config_ids.*' => 'exists:large_medical_insurance_configs,id'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证配置是否属于当前账套（如果有配置的话）
        $configIds = $request->config_ids ?? [];
        if (!empty($configIds)) {
            $validConfigs = \App\Models\LargeMedicalInsuranceConfig::where('account_set_id', $project->account_set_id)
                ->whereIn('id', $configIds)
                ->get();

            if ($validConfigs->count() !== count($configIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '部分配置不属于当前账套'
                ], 422);
            }
        }

        // 同步关联关系，并设置account_set_id
        $syncData = [];
        foreach ($configIds as $configId) {
            $syncData[$configId] = ['account_set_id' => $project->account_set_id];
        }
        $project->largeMedicalInsuranceConfigs()->sync($syncData);
        $this->logProjectInsuranceSelectionChange($project, 'large_medical_insurance', $configIds);

        return response()->json([
            'success' => true,
            'message' => '大额医疗保险配置设置成功',
            'data' => $request->config_ids
        ]);
    }

    /**
     * 获取可用的社保地区列表（用于项目设置）
     */
    public function getAvailableSocialSecurityRegions(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        // 检查用户是否有权限访问此账套
        if ($user->role !== 'admin') {
            $hasAccess = \App\Models\AccountSet::whereHas('users', function($query) use ($user, $accountSetId) {
                $query->where('user_id', $user->id)
                      ->where('account_set_users.account_set_id', $accountSetId);
            })->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }
        
        // 管理员可以访问所有账套，无需额外检查

        $regions = \App\Models\SocialSecurityRegion::where('account_set_id', $accountSetId)
            ->with(['socialSecurityTypes'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取可用的公积金地区列表（用于项目设置）
     */
    public function getAvailableHousingFundRegions(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        // 检查用户是否有权限访问此账套
        if ($user->role !== 'admin') {
            $hasAccess = \App\Models\AccountSet::whereHas('users', function($query) use ($user, $accountSetId) {
                $query->where('user_id', $user->id)
                      ->where('account_set_users.account_set_id', $accountSetId);
            })->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }
        
        // 管理员可以访问所有账套，无需额外检查

        $regions = \App\Models\HousingFundRegion::where('account_set_id', $accountSetId)->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取可用的大额医疗保险地区列表（用于项目设置）
     */
    public function getAvailableLargeMedicalInsuranceRegions(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        // 检查用户是否有权限访问此账套
        if ($user->role !== 'admin') {
            $hasAccess = \App\Models\AccountSet::whereHas('users', function($query) use ($user, $accountSetId) {
                $query->where('user_id', $user->id)
                      ->where('account_set_users.account_set_id', $accountSetId);
            })->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        $configs = \App\Models\LargeMedicalInsuranceConfig::where('account_set_id', $accountSetId)
            ->where('status', 1)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * 获取可用的医保地区列表（用于项目设置）
     */
    public function getAvailableMedicalInsuranceRegions(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        // 非管理员需要检查账套访问权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_sets.id', $accountSetId)->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }
        
        // 管理员可以访问所有账套，无需额外检查

        $regions = \App\Models\MedicalInsuranceRegion::where('account_set_id', $accountSetId)
            ->with(['medicalInsuranceTypes'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取可用的其他保险保单列表（用于项目设置）
     */
    public function getAvailableOtherInsurancePolicies(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        // 非管理员需要检查账套访问权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_sets.id', $accountSetId)->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }
        
        // 管理员可以访问所有账套，无需额外检查

        $policies = \App\Models\OtherInsurancePolicy::where('account_set_id', $accountSetId)
            ->where('status', 'active')
            ->with(['type'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $policies
        ]);
    }

    /**
     * 项目其他保险保单变更时，为该项目的所有员工创建增减记录
     */
    private function triggerOtherInsuranceChangeForProject($project, $oldPolicyIds, $newPolicyIds)
    {
        try {
            // 获取该项目下的所有在职员工
            $employees = $project->employees()
                ->where('contract_status', 'active')
                ->get();
            
            if ($employees->isEmpty()) {
                \Log::info('项目下没有在职员工，跳过创建增减记录', [
                    'project_id' => $project->id
                ]);
                return;
            }
            
            $detectionService = app(\App\Services\InsuranceChangeDetectionService::class);
            
            foreach ($employees as $employee) {
                $selectedPolicyIds = array_values(array_unique(array_filter(array_map(function ($id) {
                    return is_numeric($id) ? (int) $id : null;
                }, (array) ($employee->other_insurance_policy_ids ?? [])))));

                $oldSelectedPolicyIds = array_values(array_intersect($selectedPolicyIds, array_map('intval', $oldPolicyIds)));
                $newSelectedPolicyIds = array_values(array_intersect($selectedPolicyIds, array_map('intval', $newPolicyIds)));

                sort($oldSelectedPolicyIds);
                sort($newSelectedPolicyIds);

                if ($oldSelectedPolicyIds === $newSelectedPolicyIds) {
                    continue;
                }

                $detectionService->triggerChange([
                    'scope' => \App\Services\InsuranceChangeDetectionService::SCOPE_EMPLOYEE,
                    'change_type' => 'other_insurance',
                    'employee' => $employee,
                    'project_id' => $project->id,
                    'old_data' => ['policies' => $oldSelectedPolicyIds],
                    'new_data' => ['policies' => $newSelectedPolicyIds],
                    'source' => 'project_other_insurance_policy_change',
                ]);
            }
            
            \Log::info('项目其他保险保单变更处理完成', [
                'project_id' => $project->id,
                'affected_employees' => $employees->count(),
                'old_policies' => $oldPolicyIds,
                'new_policies' => $newPolicyIds
            ]);
            
        } catch (\Exception $e) {
            \Log::error('触发项目保单变更失败', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 获取可用的占位符字段列表
     */
    /**
     * 项目层面的四类保险配置只更新项目可选范围，不直接触发参保增减。
     * 实际增减仍以员工档案或人员绑定中的具体员工变更为准。
     */
    private function logProjectInsuranceSelectionChange(Project $project, string $insuranceType, array $selectedIds): void
    {
        \Log::info('项目保险配置已更新（仅更新项目配置，不自动触发参保增减）', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'insurance_type' => $insuranceType,
            'selected_ids' => array_values($selectedIds),
        ]);
    }

    private function syncLargeMedicalInsuranceConfigsFromMedicalRegions(Project $project): array
    {
        $configIds = $project->getResolvedLargeMedicalInsuranceConfigs()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        $syncData = [];
        foreach ($configIds as $configId) {
            $syncData[$configId] = ['account_set_id' => $project->account_set_id];
        }

        $project->largeMedicalInsuranceConfigs()->sync($syncData);
        $this->logProjectInsuranceSelectionChange($project, 'large_medical_insurance', $configIds);

        return $configIds;
    }

    public function getAvailablePlaceholderFields()
    {
        return response()->json([
            'success' => true,
            'data' => Project::getAvailablePlaceholderFields()
        ]);
    }

    /**
     * 获取项目的占位符字段配置
     */
    public function getPlaceholderFields(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->checkProjectAccess($request, $project);

        return response()->json([
            'success' => true,
            'data' => $project->placeholder_fields ?? []
        ]);
    }

    /**
     * 保存项目的占位符字段配置
     */
    public function savePlaceholderFields(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->checkProjectAccess($request, $project);

        $validator = $this->makeProjectValidator($request->all(), [
            'placeholder_fields' => 'present|array',
            'placeholder_fields.*.key' => 'required|string',
            'placeholder_fields.*.label' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $availablePlaceholderFields = Project::getAvailablePlaceholderFields();
        $rawFields = $request->input('placeholder_fields', []);
        $normalizedFields = [];

        foreach ($rawFields as $field) {
            // 兼容前端仅上传 key 字符串的场景
            if (is_string($field)) {
                $key = trim($field);
                if ($key === '') {
                    continue;
                }
                $normalizedFields[] = [
                    'key' => $key,
                    'label' => $availablePlaceholderFields[$key] ?? $key,
                ];
                continue;
            }

            if (!is_array($field)) {
                continue;
            }

            $key = trim((string)($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $label = trim((string)($field['label'] ?? ''));
            if ($label === '') {
                $label = $availablePlaceholderFields[$key] ?? $key;
            }

            $normalizedFields[] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        // 去重，避免重复 key
        $uniqueFields = [];
        foreach ($normalizedFields as $field) {
            $uniqueFields[$field['key']] = $field;
        }

        $project->placeholder_fields = array_values($uniqueFields);
        $project->save();

        return response()->json([
            'success' => true,
            'message' => '占位符字段配置保存成功',
            'data' => $project->placeholder_fields
        ]);
    }
}
