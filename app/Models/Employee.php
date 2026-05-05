<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Traits\Auditable;

class Employee extends Model
{
    use HasFactory, HasApiTokens, Auditable;

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'name' => '姓名',
        'position' => '岗位',
        'employee_number' => '工号',
        'id_number' => '身份证号',
        'phone' => '手机号',
        'email' => '邮箱',
        'gender' => '性别',
        'birth_date' => '出生日期',
        'nationality' => '国籍',
        'marital_status' => '婚姻状况',
        'education' => '学历',
        'address' => '地址',
        'emergency_contact' => '紧急联系人',
        'emergency_phone' => '紧急联系电话',
        'hire_date' => '入职日期',
        'contract_start_date' => '合同开始日期',
        'contract_end_date' => '合同结束日期',
        'probation_end_date' => '试用期结束日期',
        'contract_months' => '合同月数',
        'contract_status' => '合同状态',
        'termination_date' => '离职日期',
        'termination_reason' => '离职原因',
        'retirement_date' => '退休日期',
        'retirement_category' => '退休类别',
        'bank_name' => '开户行',
        'bank_account' => '银行账号',
        'bank_account_holder' => '户名',
        'bank_branch' => '开户支行',
        'remittance_remark' => '汇款备注',
        'basic_salary' => '基本工资',
        'social_security_base' => '社保基数',
        'medical_insurance_base' => '医保基数',
        'housing_fund_base' => '公积金基数',
        'large_medical_base' => '大额医疗基数',
        'large_medical_company_base' => '大额医疗公司基数',
        'special_deduction' => '专项扣除',
        'personal_investment_amount' => '个人投资金额',
        'personal_investment_ratio' => '个人投资比例',
        'social_security_region_id' => '社保参保地区',
        'medical_insurance_region_id' => '医保参保地区',
        'housing_fund_region_id' => '公积金参保地区',
        'social_insurance_enrollment_date' => '社保参保日期',
        'provident_fund_enrollment_date' => '公积金参保日期',
        'medical_insurance_enrollment_date' => '医保参保日期',
        'large_medical_enrollment_date' => '大额医疗参保日期',
        'signing_location' => '签署地',
        'household_type' => '户口类型',
        'id_card_valid_from' => '身份证有效期开始',
        'id_card_valid_until' => '身份证有效期结束',
    ];

    protected $fillable = [
        'account_set_id',
        'name',
        'position', // 岗位
        'employee_number', // 新增工号字段
        'id_number',
        'phone',
        'email',
        'gender',
        'birth_date',
        'nationality',
        'marital_status',
        'education',
        'address',
        'native_place',
        'project_ids',
        'emergency_contact',
        'emergency_phone',
        'hire_date',
        'contract_start_date',
        'contract_end_date',
        'probation_end_date', // 试用期结束日期
        'contract_months', // 签订月份数量
        'contract_status',
        'termination_date',
        'termination_reason',
        'is_retired',
        'retirement_date',
        'retirement_category', // 退休类别：cadre-管理岗, worker-普通岗
        'bank_name',
        'bank_account',
        'bank_account_holder', // 户名
        'bank_branch',
        'remittance_remark',
        'basic_salary',
        'salary_items',
        'social_security_base',
        'social_security_enrollment_month', // 社保参保月份
        'medical_insurance_base',
        'housing_fund_base',
        'housing_fund_enrollment_month', // 公积金参保月份
        'large_medical_base',
        'large_medical_company_base',
        'has_social_security',
        'has_housing_fund',
        'has_work_injury',
        'has_liability_insurance',
        'has_accident_insurance',
        'has_employer_insurance',
        'special_deduction',
        'is_annual_deduction',
        'password',  // 【小程序登录】
        'social_security_region_id',  // 【社保参保地区ID】
        'medical_insurance_region_id',  // 【医保参保地区ID】
        'housing_fund_region_id',  // 【公积金参保地区ID】
        'housing_fund_config_id',  // 【公积金配置ID】
        'large_medical_insurance_config_id',  // 【大额医疗保险配置ID】
        'insurance_completed_at',  // 【参保完成时间】
        'password_changed_at',
        'login_failed_count',
        'locked_until',
        'last_login_at',
        'last_login_ip',
        // 新增详细字段
        'country_region', 'chinese_name', 'birth_country', 'other_id_type', 'other_id_number',
        'personnel_status', 'employment_type', 'employment_date', 'resignation_date', 
        'signing_location', // 签署地
        'household_type', // 户口类型：agricultural-农业, non_agricultural-非农业
        'annual_employment_status', 'job_title',
        'is_disabled', 'disability_cert_type', 'disability_cert_number', 
        'is_martyr_family', 'martyr_family_cert_number', 'is_elderly_alone',
        'tax_matter', 'deduct_expense', 'personal_investment_amount', 'personal_investment_ratio',
        'first_entry_date', 'expected_departure_date',
        'email_address', 'bank_province',
        'other_notes',
        // 地址信息
        'household_province', 'household_city', 'household_district', 'household_address',
        'residence_province', 'residence_city', 'residence_district', 'residence_address',
        'contact_province', 'contact_city', 'contact_district', 'contact_address',
        'remarks',
        // 身份证有效期
        'id_card_valid_from', 'id_card_valid_until',
        'social_insurance_enrollment_date', 'provident_fund_enrollment_date',
        'medical_insurance_enrollment_date', 'large_medical_enrollment_date',
        // 线下入职相关字段
        'is_offline_onboarding', 'offline_onboarding_date', 'contract_upload_deadline', 'contract_uploaded',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'probation_end_date' => 'date',
        'termination_date' => 'date',
        'retirement_date' => 'date',
        'id_card_valid_from' => 'date',
        'id_card_valid_until' => 'date',
        'social_insurance_enrollment_date' => 'date',
        'provident_fund_enrollment_date' => 'date',
        'medical_insurance_enrollment_date' => 'date',
        'large_medical_enrollment_date' => 'date',
        'offline_onboarding_date' => 'date',
        'contract_upload_deadline' => 'date',
        'basic_salary' => 'decimal:2',
        'salary_items' => 'array',
        'social_security_base' => 'decimal:2',
        'housing_fund_base' => 'decimal:2',
        'special_deduction' => 'decimal:2',
        'project_ids' => 'array',
        'has_social_security' => 'boolean',
        'has_housing_fund' => 'boolean',
        'has_work_injury' => 'boolean',
        'has_liability_insurance' => 'boolean',
        'has_accident_insurance' => 'boolean',
        'has_employer_insurance' => 'boolean',
        'is_retired' => 'boolean',
        'is_disabled' => 'boolean',
        'is_martyr_family' => 'boolean',
        'is_elderly_alone' => 'boolean',
        'deduct_expense' => 'boolean',
        'social_security_completed_at' => 'datetime:Y-m-d H:i:s',
        'housing_fund_completed_at' => 'datetime:Y-m-d H:i:s',
        'is_annual_deduction' => 'boolean',
        'is_offline_onboarding' => 'boolean',
        'contract_uploaded' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 模型启动时注册事件
     */
    /**
     * 兼容中文/英文婚姻状态写入 employees 枚举字段
     */
    public function setMaritalStatusAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['marital_status'] = null;
            return;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            $this->attributes['marital_status'] = null;
            return;
        }

        $map = [
            'single' => 'single',
            '未婚' => 'single',
            '未婚未育' => 'single',
            '未婚(未育)' => 'single',
            '未婚（未育）' => 'single',
            'married' => 'married',
            '已婚' => 'married',
            '已婚已育' => 'married',
            '已婚未育' => 'married',
            'divorced' => 'divorced',
            '离婚' => 'divorced',
            '离异' => 'divorced',
            'widowed' => 'widowed',
            '丧偶' => 'widowed',
        ];

        $normalizedKey = strtolower($raw);
        if (array_key_exists($normalizedKey, $map)) {
            $this->attributes['marital_status'] = $map[$normalizedKey];
            return;
        }

        if (array_key_exists($raw, $map)) {
            $this->attributes['marital_status'] = $map[$raw];
            return;
        }

        // 避免 enum 字段写入非法值导致 Data truncated
        $this->attributes['marital_status'] = null;
    }

    protected static function booted()
    {
        // 保存前自动计算退休日期
        static::saving(function ($employee) {
            // 只有当出生日期或性别或退休类别变化时才重新计算
            if ($employee->isDirty(['birth_date', 'gender', 'retirement_category']) 
                || !$employee->retirement_date) {
                $calculatedDate = $employee->calculateRetirementDate();
                if ($calculatedDate) {
                    $employee->retirement_date = $calculatedDate;
                }
            }
        });
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'employee_projects')
                    ->withPivot(['start_date', 'end_date', 'status'])
                    ->withTimestamps();
    }

    public function activeProjects()
    {
        return $this->projects()->wherePivot('status', 'active');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class);
    }

    public function insuranceRecords()
    {
        return $this->hasMany(InsuranceRecord::class);
    }

    public function isContractExpiringSoon($days = 30)
    {
        if (!$this->contract_end_date) {
            return false;
        }
        
        return Carbon::now()->diffInDays($this->contract_end_date) <= $days;
    }

    public function isRetiringSoon($months = 2)
    {
        if (!$this->retirement_date) {
            return false;
        }
        
        return Carbon::now()->diffInMonths($this->retirement_date) <= $months;
    }

    /**
     * 计算法定退休日期（2025年延迟退休政策）
     * 
     * 政策规则（2025年1月1日起实施）：
     * - 男职工：60岁→63岁，每4个月延迟1个月
     * - 女职工（管理岗/原55岁）：55岁→58岁，每4个月延迟1个月
     * - 女职工（普通岗/原50岁）：50岁→55岁，每2个月延迟1个月
     * 
     * @return Carbon|null
     */
    public function calculateRetirementDate(): ?Carbon
    {
        if (!$this->birth_date || !$this->gender) {
            return null;
        }

        $birth = Carbon::parse($this->birth_date);
        $policyStartDate = Carbon::parse('2025-01-01');

        // 根据性别和退休类别确定退休参数
        if ($this->gender === 'male') {
            // 男职工：60岁→63岁，每4个月延1个月
            $originalAge = 60;
            $targetAge = 63;
            $delayPer = 4;
        } elseif ($this->retirement_category === 'cadre') {
            // 女职工管理岗：55岁→58岁，每4个月延1个月
            $originalAge = 55;
            $targetAge = 58;
            $delayPer = 4;
        } else {
            // 女职工普通岗：50岁→55岁，每2个月延1个月
            $originalAge = 50;
            $targetAge = 55;
            $delayPer = 2;
        }

        // 按原政策的退休日期（达到原退休年龄的那个月）
        $originalRetireDate = $birth->copy()->addYears($originalAge);

        // 如果原退休日期在政策实施前，按原政策退休
        if ($originalRetireDate < $policyStartDate) {
            return $originalRetireDate;
        }

        // 计算从政策开始到原退休日期经过的月数
        $monthsFromStart = $policyStartDate->diffInMonths($originalRetireDate);

        // 计算延迟月数（每delayPer个月延迟1个月）
        $delayMonths = intval($monthsFromStart / $delayPer);

        // 延迟月数不能超过最大值
        $maxDelay = ($targetAge - $originalAge) * 12;
        $delayMonths = min($delayMonths, $maxDelay);

        return $originalRetireDate->addMonths($delayMonths);
    }

    /**
     * 获取原法定退休年龄（延迟前）
     * 
     * @return int|null
     */
    public function getOriginalRetirementAge(): ?int
    {
        if (!$this->gender) {
            return null;
        }

        if ($this->gender === 'male') {
            return 60;
        }

        return $this->retirement_category === 'cadre' ? 55 : 50;
    }

    /**
     * 获取目标退休年龄（延迟后最终年龄）
     * 
     * @return int|null
     */
    public function getTargetRetirementAge(): ?int
    {
        if (!$this->gender) {
            return null;
        }

        if ($this->gender === 'male') {
            return 63;
        }

        return $this->retirement_category === 'cadre' ? 58 : 55;
    }

    public function getAgeAttribute()
    {
        return Carbon::parse($this->birth_date)->age;
    }

    // 工资相关访问器
    // 访问器已注释：避免添加千位分隔符，让前端自己格式化
    // public function getBasicSalaryAttribute($value)
    // {
    //     return $value ? number_format($value, 2) : '0.00';
    // }

    public function getContractStatusAttribute($value)
    {
        // 优先检查 is_retired 字段（退休状态）
        if (isset($this->attributes['is_retired']) && $this->attributes['is_retired']) {
            return 'retired';
        }
        
        // 检查 termination_date（离职状态）
        if (isset($this->attributes['termination_date']) && $this->termination_date) {
            return 'terminated';
        }
        
        // 检查合同是否过期
        if (isset($this->attributes['contract_end_date']) && $this->contract_end_date && Carbon::now()->gt($this->contract_end_date)) {
            return 'expired';
        }
        
        return $value;
    }

    /**
     * 设置合同状态（防止 'retired' 值被保存到数据库）
     */
    public function setContractStatusAttribute($value)
    {
        // 如果尝试设置 'retired'，则转换为 'terminated' 并设置 is_retired = true
        // 因为数据库 ENUM 字段只支持 'active', 'expired', 'terminated'
        if ($value === 'retired') {
            // 设置 contract_status 为 terminated（因为退休也是一种终止）
            $this->attributes['contract_status'] = 'terminated';
            // 同时设置 is_retired = true（如果字段存在）
            if (Schema::hasColumn('employees', 'is_retired')) {
                $this->attributes['is_retired'] = true;
            }
        } else {
            // 其他值正常设置（必须是 'active', 'expired', 'terminated' 之一）
            $this->attributes['contract_status'] = $value;
        }
    }

    // 社保参保地区
    public function socialSecurityRegion()
    {
        return $this->belongsTo(SocialSecurityRegion::class, 'social_security_region_id');
    }

    // 医保参保地区
    public function medicalInsuranceRegion()
    {
        return $this->belongsTo(MedicalInsuranceRegion::class, 'medical_insurance_region_id');
    }

    // 公积金参保地区
    public function housingFundRegion()
    {
        return $this->belongsTo(HousingFundRegion::class, 'housing_fund_region_id');
    }

    // 公积金配置
    public function housingFundConfig()
    {
        return $this->belongsTo(HousingFundConfig::class, 'housing_fund_config_id');
    }

    /**
     * 大额医疗保险配置
     */
    public function largeMedicalInsuranceConfigRelation()
    {
        return $this->belongsTo(LargeMedicalInsuranceConfig::class, 'large_medical_insurance_config_id');
    }

    /**
     * 关联员工上传的资料
     */
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * 关联离职证明
     */
    public function resignationCertificates()
    {
        return $this->hasMany(ResignationCertificate::class);
    }

    /**
     * 检查是否已上传离职证明
     */
    public function hasResignationCertificate()
    {
        return $this->resignationCertificates()->exists();
    }

    /**
     * 大额医疗保险配置
     */
    public function largeMedicalInsurance()
    {
        return $this->hasOne(EmployeeLargeMedicalInsurance::class, 'employee_id');
    }

    /**
     * 入职登记表
     */
    public function onboardingForm()
    {
        return $this->hasOne(OnboardingForm::class);
    }

    /**
     * 从业人员登记表
     */
    public function registrationForm()
    {
        return $this->hasOne(EmployeeRegistrationForm::class);
    }

    /**
     * 大额医疗保险配置（别名）
     */
    public function largeMedicalInsuranceConfig()
    {
        return $this->belongsTo(LargeMedicalInsuranceConfig::class, 'large_medical_insurance_config_id');
    }
}
