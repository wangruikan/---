<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Traits\Auditable;

class Project extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '项目';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'name' => '项目名称',
        'description' => '项目描述',
        'code' => '项目编码',
        'status' => '状态',
        'start_date' => '开始日期',
        'end_date' => '结束日期',
        'social_security_location' => '社保缴纳地',
        'salary_payment_date' => '工资发放日',
        'salary_payment_month' => '工资发放月份',
        'insurance_import_month' => '保险导入月份',
        'requires_attendance' => '是否需要考勤',
        'requires_salary_basis' => '是否需要工资依据',
        'requires_attendance_basis' => '是否需要考勤依据',
        'delivery_frequency' => '交付频率',
        'delivery_method' => '交付方式',
        'registration_form_type' => '登记表类型',
        'invoice_infos' => '开票信息',
        'invoice_company_name' => '开票企业名称',
        'invoice_tax_number' => '开票企业税号',
        'invoice_company_address' => '开票企业地址',
        'invoice_company_phone' => '开票企业电话',
        'invoice_bank_name' => '开户银行',
        'invoice_bank_account' => '银行账户',
        'invoice_bank_code' => '行号',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->name;
    }

    protected $fillable = [
        'name',
        'description',
        'code',
        'status',
        'start_date',
        'end_date',
        'social_security_location',
        'insurance_types',
        'salary_payment_date',
        'salary_payment_month',      // 工资发放月份：current-本月，next-次月
        'insurance_import_month',    // 保险导入设置：current-当月，next-次月，none-不导入
        'requires_attendance',
        'require_attendance',        // 是否需要考勤：1-需要，0-不需要
        'requires_salary_basis',     // 是否需要上传工资依据
        'requires_attendance_basis', // 是否需要上传考勤依据
        'delivery_requirements',
        'delivery_frequency',
        'delivery_method',
        'account_set_id',  // 【账套关联】
        'contract_notice_file_id',  // 【劳动合同须知文件ID】单个文件
        'contract_notice_files',  // 【劳动合同须知文件ID列表】逗号分隔
        'notice_placeholder_positions',  // 【须知文件签名占位符配置】按文件ID映射
        'labor_contract_notice_name',  // 【劳动合同须知文件名称】
        'labor_contract_notice_file',  // 【劳动合同须知文件路径】
        'social_security_regions',  // 【社保地区ID列表】
        'medical_insurance_regions',  // 【医保地区ID列表】
        'housing_fund_regions',  // 【公积金地区ID列表】
        'placeholder_fields',  // 【占位符可用字段配置】
        'registration_form_type',  // 【登记表类型】onboarding-入职登记表，registration-从业人员登记表
        'invoice_infos',
        'invoice_company_name',
        'invoice_tax_number',
        'invoice_company_address',
        'invoice_company_phone',
        'invoice_bank_name',
        'invoice_bank_account',
        'invoice_bank_code',
    ];

    protected $casts = [
        'insurance_types' => 'array',
        'delivery_requirements' => 'array',
        'requires_attendance' => 'boolean',
        'require_attendance' => 'boolean',
        'requires_salary_basis' => 'boolean',
        'requires_attendance_basis' => 'boolean',
        'salary_payment_date' => 'integer',  // 每月几号（1-31）
        'social_security_regions' => 'array',
        'medical_insurance_regions' => 'array',
        'housing_fund_regions' => 'array',
        'placeholder_fields' => 'array',
        'notice_placeholder_positions' => 'array',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public static function normalizeInvoiceInfos($invoiceInfos): array
    {
        if (is_string($invoiceInfos)) {
            $decoded = json_decode($invoiceInfos, true);
            $invoiceInfos = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($invoiceInfos)) {
            return [];
        }

        $normalized = [];

        foreach ($invoiceInfos as $invoiceInfo) {
            if (!is_array($invoiceInfo)) {
                continue;
            }

            $normalizedItem = [
                'remark' => trim((string) ($invoiceInfo['remark'] ?? '')),
                'company_name' => trim((string) ($invoiceInfo['company_name'] ?? $invoiceInfo['invoice_company_name'] ?? '')),
                'tax_number' => trim((string) ($invoiceInfo['tax_number'] ?? $invoiceInfo['invoice_tax_number'] ?? '')),
                'company_address' => trim((string) ($invoiceInfo['company_address'] ?? $invoiceInfo['invoice_company_address'] ?? '')),
                'company_phone' => trim((string) ($invoiceInfo['company_phone'] ?? $invoiceInfo['invoice_company_phone'] ?? '')),
                'bank_name' => trim((string) ($invoiceInfo['bank_name'] ?? $invoiceInfo['invoice_bank_name'] ?? '')),
                'bank_account' => trim((string) ($invoiceInfo['bank_account'] ?? $invoiceInfo['invoice_bank_account'] ?? '')),
                'bank_code' => trim((string) ($invoiceInfo['bank_code'] ?? $invoiceInfo['invoice_bank_code'] ?? '')),
            ];

            $hasValue = collect($normalizedItem)
                ->except('remark')
                ->contains(fn ($value) => $value !== '');

            if (!$hasValue && $normalizedItem['remark'] === '') {
                continue;
            }

            $normalized[] = $normalizedItem;
        }

        return array_values($normalized);
    }

    public static function buildLegacyInvoiceInfoFromAttributes(array $attributes): ?array
    {
        $legacyInfo = [
            'remark' => '默认开票信息',
            'company_name' => trim((string) ($attributes['invoice_company_name'] ?? '')),
            'tax_number' => trim((string) ($attributes['invoice_tax_number'] ?? '')),
            'company_address' => trim((string) ($attributes['invoice_company_address'] ?? '')),
            'company_phone' => trim((string) ($attributes['invoice_company_phone'] ?? '')),
            'bank_name' => trim((string) ($attributes['invoice_bank_name'] ?? '')),
            'bank_account' => trim((string) ($attributes['invoice_bank_account'] ?? '')),
            'bank_code' => trim((string) ($attributes['invoice_bank_code'] ?? '')),
        ];

        $hasLegacyValue = collect($legacyInfo)
            ->except('remark')
            ->contains(fn ($value) => $value !== '');

        return $hasLegacyValue ? $legacyInfo : null;
    }

    public static function resolveInvoiceInfos($invoiceInfos, array $fallbackAttributes = []): array
    {
        $normalized = static::normalizeInvoiceInfos($invoiceInfos);
        if (!empty($normalized)) {
            return $normalized;
        }

        $legacyInfo = static::buildLegacyInvoiceInfoFromAttributes($fallbackAttributes);

        return $legacyInfo ? [$legacyInfo] : [];
    }

    public function getSalaryStartMonth(): ?string
    {
        return $this->start_date ? Carbon::parse($this->start_date)->format('Y-m') : null;
    }

    public function getSalaryEndMonth(): ?string
    {
        return $this->end_date ? Carbon::parse($this->end_date)->format('Y-m') : null;
    }

    public function usesNextMonthSalary(): bool
    {
        return ($this->salary_payment_month ?? 'current') === 'next';
    }

    public function isSalaryPeriodReleased(string $salaryMonth, ?string $referenceMonth = null): bool
    {
        if (!$this->usesNextMonthSalary()) {
            return true;
        }

        $referenceMonth = $referenceMonth ?: Carbon::now('Asia/Shanghai')->format('Y-m');
        return $salaryMonth < $referenceMonth;
    }

    public function canCreateSalaryForMonth(string $salaryMonth, bool $hasSalaryHistory, ?string $referenceMonth = null): bool
    {
        $startMonth = $this->getSalaryStartMonth();
        $endMonth = $this->getSalaryEndMonth();

        if (!$this->isSalaryPeriodReleased($salaryMonth, $referenceMonth)) {
            return false;
        }

        if (!$hasSalaryHistory) {
            return !$startMonth || $salaryMonth === $startMonth;
        }

        if ($startMonth && $salaryMonth < $startMonth) {
            return false;
        }

        if ($endMonth && $salaryMonth > $endMonth) {
            return false;
        }

        return true;
    }

    public function resolveSalaryTaskMonth(?string $referenceMonth = null): string
    {
        $referenceMonth = $referenceMonth ?: Carbon::now('Asia/Shanghai')->format('Y-m');

        if (!$this->usesNextMonthSalary()) {
            return $referenceMonth;
        }

        return Carbon::createFromFormat('Y-m', $referenceMonth, 'Asia/Shanghai')
            ->subMonth()
            ->format('Y-m');
    }

    public function resolveBasisMonth(?string $processingMonth = null): string
    {
        return $this->resolveSalaryTaskMonth($processingMonth);
    }

    public function resolveBasisProcessingMonth(string $basisMonth): string
    {
        if (!$this->usesNextMonthSalary()) {
            return $basisMonth;
        }

        return Carbon::createFromFormat('Y-m', $basisMonth, 'Asia/Shanghai')
            ->addMonth()
            ->format('Y-m');
    }

    public function isPayrollBusinessMonthAvailable(string $businessMonth, ?string $processingMonth = null): bool
    {
        $processingMonth = $processingMonth ?: Carbon::now('Asia/Shanghai')->format('Y-m');

        if (!$this->isSalaryPeriodReleased($businessMonth, $processingMonth)) {
            return false;
        }

        $startMonth = $this->getSalaryStartMonth();
        if ($startMonth && $businessMonth < $startMonth) {
            return false;
        }

        $endMonth = $this->getSalaryEndMonth();
        if ($endMonth && $businessMonth > $endMonth) {
            return false;
        }

        return true;
    }

    public static function syncLegacyInvoiceFields(array &$data, array $invoiceInfos): void
    {
        $primaryInvoiceInfo = $invoiceInfos[0] ?? [];

        $data['invoice_company_name'] = $primaryInvoiceInfo['company_name'] ?? '';
        $data['invoice_tax_number'] = $primaryInvoiceInfo['tax_number'] ?? '';
        $data['invoice_company_address'] = $primaryInvoiceInfo['company_address'] ?? '';
        $data['invoice_company_phone'] = $primaryInvoiceInfo['company_phone'] ?? '';
        $data['invoice_bank_name'] = $primaryInvoiceInfo['bank_name'] ?? '';
        $data['invoice_bank_account'] = $primaryInvoiceInfo['bank_account'] ?? '';
        $data['invoice_bank_code'] = $primaryInvoiceInfo['bank_code'] ?? '';
    }

    public function getInvoiceInfosAttribute($value): array
    {
        return static::resolveInvoiceInfos($value, $this->attributes);
    }

    public function setInvoiceInfosAttribute($value): void
    {
        $normalized = static::normalizeInvoiceInfos($value);

        $this->attributes['invoice_infos'] = empty($normalized)
            ? null
            : json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 获取可用的占位符字段列表（带标签）
     */
    public static function getAvailablePlaceholderFields()
    {
        return [
            'name' => '姓名',
            'id_number' => '身份证号',
            'phone' => '手机号',
            'address' => '地址',
            'gender' => '性别',
            'birth_date' => '出生日期',
            'nationality' => '民族',
            'education' => '学历',
            'position' => '岗位',
            'previous_company' => '上个公司',
            'employee_number' => '工号',
            'contract_number' => '合同编号（引用工号）',
            'email' => '邮箱',
            'bank_name' => '开户银行',
            'bank_account' => '银行卡号',
            'bank_account_holder' => '开户名',
            'basic_salary' => '基础薪资',
            'comprehensive_salary' => '综合薪资',
            'probation_salary' => '试用期薪资',
            'performance_salary' => '绩效薪资',
            'signing_location' => '签署地',
            'household_type' => '户口类型',
            'gender_male_check' => '性别-男（打勾）',
            'gender_female_check' => '性别-女（打勾）',
            'household_agricultural_check' => '户籍-农业（打勾）',
            'household_non_agricultural_check' => '户籍-非农业（打勾）',
            'hire_date' => '入职日期',
            'contract_sign_date' => '签订日期',
            'contract_sign_year' => '签订年',
            'contract_sign_month' => '签订月',
            'contract_sign_day' => '签订日',
            'contract_start_date' => '合同开始日期',
            'contract_end_date' => '合同结束日期',
            'contract_months' => '签订月份数量',
            'contract_start_year' => '合同开始年',
            'contract_start_month' => '合同开始月',
            'contract_start_day' => '合同开始日',
            'contract_end_year' => '合同结束年',
            'contract_end_month' => '合同结束月',
            'contract_end_day' => '合同结束日',
            'emergency_contact' => '紧急联系人',
            'emergency_phone' => '紧急联系电话',
            'household_address' => '户籍地址',
            'residence_address' => '居住地址',
            'contact_address' => '通讯地址',
            'employee_signature' => '员工签字',
            'company_stamp' => '公司盖章',
            'slash_placeholder' => '/占位符',
        ];
    }

    protected $appends = [
        'social_security_regions_data',
        'medical_insurance_regions_data',
        'housing_fund_regions_data',
    ];

    public function employees()
    {
        $pivotFields = ['start_date', 'end_date', 'status'];
        if (Schema::hasColumn('employee_projects', 'document_set_id')) {
            $pivotFields[] = 'document_set_id';
        }

        return $this->belongsToMany(Employee::class, 'employee_projects')
                    ->withPivot($pivotFields)
                    ->withTimestamps();
    }

    public function activeEmployees()
    {
        return $this->employees()->wherePivot('status', 'active');
    }

    public function attendanceSheets()
    {
        return $this->hasMany(AttendanceSheet::class);
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class);
    }

    public function insuranceRecords()
    {
        return $this->hasMany(InsuranceRecord::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function recruitment()
    {
        return $this->hasMany(Recruitment::class);
    }

    public function roleAssignments()
    {
        return $this->hasMany(ProjectRoleUser::class);
    }

    // 获取项目的社保地区（Accessor）
    public function getSocialSecurityRegionsDataAttribute()
    {
        if (empty($this->social_security_regions)) {
            return [];
        }
        return SocialSecurityRegion::whereIn('id', $this->social_security_regions)->get();
    }

    // 获取项目的医保地区（Accessor）
    public function getMedicalInsuranceRegionsDataAttribute()
    {
        if (empty($this->medical_insurance_regions)) {
            return [];
        }
        return MedicalInsuranceRegion::whereIn('id', $this->medical_insurance_regions)->get();
    }

    // 获取项目的公积金地区（Accessor）
    public function getHousingFundRegionsDataAttribute()
    {
        if (empty($this->housing_fund_regions)) {
            return [];
        }
        return HousingFundRegion::whereIn('id', $this->housing_fund_regions)->get();
    }

    // 获取项目的医保地区
    public function medicalInsuranceRegions()
    {
        return $this->belongsToMany(MedicalInsuranceRegion::class, 'project_medical_insurance', 'project_id', 'region_id')
            ->withPivot('account_set_id')
            ->withTimestamps();
    }

    // 获取项目绑定的其他保险保单
    public function otherInsurancePolicies()
    {
        return $this->belongsToMany(OtherInsurancePolicy::class, 'project_other_insurance_policies', 'project_id', 'policy_id')
            ->withPivot('account_set_id')
            ->withTimestamps()
            ->with(['type']);
    }

    /**
     * 关联项目资料配置
     */
    public function documentConfigs()
    {
        return $this->hasMany(ProjectDocumentConfig::class);
    }

    public function documentSets()
    {
        return $this->hasMany(ProjectDocumentSet::class)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * 大额医疗保险配置
     */
    public function largeMedicalInsuranceConfigs()
    {
        return $this->belongsToMany(
            LargeMedicalInsuranceConfig::class,
            'project_large_medical_insurance',
            'project_id',
            'config_id'
        )->withTimestamps();
    }

    /**
     * 根据项目绑定的医保地区解析对应的大额医疗配置
     */
    public function getResolvedLargeMedicalInsuranceConfigs()
    {
        $medicalRegions = $this->relationLoaded('medicalInsuranceRegions')
            ? collect($this->medicalInsuranceRegions)
            : $this->medicalInsuranceRegions()->get();

        $regionNames = $medicalRegions
            ->map(function ($region) {
                return trim((string) ($region->name ?? $region->region_name ?? ''));
            })
            ->filter()
            ->unique()
            ->values();

        if ($regionNames->isEmpty()) {
            return collect();
        }

        return LargeMedicalInsuranceConfig::where('account_set_id', $this->account_set_id)
            ->where('status', 1)
            ->whereIn('region_name', $regionNames->all())
            ->get();
    }

}
