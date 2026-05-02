<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LargeMedicalInsuranceConfig extends Model
{
    protected $table = 'large_medical_insurance_configs';

    protected $fillable = [
        'region_name',
        'account_set_id',
        'calculation_type',
        'base_source', // employee=使用员工基数(普通地区), config=使用统一基数(特殊地区)
        'base_amount',
        'employee_base_amount',
        'company_ratio',
        'employee_ratio',
        'company_amount',
        'employee_amount',
        'payment_cycle',
        'status',
        'effective_date',
        'pending_changes',
        'remarks',
        'created_by'
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'employee_base_amount' => 'decimal:2',
        'company_ratio' => 'decimal:2',
        'employee_ratio' => 'decimal:2',
        'company_amount' => 'decimal:2',
        'employee_amount' => 'decimal:2',
        'status' => 'boolean',
        'effective_date' => 'date',
        'pending_changes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 所属账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    /**
     * 创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 关联的项目
     */
    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            'project_large_medical_insurance',
            'config_id',
            'project_id'
        )->withTimestamps();
    }

    /**
     * 关联的员工
     */
    public function employees()
    {
        return $this->hasMany(EmployeeLargeMedicalInsurance::class, 'config_id');
    }

    /**
     * 按账套筛选
     */
    public function scopeByAccountSet($query, $accountSetId)
    {
        return $query->where('account_set_id', $accountSetId);
    }

    /**
     * 按地区筛选
     */
    public function scopeByRegion($query, $regionName)
    {
        return $query->where('region_name', $regionName);
    }

    /**
     * 只查询启用的配置
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 计算公司费用
     *
     * @return float
     */
    public function calculateCompanyCost()
    {
        if ($this->calculation_type === 'base') {
            // 按基数计算：公司基数 * 公司比例（比例已经是小数形式，如0.1代表10%）
            return round(($this->base_amount ?? 0) * ($this->company_ratio ?? 0), 2);
        } else {
            // 按固定金额
            return $this->company_amount ?? 0;
        }
    }

    /**
     * 计算员工费用
     *
     * @return float
     */
    public function calculateEmployeeCost()
    {
        if ($this->calculation_type === 'base') {
            // 按基数计算：个人基数 * 员工比例（比例已经是小数形式，如0.1代表10%）
            return round(($this->employee_base_amount ?? $this->base_amount ?? 0) * ($this->employee_ratio ?? 0), 2);
        } else {
            // 按固定金额
            return $this->employee_amount ?? 0;
        }
    }

    /**
     * 计算总费用
     *
     * @return float
     */
    public function calculateTotalCost()
    {
        return $this->calculateCompanyCost() + $this->calculateEmployeeCost();
    }

    /**
     * 获取计算方式文本
     */
    public function getCalculationTypeTextAttribute()
    {
        return $this->calculation_type === 'base' ? '按基数' : '按固定金额';
    }

    /**
     * 获取付款周期文本
     */
    public function getPaymentCycleTextAttribute()
    {
        return $this->payment_cycle === 'month' ? '按月' : '按年';
    }

    /**
     * 历史记录
     */
    public function histories()
    {
        return $this->hasMany(LargeMedicalInsuranceConfigHistory::class, 'config_id')->orderBy('created_at', 'desc');
    }

    /**
     * 判断是否有待生效的变更
     */
    public function hasPendingChanges(): bool
    {
        return !empty($this->pending_changes) && $this->effective_date;
    }

    /**
     * 判断是否到达生效时间
     */
    public function isEffective(?\Carbon\Carbon $referenceDate = null): bool
    {
        if (!$this->effective_date) {
            return false;
        }
        $compareDate = ($referenceDate ? $referenceDate->copy() : now())->startOfDay();
        return \Carbon\Carbon::parse($this->effective_date)->startOfDay() <= $compareDate;
    }

    /**
     * 应用待生效的变更
     */
    public function applyPendingChanges(?\Carbon\Carbon $referenceDate = null): bool
    {
        if (!$this->hasPendingChanges() || !$this->isEffective($referenceDate)) {
            return false;
        }

        $pendingChanges = $this->pending_changes;
        $oldValues = $this->getOriginalValues();

        // 应用变更
        $updateData = [];
        foreach ($pendingChanges as $field => $value) {
            if (in_array($field, $this->fillable) && $field !== 'pending_changes' && $field !== 'effective_date') {
                $updateData[$field] = $value;
            }
        }

        if (!empty($updateData)) {
            $this->update($updateData);
        }

        // 清除待生效配置
        $this->update([
            'pending_changes' => null,
            'effective_date' => null
        ]);

        // 创建历史记录
        LargeMedicalInsuranceConfigHistory::create([
            'config_id' => $this->id,
            'account_set_id' => $this->account_set_id,
            'region_name' => $this->region_name,
            'change_type' => 'effective',
            'old_calculation_type' => $oldValues['calculation_type'],
            'old_base_source' => $oldValues['base_source'],
            'old_base_amount' => $oldValues['base_amount'],
            'old_employee_base_amount' => $oldValues['employee_base_amount'],
            'old_company_ratio' => $oldValues['company_ratio'],
            'old_employee_ratio' => $oldValues['employee_ratio'],
            'old_company_amount' => $oldValues['company_amount'],
            'old_employee_amount' => $oldValues['employee_amount'],
            'new_calculation_type' => $pendingChanges['calculation_type'] ?? $this->calculation_type,
            'new_base_source' => $pendingChanges['base_source'] ?? $this->base_source,
            'new_base_amount' => $pendingChanges['base_amount'] ?? $this->base_amount,
            'new_employee_base_amount' => $pendingChanges['employee_base_amount'] ?? $this->employee_base_amount,
            'new_company_ratio' => $pendingChanges['company_ratio'] ?? $this->company_ratio,
            'new_employee_ratio' => $pendingChanges['employee_ratio'] ?? $this->employee_ratio,
            'new_company_amount' => $pendingChanges['company_amount'] ?? $this->company_amount,
            'new_employee_amount' => $pendingChanges['employee_amount'] ?? $this->employee_amount,
            'effective_date' => now(),
            'operated_by' => null,
            'operated_by_name' => '系统自动',
            'remark' => '定时任务自动生效'
        ]);

        \Log::info('大额医疗保险配置已生效', [
            'config_id' => $this->id,
            'region_name' => $this->region_name,
            'changes' => $pendingChanges
        ]);

        return true;
    }

    /**
     * 获取原始值（用于历史记录）
     */
    private function getOriginalValues(): array
    {
        return [
            'calculation_type' => $this->calculation_type,
            'base_source' => $this->base_source,
            'base_amount' => $this->base_amount,
            'employee_base_amount' => $this->employee_base_amount,
            'company_ratio' => $this->company_ratio,
            'employee_ratio' => $this->employee_ratio,
            'company_amount' => $this->company_amount,
            'employee_amount' => $this->employee_amount,
        ];
    }

    /**
     * 设置待生效的变更
     */
    public function setPendingChanges(array $changes, $effectiveDate, $operatorId = null, $operatorName = null): void
    {
        $oldValues = $this->getOriginalValues();

        $this->update([
            'pending_changes' => $changes,
            'effective_date' => $effectiveDate
        ]);

        // 创建历史记录（待生效）
        LargeMedicalInsuranceConfigHistory::create([
            'config_id' => $this->id,
            'account_set_id' => $this->account_set_id,
            'region_name' => $this->region_name,
            'change_type' => 'pending',
            'old_calculation_type' => $oldValues['calculation_type'],
            'old_base_source' => $oldValues['base_source'],
            'old_base_amount' => $oldValues['base_amount'],
            'old_employee_base_amount' => $oldValues['employee_base_amount'],
            'old_company_ratio' => $oldValues['company_ratio'],
            'old_employee_ratio' => $oldValues['employee_ratio'],
            'old_company_amount' => $oldValues['company_amount'],
            'old_employee_amount' => $oldValues['employee_amount'],
            'new_calculation_type' => $changes['calculation_type'] ?? $this->calculation_type,
            'new_base_source' => $changes['base_source'] ?? $this->base_source,
            'new_base_amount' => $changes['base_amount'] ?? $this->base_amount,
            'new_employee_base_amount' => $changes['employee_base_amount'] ?? $this->employee_base_amount,
            'new_company_ratio' => $changes['company_ratio'] ?? $this->company_ratio,
            'new_employee_ratio' => $changes['employee_ratio'] ?? $this->employee_ratio,
            'new_company_amount' => $changes['company_amount'] ?? $this->company_amount,
            'new_employee_amount' => $changes['employee_amount'] ?? $this->employee_amount,
            'effective_date' => $effectiveDate,
            'operated_by' => $operatorId,
            'operated_by_name' => $operatorName,
            'remark' => '设置待生效变更，预计生效日期：' . $effectiveDate
        ]);
    }

    /**
     * 作用域：查询有待生效变更的配置
     */
    public function scopeHasPending($query)
    {
        return $query->whereNotNull('pending_changes')
            ->whereNotNull('effective_date');
    }

    /**
     * 作用域：查询已到达生效时间的配置
     */
    public function scopeEffectiveNow($query, $date = null)
    {
        $effectiveDate = $date ?: now()->toDateString();
        return $query->hasPending()
            ->where('effective_date', '<=', $effectiveDate);
    }
}

