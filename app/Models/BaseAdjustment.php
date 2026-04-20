<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseAdjustment extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '基数调差';
    
    protected $auditableFields = [
        'old_social_security_base' => '原社保基数',
        'old_medical_insurance_base' => '原医保基数',
        'old_housing_fund_base' => '原公积金基数',
        'old_large_medical_base' => '原大额医疗基数',
        'new_social_security_base' => '新社保基数',
        'new_medical_insurance_base' => '新医保基数',
        'new_housing_fund_base' => '新公积金基数',
        'new_large_medical_base' => '新大额医疗基数',
        'status' => '状态',
        'adjustment_reason' => '调整原因'
    ];

    protected $fillable = [
        'employee_id',
        'account_set_id',
        'old_social_security_base',
        'old_medical_insurance_base',
        'old_housing_fund_base',
        'old_large_medical_base',
        'old_large_medical_company_base',
        'new_social_security_base',
        'new_medical_insurance_base',
        'new_housing_fund_base',
        'new_large_medical_base',
        'new_large_medical_company_base',
        'social_security_effective_date',
        'medical_insurance_effective_date',
        'housing_fund_effective_date',
        'large_medical_effective_date',
        'status',
        'applied_at',
        'adjustment_reason',
        'created_by'
    ];

    protected $casts = [
        'old_social_security_base' => 'float',
        'old_medical_insurance_base' => 'float',
        'old_housing_fund_base' => 'float',
        'old_large_medical_base' => 'float',
        'old_large_medical_company_base' => 'float',
        'new_social_security_base' => 'float',
        'new_medical_insurance_base' => 'float',
        'new_housing_fund_base' => 'float',
        'new_large_medical_base' => 'float',
        'new_large_medical_company_base' => 'float',
        'social_security_effective_date' => 'date',
        'medical_insurance_effective_date' => 'date',
        'housing_fund_effective_date' => 'date',
        'large_medical_effective_date' => 'date',
        'applied_at' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->employee ? $this->employee->name : "ID:{$this->id}";
    }

    /**
     * 获取所属员工
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * 获取所属账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    /**
     * 获取创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 判断是否到达生效时间（任意一个基数的生效时间到期即可）
     */
    public function isEffective(): bool
    {
        $today = now()->startOfDay();
        
        return ($this->social_security_effective_date && \Carbon\Carbon::parse($this->social_security_effective_date)->startOfDay() <= $today)
            || ($this->medical_insurance_effective_date && \Carbon\Carbon::parse($this->medical_insurance_effective_date)->startOfDay() <= $today)
            || ($this->housing_fund_effective_date && \Carbon\Carbon::parse($this->housing_fund_effective_date)->startOfDay() <= $today)
            || ($this->large_medical_effective_date && \Carbon\Carbon::parse($this->large_medical_effective_date)->startOfDay() <= $today);
    }

    /**
     * 应用基数调整到员工档案
     */
    public function apply(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        if (!$this->isEffective()) {
            return false;
        }

        // 更新员工档案
        $employee = $this->employee;
        if (!$employee) {
            return false;
        }

        // 只更新设置了新基数且已到达生效时间的字段
        $employeeUpdateData = [];
        $personnelUpdateData = [];
        $today = now()->startOfDay();
        
        // 社保基数：有新值且生效时间已到
        if (!is_null($this->new_social_security_base) 
            && $this->social_security_effective_date 
            && \Carbon\Carbon::parse($this->social_security_effective_date)->startOfDay() <= $today) {
            $employeeUpdateData['social_security_base'] = $this->new_social_security_base;
            $personnelUpdateData['employee_social_security_base'] = $this->new_social_security_base;
        }
        
        // 医疗基数：有新值且生效时间已到
        if (!is_null($this->new_medical_insurance_base) 
            && $this->medical_insurance_effective_date 
            && \Carbon\Carbon::parse($this->medical_insurance_effective_date)->startOfDay() <= $today) {
            $employeeUpdateData['medical_insurance_base'] = $this->new_medical_insurance_base;
            $personnelUpdateData['employee_medical_insurance_base'] = $this->new_medical_insurance_base;
        }
        
        // 公积金基数：有新值且生效时间已到
        if (!is_null($this->new_housing_fund_base) 
            && $this->housing_fund_effective_date 
            && \Carbon\Carbon::parse($this->housing_fund_effective_date)->startOfDay() <= $today) {
            $employeeUpdateData['housing_fund_base'] = $this->new_housing_fund_base;
            $personnelUpdateData['employee_housing_fund_base'] = $this->new_housing_fund_base;
        }
        
        // 大额医疗基数（个人基数）：有新值且生效时间已到
        if (!is_null($this->new_large_medical_base) 
            && $this->large_medical_effective_date 
            && \Carbon\Carbon::parse($this->large_medical_effective_date)->startOfDay() <= $today) {
            $employeeUpdateData['large_medical_base'] = $this->new_large_medical_base;
            $personnelUpdateData['employee_large_medical_base'] = $this->new_large_medical_base;
        }
        
        // 大额医疗公司基数：有新值且生效时间已到（使用同一个生效日期）
        if (!is_null($this->new_large_medical_company_base) 
            && $this->large_medical_effective_date 
            && \Carbon\Carbon::parse($this->large_medical_effective_date)->startOfDay() <= $today) {
            $employeeUpdateData['large_medical_company_base'] = $this->new_large_medical_company_base;
            $personnelUpdateData['employee_large_medical_company_base'] = $this->new_large_medical_company_base;
        }
        
        // 更新员工档案
        if (!empty($employeeUpdateData)) {
            $employee->update($employeeUpdateData);
            
            \Log::info('员工档案基数已更新', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'updated_fields' => $employeeUpdateData
            ]);
        }
        
        // 同时更新参保人员表中该员工的所有记录
        if (!empty($personnelUpdateData)) {
            $updatedCount = \App\Models\InsurancePersonnel::where('employee_id', $employee->id)
                ->update($personnelUpdateData);
            
            \Log::info('参保人员表基数已更新', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'updated_records_count' => $updatedCount,
                'updated_fields' => $personnelUpdateData
            ]);
        }

        // 检查是否所有设置了的基数都已生效
        $allEffective = true;
        
        if (!is_null($this->new_social_security_base) && $this->social_security_effective_date) {
            if (\Carbon\Carbon::parse($this->social_security_effective_date)->startOfDay() > $today) {
                $allEffective = false;
            }
        }
        
        if (!is_null($this->new_medical_insurance_base) && $this->medical_insurance_effective_date) {
            if (\Carbon\Carbon::parse($this->medical_insurance_effective_date)->startOfDay() > $today) {
                $allEffective = false;
            }
        }
        
        if (!is_null($this->new_housing_fund_base) && $this->housing_fund_effective_date) {
            if (\Carbon\Carbon::parse($this->housing_fund_effective_date)->startOfDay() > $today) {
                $allEffective = false;
            }
        }
        
        if (!is_null($this->new_large_medical_base) && $this->large_medical_effective_date) {
            if (\Carbon\Carbon::parse($this->large_medical_effective_date)->startOfDay() > $today) {
                $allEffective = false;
            }
        }
        
        if (!is_null($this->new_large_medical_company_base) && $this->large_medical_effective_date) {
            if (\Carbon\Carbon::parse($this->large_medical_effective_date)->startOfDay() > $today) {
                $allEffective = false;
            }
        }
        
        // 只有所有设置了的基数都已生效，才标记为 applied
        if ($allEffective) {
            $this->update([
                'status' => 'applied',
                'applied_at' => now()
            ]);
        }

        return true;
    }

    /**
     * 作用域：仅查询待生效的记录
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * 作用域：仅查询已生效的记录
     */
    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    /**
     * 作用域：仅查询已到达生效时间的记录（任意一个基数的生效时间到期即可）
     */
    public function scopeEffective($query)
    {
        $today = now()->toDateString();
        
        return $query->where(function($q) use ($today) {
            $q->where('social_security_effective_date', '<=', $today)
              ->orWhere('medical_insurance_effective_date', '<=', $today)
              ->orWhere('housing_fund_effective_date', '<=', $today)
              ->orWhere('large_medical_effective_date', '<=', $today);
        });
    }

    /**
     * 作用域：按账套过滤
     */
    public function scopeOfAccountSet($query, $accountSetId)
    {
        return $query->where('account_set_id', $accountSetId);
    }
}

