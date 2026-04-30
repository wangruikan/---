<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsurancePersonnel extends Model
{
    use HasFactory;

    protected $table = 'insurance_personnel';

    protected $fillable = [
        'employee_id',
        'employee_name',
        'employee_id_number',
        'employee_gender',
        'employee_birth_date',
        'employee_phone',
        'employee_status',
        'project_id',
        'account_set_id',
        'social_security_region_id',
        'social_security_code',
        'medical_insurance_region_id',
        'medical_insurance_code',
        'housing_fund_region_id',
        'housing_fund_account_number',
        'housing_fund_config_id',
        'large_medical_insurance_config_id',
        'large_medical_insurance_enabled',
        'employee_social_security_base',
        'employee_medical_insurance_base',
        'employee_housing_fund_base',
        'employee_large_medical_base',
        'employee_large_medical_company_base',
        'social_security_types',
        'medical_insurance_types',
        'housing_fund_params',
        'other_insurance_policies',
        'large_medical_insurance_config',
        'large_medical_payment_start_month',
        'large_medical_payment_start_year',
        'large_medical_last_payment_month',
        'large_medical_last_payment_year',
        'used_quotas',
        'status',
        'first_confirmation_date',
        'last_updated_at',
        'is_compensation',
        'compensation_months',
        'compensation_start_month',
        'compensation_end_month',
        'old_base',
        'new_base',
        'enrollment_status',
        'social_security_base',
        'medical_insurance_base',
    ];

    protected $casts = [
        'employee_social_security_base' => 'decimal:2',
        'employee_medical_insurance_base' => 'decimal:2',
        'employee_housing_fund_base' => 'decimal:2',
        'employee_large_medical_base' => 'decimal:2',
        'employee_large_medical_company_base' => 'decimal:2',
        'large_medical_insurance_enabled' => 'boolean',
        'used_quotas' => 'array',
        'last_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
     * 社保地区关联
     */
    public function socialSecurityRegion()
    {
        return $this->belongsTo(SocialSecurityRegion::class, 'social_security_region_id');
    }

    /**
     * 医保地区关联
     */
    public function medicalInsuranceRegion()
    {
        return $this->belongsTo(MedicalInsuranceRegion::class, 'medical_insurance_region_id');
    }

    /**
     * 公积金地区关联
     */
    public function housingFundRegion()
    {
        return $this->belongsTo(HousingFundRegion::class, 'housing_fund_region_id');
    }

    /**
     * 公积金配置关联
     */
    public function housingFundConfig()
    {
        return $this->belongsTo(HousingFundConfig::class, 'housing_fund_config_id');
    }

    /**
     * 大额医疗保险配置关联
     */
    public function largeMedicalInsuranceConfig()
    {
        return $this->belongsTo(LargeMedicalInsuranceConfig::class, 'large_medical_insurance_config_id');
    }

    /**
     * 从保险变更记录创建或更新参保人员信息
     */
    public static function getOrCreateFromInsuranceChange($change)
    {
        $matchedRecords = self::buildCurrentPersonnelQueryFromChange($change)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        // decrease: remove all matching current records
        if ($change->change_type === 'decrease') {
            foreach ($matchedRecords as $record) {
                \Log::info('删除参保人员记录', [
                    'personnel_id' => $record->id,
                    'employee_id' => $change->employee_id,
                    'employee_name' => $change->employee_name,
                    'employee_status' => $change->employee_status
                ]);
                $record->delete();
            }

            return null;
        }

        $personnel = $matchedRecords->first();
        $duplicateIds = $matchedRecords->skip(1)->pluck('id')->values()->all();

        if (!empty($duplicateIds)) {
            \Log::warning('参保人员记录存在重复，已清理', [
                'employee_id' => $change->employee_id,
                'project_id' => $change->project_id,
                'account_set_id' => $change->account_set_id,
                'kept_id' => $personnel ? $personnel->id : null,
                'deleted_ids' => $duplicateIds
            ]);

            self::whereIn('id', $duplicateIds)->delete();
        }

        // 获取员工信息（带地区关联）
        $employee = $change->employee()->with([
            'socialSecurityRegion',
            'medicalInsuranceRegion',
            'housingFundRegion'
        ])->first();

        // 从员工的地区信息中获取编号和账号
        $socialSecurityCode = $employee && $employee->socialSecurityRegion
            ? $employee->socialSecurityRegion->code
            : null;
        $medicalInsuranceCode = $employee && $employee->medicalInsuranceRegion
            ? $employee->medicalInsuranceRegion->code
            : null;
        $housingFundAccountNumber = $employee && $employee->housingFundRegion
            ? $employee->housingFundRegion->account_number
            : null;

        // 应用上下限约束到基数
        $constrainedBases = self::applyBaseConstraintsFromChange($change);

        // 确保地区ID正确（优先从变更记录，回退到员工档案）
        $socialSecurityRegionId = $change->social_security_region_id ?: ($employee ? $employee->social_security_region_id : null);
        $medicalInsuranceRegionId = $change->medical_insurance_region_id ?: ($employee ? $employee->medical_insurance_region_id : null);
        $housingFundRegionId = $change->housing_fund_region_id ?: ($employee ? $employee->housing_fund_region_id : null);

        if ($personnel) {
            // 更新现有记录
            // 注意：first_confirmation_date 只在首次创建时设置，更新时不修改，避免影响补交逻辑
            $personnel->update([
                'employee_name' => $change->employee_name,
                'employee_id_number' => $change->employee_id_number,
                'employee_gender' => $change->employee_gender,
                'employee_birth_date' => $change->employee_birth_date,
                'employee_phone' => $change->employee_phone,
                'employee_status' => $change->employee_status,
                'social_security_region_id' => $socialSecurityRegionId,
                'social_security_code' => $socialSecurityCode,
                'medical_insurance_region_id' => $medicalInsuranceRegionId,
                'medical_insurance_code' => $medicalInsuranceCode,
                'housing_fund_region_id' => $housingFundRegionId,
                'housing_fund_account_number' => $housingFundAccountNumber,
                'housing_fund_config_id' => $change->housing_fund_config_id,
                'large_medical_insurance_config_id' => $change->large_medical_insurance_config_id,
                'large_medical_insurance_enabled' => $change->large_medical_insurance_enabled,
                'employee_social_security_base' => $constrainedBases['social_security'],
                'employee_medical_insurance_base' => $constrainedBases['medical_insurance'],
                'employee_housing_fund_base' => $constrainedBases['housing_fund'],
                'employee_large_medical_base' => $change->employee_large_medical_base,
                'employee_large_medical_company_base' => $change->employee_large_medical_company_base,
                'social_security_types' => $change->social_security_types,
                'medical_insurance_types' => $change->medical_insurance_types,
                'housing_fund_params' => $change->housing_fund_params,
                'other_insurance_policies' => $change->other_insurance_policies,
                'large_medical_insurance_config' => $change->large_medical_insurance_config,
                'used_quotas' => $change->used_quotas,
                'status' => 'active',
                'is_compensation' => 0,
                // first_confirmation_date 不更新，保持首次确认日期不变
                'last_updated_at' => now(),
            ]);

            // 如果启用了大额医疗保险且之前没有设置支付起始月份，设置支付起始月份
            if ($change->large_medical_insurance_enabled && $change->large_medical_insurance_config_id) {
                if (!$personnel->large_medical_payment_start_month || !$personnel->large_medical_payment_start_year) {
                    self::setLargeMedicalPaymentStartTime($personnel, $change);
                }
            }
        } else {
            // 创建新记录
            $personnel = self::create([
                'employee_id' => $change->employee_id,
                'employee_name' => $change->employee_name,
                'employee_id_number' => $change->employee_id_number,
                'employee_gender' => $change->employee_gender,
                'employee_birth_date' => $change->employee_birth_date,
                'employee_phone' => $change->employee_phone,
                'employee_status' => $change->employee_status,
                'project_id' => $change->project_id,
                'account_set_id' => $change->account_set_id,
                'social_security_region_id' => $socialSecurityRegionId,
                'social_security_code' => $socialSecurityCode,
                'medical_insurance_region_id' => $medicalInsuranceRegionId,
                'medical_insurance_code' => $medicalInsuranceCode,
                'housing_fund_region_id' => $housingFundRegionId,
                'housing_fund_account_number' => $housingFundAccountNumber,
                'housing_fund_config_id' => $change->housing_fund_config_id,
                'large_medical_insurance_config_id' => $change->large_medical_insurance_config_id,
                'large_medical_insurance_enabled' => $change->large_medical_insurance_enabled,
                'employee_social_security_base' => $constrainedBases['social_security'],
                'employee_medical_insurance_base' => $constrainedBases['medical_insurance'],
                'employee_housing_fund_base' => $constrainedBases['housing_fund'],
                'employee_large_medical_base' => $change->employee_large_medical_base,
                'employee_large_medical_company_base' => $change->employee_large_medical_company_base,
                'social_security_types' => $change->social_security_types,
                'medical_insurance_types' => $change->medical_insurance_types,
                'housing_fund_params' => $change->housing_fund_params,
                'other_insurance_policies' => $change->other_insurance_policies,
                'large_medical_insurance_config' => $change->large_medical_insurance_config,
                'used_quotas' => $change->used_quotas,
                'status' => 'active',
                'is_compensation' => 0,
                'first_confirmation_date' => $change->first_confirmation_date ?? now()->toDateString(),
                'last_updated_at' => now(),
            ]);

            // 如果启用了大额医疗保险，设置支付起始月份
            if ($change->large_medical_insurance_enabled && $change->large_medical_insurance_config_id) {
                self::setLargeMedicalPaymentStartTime($personnel, $change);
            }
        }

        return $personnel;
    }

    /**
     * Build current personnel query by employee + project + account set.
     */
    private static function buildCurrentPersonnelQueryFromChange($change)
    {
        return self::where('employee_id', $change->employee_id)
            ->where('project_id', $change->project_id)
            ->where('account_set_id', $change->account_set_id)
            ->where(function ($query) {
                $query->whereNull('is_compensation')->orWhere('is_compensation', 0);
            });
    }

    /**
     * Set large medical payment start month for yearly cycle.
     *
     * @param InsurancePersonnel $personnel
     * @param \App\Models\InsuranceChange $change
     */
    private static function setLargeMedicalPaymentStartTime($personnel, $change)
    {
        // 获取大额医疗保险配置
        $config = json_decode($change->large_medical_insurance_config, true);
        $paymentCycle = $config['payment_cycle'] ?? 'month';
        
        // 只有按年付款模式才需要设置支付起始月份
        if ($paymentCycle === 'year' || $paymentCycle === 'yearly') {
            // 使用当前月份作为支付起始月份
            $currentYear = (int) date('Y');
            $currentMonth = (int) date('n');
            
            $personnel->update([
                'large_medical_payment_start_month' => $currentMonth,
                'large_medical_payment_start_year' => $currentYear,
                'large_medical_last_payment_month' => $currentMonth,
                'large_medical_last_payment_year' => $currentYear,
            ]);
            
            \Log::info('设置大额医疗保险支付起始月份', [
                'employee_id' => $personnel->employee_id,
                'employee_name' => $personnel->employee_name,
                'payment_cycle' => $paymentCycle,
                'start_month' => $currentMonth,
                'start_year' => $currentYear,
            ]);
        }
    }

    /**
     * 从insurance_change中应用上下限约束到基数
     * 
     * @param \App\Models\InsuranceChange $change
     * @return array ['social_security' => float, 'medical_insurance' => float, 'housing_fund' => float]
     */
    private static function applyBaseConstraintsFromChange($change)
    {
        // 获取原始基数
        $socialSecurityBase = $change->employee_social_security_base ?? 0;
        $medicalInsuranceBase = $change->employee_medical_insurance_base ?? 0;
        $housingFundBase = $change->employee_housing_fund_base ?? 0;

        // 调试：输出到文件
        file_put_contents(storage_path('logs/base_constraint_debug.log'), 
            date('Y-m-d H:i:s') . " - 开始约束基数\n" .
            "员工: {$change->employee_name}\n" .
            "原始社保基数: {$socialSecurityBase}\n" .
            "原始医保基数: {$medicalInsuranceBase}\n" .
            "原始公积金基数: {$housingFundBase}\n\n",
            FILE_APPEND
        );

        // 解析保险配置获取上下限
        $socialSecurityTypes = json_decode($change->social_security_types, true) ?: [];
        $medicalInsuranceTypes = json_decode($change->medical_insurance_types, true) ?: [];
        $housingFundParams = json_decode($change->housing_fund_params, true) ?: [];

        // 应用社保上下限约束（从地区获取上下限）
        // 优先从变更记录读取，如果没有则从员工档案读取
        $socialRegionId = $change->social_security_region_id;
        if (!$socialRegionId && $change->employee) {
            $socialRegionId = $change->employee->social_security_region_id;
        }
        
        if ($socialRegionId) {
            $socialRegion = \App\Models\SocialSecurityRegion::find($socialRegionId);
            if ($socialRegion) {
                $minBase = $socialRegion->min_base_amount;
                $maxBase = $socialRegion->max_base_amount;
                $socialSecurityBase = self::applyConstraint($socialSecurityBase, $minBase, $maxBase);
                
                \Log::info('社保基数约束', [
                    'employee_name' => $change->employee_name,
                    'original' => $change->employee_social_security_base,
                    'region_id' => $socialRegionId,
                    'min' => $minBase,
                    'max' => $maxBase,
                    'constrained' => $socialSecurityBase
                ]);
            }
        }

        // 应用医保上下限约束（从地区获取上下限）
        $medicalRegionId = $change->medical_insurance_region_id;
        if (!$medicalRegionId && $change->employee) {
            $medicalRegionId = $change->employee->medical_insurance_region_id;
        }
        
        if ($medicalRegionId) {
            $medicalRegion = \App\Models\MedicalInsuranceRegion::find($medicalRegionId);
            if ($medicalRegion) {
                $minBase = $medicalRegion->min_base_amount;
                $maxBase = $medicalRegion->max_base_amount;
                $medicalInsuranceBase = self::applyConstraint($medicalInsuranceBase, $minBase, $maxBase);
                
                \Log::info('医保基数约束', [
                    'employee_name' => $change->employee_name,
                    'original' => $change->employee_medical_insurance_base,
                    'region_id' => $medicalRegionId,
                    'min' => $minBase,
                    'max' => $maxBase,
                    'constrained' => $medicalInsuranceBase
                ]);
            }
        }

        // 应用公积金上下限约束（从公积金配置表读取）
        $housingFundConfigId = $change->housing_fund_config_id;
        if (!$housingFundConfigId && $change->employee) {
            $housingFundConfigId = $change->employee->housing_fund_config_id;
        }
        
        if ($housingFundConfigId) {
            $housingFundConfig = \App\Models\HousingFundConfig::find($housingFundConfigId);
            if ($housingFundConfig) {
                $minBase = $housingFundConfig->min_base_amount;
                $maxBase = $housingFundConfig->max_base_amount;
                $housingFundBase = self::applyConstraint($housingFundBase, $minBase, $maxBase);
                
                \Log::info('公积金基数约束', [
                    'employee_name' => $change->employee_name,
                    'original' => $change->employee_housing_fund_base,
                    'config_id' => $housingFundConfigId,
                    'min' => $minBase,
                    'max' => $maxBase,
                    'constrained' => $housingFundBase
                ]);
            }
        }

        // 调试：输出约束后的基数
        file_put_contents(storage_path('logs/base_constraint_debug.log'), 
            "约束后社保基数: {$socialSecurityBase}\n" .
            "约束后医保基数: {$medicalInsuranceBase}\n" .
            "约束后公积金基数: {$housingFundBase}\n" .
            str_repeat('=', 50) . "\n\n",
            FILE_APPEND
        );

        return [
            'social_security' => $socialSecurityBase,
            'medical_insurance' => $medicalInsuranceBase,
            'housing_fund' => $housingFundBase
        ];
    }

    /**
     * 应用单个基数约束
     * 
     * @param float $base 原始基数
     * @param float|null $minBase 下限
     * @param float|null $maxBase 上限
     * @return float 约束后的基数
     */
    private static function applyConstraint($base, $minBase, $maxBase)
    {
        $constrainedBase = $base;

        // 应用下限约束
        if ($minBase !== null && $constrainedBase < $minBase) {
            $constrainedBase = $minBase;
        }

        // 应用上限约束
        if ($maxBase !== null && $constrainedBase > $maxBase) {
            $constrainedBase = $maxBase;
        }

        return $constrainedBase;
    }
}
