<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceChangeDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_change_id',
        'employee_id',
        'project_id',
        'account_set_id',
        'insurance_type',
        'insurance_name',
        'region_name',
        'base_amount',
        'employee_ratio',
        'company_ratio',
        'employee_amount',
        'company_amount',
        'total_amount',
        'status',
        'effective_date',
        'expiry_date',
        'payment_cycle',  // 付款周期：month/year
        'payment_month',  // 付款月份（用于按年付款）
        'medical_base',   // 医疗基数
        'pension_base',   // 养老、失业、工伤基数
        'employee_medical_insurance_base',  // 员工医保基数
        'employee_social_security_base',    // 员工社保基数
        'employee_housing_fund_base',       // 员工公积金基数
        'dynamic_insurance_details', // 动态保险细分详情（JSON格式）
        'detail_type',    // 明细类型：summary=汇总明细，detail=细分明细
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'employee_ratio' => 'decimal:4',
        'company_ratio' => 'decimal:4',
        'employee_amount' => 'decimal:2',
        'company_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'medical_base' => 'decimal:2',
        'pension_base' => 'decimal:2',
        'employee_medical_insurance_base' => 'decimal:2',
        'employee_social_security_base' => 'decimal:2',
        'employee_housing_fund_base' => 'decimal:2',
        'dynamic_insurance_details' => 'array',
        'effective_date' => 'datetime',
        'expiry_date' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 参保增减关联
     */
    public function insuranceChange()
    {
        return $this->belongsTo(InsuranceChange::class);
    }

    /**
     * 员工关联
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 项目关联
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 账套关联
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 获取保险类型文本
     */
    public function getInsuranceTypeTextAttribute()
    {
        $typeMap = [
            'social_security' => '社保',
            'medical_insurance' => '医保',
            'housing_fund' => '公积金',
            'other_insurance' => '其他保险'
        ];

        return $typeMap[$this->insurance_type] ?? '未知';
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        return $this->status === 'active' ? '生效' : '失效';
    }

    /**
     * 计算缴纳金额
     */
    public function calculateAmounts()
    {
        $this->employee_amount = $this->base_amount * $this->employee_ratio;
        $this->company_amount = $this->base_amount * $this->company_ratio;
        $this->total_amount = $this->employee_amount + $this->company_amount;
        
        return $this;
    }
}
