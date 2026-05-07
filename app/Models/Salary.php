<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Salary extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '工资';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'employee_name' => '员工姓名',
        'id_card' => '身份证号',
        'month' => '月份',
        'period_start' => '周期开始',
        'period_end' => '周期结束',
        'department' => '部门',
        'position' => '岗位',
        'work_days' => '应出勤天数',
        'actual_work_days' => '实际出勤天数',
        'absent_days' => '缺勤天数',
        'absent_deduction' => '缺勤扣款',
        'basic_salary' => '基本工资',
        'gross_salary' => '应发工资',
        'social_security' => '社保',
        'housing_fund' => '公积金',
        'deductions' => '扣款',
        'net_salary' => '实发工资',
        'paid_salary' => '已发工资',
        'status' => '状态',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->employee_name} {$this->month}";
    }

    protected $fillable = [
        'seq_number',        // 草稿工资表批次号
        'employee_id',
        'id_card',           // 身份证号
        'employee_name',     // 员工姓名
        'project_id',
        'month',
        'period_start',  // 工资周期开始日期
        'period_end',    // 工资周期结束日期
        'department',  // 部门（项目名称）
        'position',    // 岗位
        'insurance_import_setting',  // 保险导入设置（生成时的设置）
        'salary_approval_id',         // 工资表审批ID
        'work_days',   // 应出勤天数
        'actual_work_days',  // 实际出勤天数
        'absent_days',       // 缺勤天数
        'absent_deduction',  // 缺勤扣款
        'basic_salary',
        'gross_salary',
        'cumulative_income',                      // 累计收入
        'cumulative_basic_deduction',             // 累计减除费用
        'cumulative_special_deduction_insurance', // 累计专项扣除（社保公积金）
        'tax_rate',                               // 税率
        'quick_deduction',                        // 速算扣除数
        'cumulative_tax_payable',                 // 累计应扣缴税额
        'tax_already_withheld',                   // 已扣缴税额
        'social_security',
        'housing_fund',
        'company_insurance_total',  // 单位保险合计
        'personal_insurance_total', // 个人保险合计
        'special_deduction_monthly',  // 当月专项附加扣除（6项扣除）
        'special_deduction',          // 累计专项附加扣除（1月到当前月）
        'taxable_income',
        'cumulative_other_taxable',   // 累计其他应纳税项（合并扣税）
        'tax_payable_or_refundable',  // 应补（退）税额
        'employee_signature',         // 本人签字
        'import_extra_columns',       // 导入工资模板的动态列（TEXT保存JSON字符串）
        'deductions',
        'net_salary',
        'paid_salary',
        'status',
        'submitted_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'paid_at',
        'rejection_reason',
        'notes',
        'account_set_id',  // 【账套关联】
    ];

    protected $casts = [
        'seq_number' => 'integer',
        'work_days' => 'integer',
        'actual_work_days' => 'decimal:1',
        'absent_days' => 'decimal:1',
        'absent_deduction' => 'decimal:2',
        'basic_salary' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'cumulative_income' => 'decimal:2',
        'cumulative_basic_deduction' => 'decimal:2',
        'cumulative_special_deduction_insurance' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'quick_deduction' => 'decimal:2',
        'cumulative_tax_payable' => 'decimal:2',
        'tax_already_withheld' => 'decimal:2',
        'social_security' => 'decimal:2',
        'housing_fund' => 'decimal:2',
        'company_insurance_total' => 'decimal:2',
        'personal_insurance_total' => 'decimal:2',
        'special_deduction_monthly' => 'decimal:2',
        'special_deduction' => 'decimal:2',
        'taxable_income' => 'decimal:2',
        'cumulative_other_taxable' => 'decimal:2',
        'tax_payable_or_refundable' => 'decimal:2',
        'import_extra_columns' => 'array',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'paid_salary' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function salaryApproval()
    {
        return $this->belongsTo(SalaryApproval::class, 'salary_approval_id');
    }

    // 数据库中不存在相关字段，已注释
    // public function submittedBy()
    // {
    //     return $this->belongsTo(User::class, 'submitted_by');
    // }

    // public function approvedBy()
    // {
    //     return $this->belongsTo(User::class, 'approved_by');
    // }

    // 计算实发工资
    public function calculateNetSalary()
    {
        $this->net_salary = $this->basic_salary + $this->overtime_pay + $this->bonus - $this->deductions;
        $this->save();
        return $this->net_salary;
    }

    // 以下方法因数据库字段不存在已注释
    // public function calculateGrossSalary()
    // {
    //     $this->gross_salary = $this->basic_salary + $this->allowance + $this->overtime_pay + $this->bonus;
    //     return $this->gross_salary;
    // }

    // public function calculateTaxableIncome()
    // {
    //     $this->taxable_income = $this->gross_salary - $this->social_security - $this->housing_fund - $this->special_deduction;
    //     return $this->taxable_income;
    // }

    // public function calculatePersonalTax()
    // {
    //     // 简化的个税计算，实际应用中需要根据最新的个税政策
    //     $taxableIncome = $this->taxable_income;
    //     
    //     if ($taxableIncome <= 0) {
    //         $this->personal_tax = 0;
    //     } elseif ($taxableIncome <= 3000) {
    //         $this->personal_tax = $taxableIncome * 0.03;
    //     } elseif ($taxableIncome <= 12000) {
    //         $this->personal_tax = $taxableIncome * 0.1 - 210;
    //     } elseif ($taxableIncome <= 25000) {
    //         $this->personal_tax = $taxableIncome * 0.2 - 1410;
    //     } elseif ($taxableIncome <= 35000) {
    //         $this->personal_tax = $taxableIncome * 0.25 - 2660;
    //     } elseif ($taxableIncome <= 55000) {
    //         $this->personal_tax = $taxableIncome * 0.3 - 4410;
    //     } elseif ($taxableIncome <= 80000) {
    //         $this->personal_tax = $taxableIncome * 0.35 - 7160;
    //     } else {
    //         $this->personal_tax = $taxableIncome * 0.45 - 15160;
    //     }
    //     
    //     return $this->personal_tax;
    // }

    // public function calculateAll()
    // {
    //     $this->calculateGrossSalary();
    //     $this->calculateTaxableIncome();
    //     $this->calculatePersonalTax();
    //     $this->calculateNetSalary();
    //     $this->save();
    // }

    public function canBeApproved()
    {
        return $this->status === 'submitted';
    }

    public function canBePaid()
    {
        return $this->status === 'approved';
    }
}
