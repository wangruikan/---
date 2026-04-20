<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLargeMedicalInsurance extends Model
{
    protected $table = 'employee_large_medical_insurance';

    protected $fillable = [
        'employee_id',
        'config_id',
        'account_set_id',
        'is_enabled'
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 所属员工
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * 大额医疗保险配置
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
     * 按账套筛选
     */
    public function scopeByAccountSet($query, $accountSetId)
    {
        return $query->where('account_set_id', $accountSetId);
    }

    /**
     * 只查询启用的
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', 1);
    }
}

