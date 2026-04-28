<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
            'employee_number' => '工号',
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
        ];
    }

    protected $appends = [
        'social_security_regions_data',
        'medical_insurance_regions_data',
        'housing_fund_regions_data',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_projects')
                    ->withPivot(['start_date', 'end_date', 'status'])
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
        return HousingFund::whereIn('id', $this->housing_fund_regions)->get();
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

}
