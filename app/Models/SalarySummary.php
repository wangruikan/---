<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalarySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'project_id',
        'project_name',
        'month',
        'period_start',
        'period_end',
        'employee_count',
        // 项目配置信息
        'insurance_import_setting',
        'social_security_location',
        'salary_payment_day',
        'requires_salary_basis',
        'salary_basis_uploaded',
        // 考勤
        'total_work_days',
        'total_actual_work_days',
        'total_absent_days',
        'total_absent_deduction',
        // 工资
        'total_basic_salary',
        'total_gross_salary',
        // 累计
        'total_cumulative_income',
        'total_cumulative_basic_deduction',
        'total_cumulative_special_deduction_insurance',
        // 税率
        'avg_tax_rate',
        'avg_quick_deduction',
        'total_cumulative_tax_payable',
        'total_tax_already_withheld',
        // 社保单位
        'total_pension_company',
        'total_medical_company',
        'total_unemployment_company',
        'total_work_injury_company',
        'total_maternity_company',
        // 社保个人
        'total_pension_personal',
        'total_medical_personal',
        'total_unemployment_personal',
        // 公积金
        'total_housing_fund_company',
        'total_housing_fund_personal',
        // 大额医疗
        'total_large_medical_company',
        'total_large_medical_personal',
        // 补差
        'total_social_security_compensation',
        'total_housing_fund_compensation',
        // 保险合计
        'total_company_insurance_total',
        'total_personal_insurance_total',
        // 专项扣除
        'total_special_deduction_monthly',
        'total_special_deduction',
        // 税务
        'total_taxable_income',
        'total_cumulative_other_taxable',
        'total_tax_payable_or_refundable',
        // 其他扣款
        'total_deductions',
        // 实发
        'total_net_salary',
        'total_paid_salary',
        // 状态
        'status',
        'salary_approval_id',
        'approved_at',
    ];

    protected $casts = [
        'employee_count' => 'integer',
        'requires_salary_basis' => 'boolean',
        'salary_basis_uploaded' => 'boolean',
        'salary_payment_day' => 'integer',
        'total_work_days' => 'float',
        'total_actual_work_days' => 'float',
        'total_absent_days' => 'float',
        'total_absent_deduction' => 'float',
        'total_basic_salary' => 'float',
        'total_gross_salary' => 'float',
        'total_cumulative_income' => 'float',
        'total_cumulative_basic_deduction' => 'float',
        'total_cumulative_special_deduction_insurance' => 'float',
        'avg_tax_rate' => 'float',
        'avg_quick_deduction' => 'float',
        'total_cumulative_tax_payable' => 'float',
        'total_tax_already_withheld' => 'float',
        'total_pension_company' => 'float',
        'total_medical_company' => 'float',
        'total_unemployment_company' => 'float',
        'total_work_injury_company' => 'float',
        'total_maternity_company' => 'float',
        'total_pension_personal' => 'float',
        'total_medical_personal' => 'float',
        'total_unemployment_personal' => 'float',
        'total_housing_fund_company' => 'float',
        'total_housing_fund_personal' => 'float',
        'total_large_medical_company' => 'float',
        'total_large_medical_personal' => 'float',
        'total_social_security_compensation' => 'float',
        'total_housing_fund_compensation' => 'float',
        'total_company_insurance_total' => 'float',
        'total_personal_insurance_total' => 'float',
        'total_special_deduction_monthly' => 'float',
        'total_special_deduction' => 'float',
        'total_taxable_income' => 'float',
        'total_cumulative_other_taxable' => 'float',
        'total_tax_payable_or_refundable' => 'float',
        'total_deductions' => 'float',
        'total_net_salary' => 'float',
        'total_paid_salary' => 'float',
        'approved_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联项目
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 关联工资表审批
     */
    public function salaryApproval()
    {
        return $this->belongsTo(SalaryApproval::class, 'salary_approval_id');
    }
}

