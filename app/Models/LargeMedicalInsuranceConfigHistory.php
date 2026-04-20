<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LargeMedicalInsuranceConfigHistory extends Model
{
    protected $table = 'large_medical_insurance_config_histories';

    protected $fillable = [
        'config_id',
        'account_set_id',
        'region_name',
        'change_type',
        'old_calculation_type',
        'old_base_source',
        'old_base_amount',
        'old_employee_base_amount',
        'old_company_ratio',
        'old_employee_ratio',
        'old_company_amount',
        'old_employee_amount',
        'new_calculation_type',
        'new_base_source',
        'new_base_amount',
        'new_employee_base_amount',
        'new_company_ratio',
        'new_employee_ratio',
        'new_company_amount',
        'new_employee_amount',
        'effective_date',
        'operated_by',
        'operated_by_name',
        'remark'
    ];

    protected $casts = [
        'old_base_amount' => 'decimal:2',
        'old_employee_base_amount' => 'decimal:2',
        'old_company_ratio' => 'decimal:4',
        'old_employee_ratio' => 'decimal:4',
        'old_company_amount' => 'decimal:2',
        'old_employee_amount' => 'decimal:2',
        'new_base_amount' => 'decimal:2',
        'new_employee_base_amount' => 'decimal:2',
        'new_company_ratio' => 'decimal:4',
        'new_employee_ratio' => 'decimal:4',
        'new_company_amount' => 'decimal:2',
        'new_employee_amount' => 'decimal:2',
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 关联的配置
     */
    public function config()
    {
        return $this->belongsTo(LargeMedicalInsuranceConfig::class, 'config_id');
    }

    /**
     * 所属账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    /**
     * 操作人
     */
    public function operator()
    {
        return $this->belongsTo(User::class, 'operated_by');
    }

    /**
     * 获取变更类型文本
     */
    public function getChangeTypeTextAttribute()
    {
        $types = [
            'create' => '新建配置',
            'update' => '修改配置',
            'effective' => '配置生效',
            'pending' => '待生效变更'
        ];
        return $types[$this->change_type] ?? $this->change_type;
    }

    /**
     * 获取计算方式文本
     */
    public function getCalculationTypeText($type)
    {
        return $type === 'base' ? '按基数' : '按固定金额';
    }

    /**
     * 获取基数来源文本
     */
    public function getBaseSourceText($source)
    {
        return $source === 'employee' ? '使用员工基数' : '使用统一基数';
    }
}
