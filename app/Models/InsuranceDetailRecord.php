<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceDetailRecord extends Model
{
    use HasFactory;

    protected $table = 'insurance_detail_records';

    protected $fillable = [
        'insurance_personnel_id',
        'employee_id',
        'employee_name',
        'employee_id_number',
        'employee_gender',
        'employee_birth_date',
        'employee_phone',
        'project_id',
        'project_name',
        'account_set_id',
        'record_year',
        'record_month',
        'employee_social_security_base',
        'employee_medical_insurance_base',
        'employee_housing_fund_base',
        'employee_large_medical_base',
        'social_security_types',
        'medical_insurance_types',
        'housing_fund_params',
        'other_insurance_policies',
        'large_medical_insurance_config',
        'social_security_company_amount',
        'social_security_employee_amount',
        'medical_insurance_company_amount',
        'medical_insurance_employee_amount',
        'housing_fund_company_amount',
        'housing_fund_employee_amount',
        'large_medical_company_amount',
        'large_medical_employee_amount',
        'other_insurance_total_amount',
        'status',
        'generated_at',
        'confirmed_at'
    ];

    protected $casts = [
        'employee_birth_date' => 'date',
        'employee_social_security_base' => 'decimal:2',
        'employee_medical_insurance_base' => 'decimal:2',
        'employee_housing_fund_base' => 'decimal:2',
        'employee_large_medical_base' => 'decimal:2',
        'social_security_company_amount' => 'decimal:2',
        'social_security_employee_amount' => 'decimal:2',
        'medical_insurance_company_amount' => 'decimal:2',
        'medical_insurance_employee_amount' => 'decimal:2',
        'housing_fund_company_amount' => 'decimal:2',
        'housing_fund_employee_amount' => 'decimal:2',
        'large_medical_company_amount' => 'decimal:2',
        'large_medical_employee_amount' => 'decimal:2',
        'other_insurance_total_amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    // 关联关系
    public function insurancePersonnel()
    {
        return $this->belongsTo(InsurancePersonnel::class, 'insurance_personnel_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    // 从参保人员信息生成明细记录
    public static function generateFromPersonnel($personnel, $year, $month)
    {
        // 检查是否已存在该月份的记录
        $existingRecord = self::where('employee_id', $personnel->employee_id)
            ->where('project_id', $personnel->project_id)
            ->where('account_set_id', $personnel->account_set_id)
            ->where('record_year', $year)
            ->where('record_month', $month)
            ->first();

        if ($existingRecord) {
            // 处理其他保险：判断每个保单是否已参保
            $otherInsurancePolicies = $personnel->other_insurance_policies;
            $policiesToEnroll = [];
            
            if ($otherInsurancePolicies && !empty($otherInsurancePolicies) && $otherInsurancePolicies !== '[]') {
                $policies = json_decode($otherInsurancePolicies, true);
                
                if (is_array($policies)) {
                    // 获取已参保的保单列表
                    $enrolledPolicies = [];
                    if ($personnel->other_insurance_policy_versions) {
                        $enrolledPolicies = json_decode($personnel->other_insurance_policy_versions, true) ?? [];
                    }
                    
                    // 检查每个保单是否已参保（考虑保单有效期变更）
                    foreach ($policies as $policy) {
                        $policyId = $policy['id'] ?? null;
                        if (!$policyId) continue;
                        
                        // ✅ 关键修改：从数据库获取保单的最新有效期（用于检测续约）
                        $latestPolicy = \App\Models\OtherInsurancePolicy::find($policyId);
                        if ($latestPolicy) {
                            // 使用数据库中的最新有效期
                            $policy['policy_start_date'] = $latestPolicy->policy_start_date ?? $latestPolicy->start_date;
                            $policy['policy_end_date'] = $latestPolicy->policy_end_date_new ?? $latestPolicy->end_date;
                        }
                        
                        // ✅ 判断当前月份是否在保单有效期内
                        $currentDate = \Carbon\Carbon::create($year, $month, 1);
                        $startDate = \Carbon\Carbon::parse($policy['policy_start_date'] ?? $policy['start_date'] ?? null);
                        $endDate = \Carbon\Carbon::parse($policy['policy_end_date'] ?? $policy['end_date'] ?? null);
                        
                        if (!$startDate || !$endDate || $currentDate->lt($startDate) || $currentDate->gt($endDate)) {
                            // 不在保单有效期内，跳过
                            \Log::info('保单不在有效期内，更新明细时跳过', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'current_date' => $currentDate->format('Y-m'),
                                'start_date' => $startDate ? $startDate->format('Y-m-d') : 'null',
                                'end_date' => $endDate ? $endDate->format('Y-m-d') : 'null'
                            ]);
                            continue;
                        }
                        
                        // 生成保单唯一标识：保单ID + 有效期
                        $policyKey = self::generatePolicyKey($policy);
                        
                        if (!isset($enrolledPolicies[$policyKey])) {
                            // 该保单（或该版本）未参保，需要生成明细
                            $policiesToEnroll[] = $policy;
                            
                            \Log::info('保单在有效期内且未参保，更新明细时需要生成', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'policy_key' => $policyKey,
                                'policy_name' => $policy['name'] ?? '未知',
                                'year' => $year,
                                'month' => $month
                            ]);
                        } else {
                            \Log::info('保单已参保，更新明细时跳过', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'policy_key' => $policyKey,
                                'enrolled_at' => $enrolledPolicies[$policyKey] ?? 'unknown',
                                'year' => $year,
                                'month' => $month
                            ]);
                        }
                    }
                    
                    // 只保留需要参保的保单
                    if (!empty($policiesToEnroll)) {
                        $otherInsurancePolicies = json_encode($policiesToEnroll);
                    } else {
                        $otherInsurancePolicies = '';
                    }
                }
            }
            
            // 更新现有记录
            $existingRecord->update([
                'employee_name' => $personnel->employee_name,
                'employee_id_number' => $personnel->employee_id_number,
                'employee_gender' => $personnel->employee_gender,
                'employee_birth_date' => $personnel->employee_birth_date,
                'employee_phone' => $personnel->employee_phone,
                'project_name' => $personnel->project ? $personnel->project->name : null,
                'employee_social_security_base' => $personnel->employee_social_security_base,
                'employee_medical_insurance_base' => $personnel->employee_medical_insurance_base,
                'employee_housing_fund_base' => $personnel->employee_housing_fund_base,
                'employee_large_medical_base' => $personnel->employee_large_medical_base,
                'social_security_types' => $personnel->social_security_types,
                'medical_insurance_types' => $personnel->medical_insurance_types,
                'housing_fund_params' => $personnel->housing_fund_params,
                'other_insurance_policies' => $otherInsurancePolicies,
                'large_medical_insurance_config' => $personnel->large_medical_insurance_config,
                'generated_at' => now(),
            ]);

            // 如果有新参保的保单，记录参保信息
            if (!empty($policiesToEnroll)) {
                $now = now();
                
                // 获取已参保列表
                $enrolledPolicies = [];
                if ($personnel->other_insurance_policy_versions) {
                    $enrolledPolicies = json_decode($personnel->other_insurance_policy_versions, true) ?? [];
                }
                
                // 添加新参保的保单（使用保单Key，包含有效期）
                foreach ($policiesToEnroll as $policy) {
                    $policyKey = self::generatePolicyKey($policy);
                    $enrolledPolicies[$policyKey] = $now->format('Y-m-d H:i:s');
                }
                
                // 保存参保记录
                $personnel->other_insurance_enrolled_at = $now;
                $personnel->other_insurance_policy_versions = json_encode($enrolledPolicies);
                $personnel->save();
                
                \Log::info('更新时记录保单参保信息', [
                    'employee_id' => $personnel->employee_id,
                    'personnel_id' => $personnel->id,
                    'enrolled_policies' => $enrolledPolicies,
                    'policy_count' => count($policiesToEnroll)
                ]);
            }
            
            // 重新计算金额
            $existingRecord->calculateAmounts();
            
            return $existingRecord;
        } else {
            // 处理其他保险：判断每个保单是否已参保
            $otherInsurancePolicies = $personnel->other_insurance_policies;
            $policiesToEnroll = [];
            $shouldEnroll = false;
            
            if ($otherInsurancePolicies && !empty($otherInsurancePolicies) && $otherInsurancePolicies !== '[]') {
                $policies = json_decode($otherInsurancePolicies, true);
                
                if (is_array($policies)) {
                    // 获取已参保的保单列表
                    $enrolledPolicies = [];
                    if ($personnel->other_insurance_policy_versions) {
                        $enrolledPolicies = json_decode($personnel->other_insurance_policy_versions, true) ?? [];
                    }
                    
                    // 检查每个保单是否已参保（考虑保单有效期变更）
                    foreach ($policies as $policy) {
                        $policyId = $policy['id'] ?? null;
                        if (!$policyId) continue;
                        
                        // ✅ 关键修改：从数据库获取保单的最新有效期（用于检测续约）
                        $latestPolicy = \App\Models\OtherInsurancePolicy::find($policyId);
                        if ($latestPolicy) {
                            // 使用数据库中的最新有效期
                            $policy['policy_start_date'] = $latestPolicy->policy_start_date ?? $latestPolicy->start_date;
                            $policy['policy_end_date'] = $latestPolicy->policy_end_date_new ?? $latestPolicy->end_date;
                        }
                        
                        // ✅ 判断当前月份是否在保单有效期内
                        $currentDate = \Carbon\Carbon::create($year, $month, 1);
                        $startDate = \Carbon\Carbon::parse($policy['policy_start_date'] ?? $policy['start_date'] ?? null);
                        $endDate = \Carbon\Carbon::parse($policy['policy_end_date'] ?? $policy['end_date'] ?? null);
                        
                        if (!$startDate || !$endDate || $currentDate->lt($startDate) || $currentDate->gt($endDate)) {
                            // 不在保单有效期内，跳过
                            \Log::info('保单不在有效期内，跳过生成明细', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'current_date' => $currentDate->format('Y-m'),
                                'start_date' => $startDate ? $startDate->format('Y-m-d') : 'null',
                                'end_date' => $endDate ? $endDate->format('Y-m-d') : 'null'
                            ]);
                            continue;
                        }
                        
                        // 生成保单唯一标识：保单ID + 有效期
                        $policyKey = self::generatePolicyKey($policy);
                        
                        if (!isset($enrolledPolicies[$policyKey])) {
                            // 该保单（或该版本）未参保，需要生成明细
                            $policiesToEnroll[] = $policy;
                            $shouldEnroll = true;
                            
                            \Log::info('保单在有效期内且未参保，需要生成明细', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'policy_key' => $policyKey,
                                'policy_name' => $policy['name'] ?? '未知',
                                'year' => $year,
                                'month' => $month
                            ]);
                        } else {
                            \Log::info('保单已参保，跳过', [
                                'employee_id' => $personnel->employee_id,
                                'policy_id' => $policyId,
                                'policy_key' => $policyKey,
                                'enrolled_at' => $enrolledPolicies[$policyKey] ?? 'unknown',
                                'year' => $year,
                                'month' => $month
                            ]);
                        }
                    }
                    
                    // 只保留需要参保的保单
                    if (!empty($policiesToEnroll)) {
                        $otherInsurancePolicies = json_encode($policiesToEnroll);
                    } else {
                        $otherInsurancePolicies = '';
                    }
                } else {
                    $otherInsurancePolicies = '';
                }
            }
            
            // 创建新记录
            $record = self::create([
                'insurance_personnel_id' => $personnel->id,
                'employee_id' => $personnel->employee_id,
                'employee_name' => $personnel->employee_name,
                'employee_id_number' => $personnel->employee_id_number,
                'employee_gender' => $personnel->employee_gender,
                'employee_birth_date' => $personnel->employee_birth_date,
                'employee_phone' => $personnel->employee_phone,
                'project_id' => $personnel->project_id,
                'project_name' => $personnel->project ? $personnel->project->name : null,
                'account_set_id' => $personnel->account_set_id,
                'record_year' => $year,
                'record_month' => $month,
                'employee_social_security_base' => $personnel->employee_social_security_base,
                'employee_medical_insurance_base' => $personnel->employee_medical_insurance_base,
                'employee_housing_fund_base' => $personnel->employee_housing_fund_base,
                'employee_large_medical_base' => $personnel->employee_large_medical_base,
                'social_security_types' => $personnel->social_security_types,
                'medical_insurance_types' => $personnel->medical_insurance_types,
                'housing_fund_params' => $personnel->housing_fund_params,
                'other_insurance_policies' => $otherInsurancePolicies,
                'large_medical_insurance_config' => $personnel->large_medical_insurance_config,
                'status' => 'generated',
                'generated_at' => now(),
            ]);

            // 如果有新参保的保单，记录参保信息
            if ($shouldEnroll && !empty($policiesToEnroll)) {
                $now = now();
                
                // 获取已参保列表
                $enrolledPolicies = [];
                if ($personnel->other_insurance_policy_versions) {
                    $enrolledPolicies = json_decode($personnel->other_insurance_policy_versions, true) ?? [];
                }
                
                // 添加新参保的保单（使用保单Key，包含有效期）
                foreach ($policiesToEnroll as $policy) {
                    $policyKey = self::generatePolicyKey($policy);
                    $enrolledPolicies[$policyKey] = $now->format('Y-m-d H:i:s');
                }
                
                // 保存参保记录
                $personnel->other_insurance_enrolled_at = $now; // 记录最后参保时间
                $personnel->other_insurance_policy_versions = json_encode($enrolledPolicies);
                $personnel->save();
                
                \Log::info('记录保单参保信息', [
                    'employee_id' => $personnel->employee_id,
                    'personnel_id' => $personnel->id,
                    'enrolled_policies' => $enrolledPolicies,
                    'policy_count' => count($policiesToEnroll)
                ]);
            }

            // 计算金额
            $record->calculateAmounts();
            
            return $record;
        }
    }

    /**
     * 生成保单唯一标识（保单ID + 有效期）
     * 用于区分同一保单的不同版本（续约）
     */
    private static function generatePolicyKey($policy)
    {
        $policyId = $policy['id'] ?? 'unknown';
        $startDate = $policy['policy_start_date'] ?? $policy['start_date'] ?? '';
        $endDate = $policy['policy_end_date'] ?? $policy['end_date'] ?? '';
        
        // 生成唯一key: policy_id_startdate_enddate
        // 例如: 3_20250101_20251231
        $key = $policyId;
        if ($startDate && $endDate) {
            // 提取日期部分（去掉时间部分）
            $startDate = substr($startDate, 0, 10); // 取前10位：YYYY-MM-DD
            $endDate = substr($endDate, 0, 10);
            
            $key .= '_' . str_replace('-', '', $startDate) . '_' . str_replace('-', '', $endDate);
        }
        
        return $key;
    }

    // 计算各种保险金额 - 直接使用新表字段，无需重新计算
    public function calculateAmounts()
    {
        // 从快照数据重新计算所有金额
        $amounts = $this->calculateInsuranceAmounts();
        
        $this->update([
            'social_security_company_amount' => $amounts['social_security']['company'],
            'social_security_employee_amount' => $amounts['social_security']['employee'],
            'medical_insurance_company_amount' => $amounts['medical_insurance']['company'],
            'medical_insurance_employee_amount' => $amounts['medical_insurance']['employee'],
            'housing_fund_company_amount' => $amounts['housing_fund']['company'],
            'housing_fund_employee_amount' => $amounts['housing_fund']['employee'],
            'large_medical_company_amount' => $amounts['large_medical']['company'],
            'large_medical_employee_amount' => $amounts['large_medical']['employee'],
            'other_insurance_total_amount' => $amounts['other_insurance']['total'],
        ]);
    }

    // 计算保险金额的具体逻辑
    private function calculateInsuranceAmounts()
    {
        $amounts = [
            'social_security' => ['company' => 0, 'employee' => 0],
            'medical_insurance' => ['company' => 0, 'employee' => 0],
            'housing_fund' => ['company' => 0, 'employee' => 0],
            'large_medical' => ['company' => 0, 'employee' => 0],
            'other_insurance' => ['total' => 0],
        ];

        // 计算社保金额
        if ($this->social_security_types && $this->employee_social_security_base) {
            $socialSecurityTypes = json_decode($this->social_security_types, true);
            if (is_array($socialSecurityTypes)) {
                foreach ($socialSecurityTypes as $type) {
                    $base = $this->employee_social_security_base;
                    $companyRatio = $type['company_ratio'] ?? 0;
                    $employeeRatio = $type['employee_ratio'] ?? 0;
                    
                    $amounts['social_security']['company'] += $base * $companyRatio;
                    $amounts['social_security']['employee'] += $base * $employeeRatio;
                }
            }
        }

        // 计算医保金额
        if ($this->medical_insurance_types && $this->employee_medical_insurance_base) {
            $medicalInsuranceTypes = json_decode($this->medical_insurance_types, true);
            if (is_array($medicalInsuranceTypes)) {
                foreach ($medicalInsuranceTypes as $type) {
                    $base = $this->employee_medical_insurance_base;
                    $companyRatio = $type['company_ratio'] ?? 0;
                    $employeeRatio = $type['employee_ratio'] ?? 0;
                    
                    $amounts['medical_insurance']['company'] += $base * $companyRatio;
                    $amounts['medical_insurance']['employee'] += $base * $employeeRatio;
                }
            }
        }

        // 计算公积金金额
        if ($this->housing_fund_params && $this->employee_housing_fund_base) {
            $housingFundParams = json_decode($this->housing_fund_params, true);
            if (is_array($housingFundParams)) {
                $base = $this->employee_housing_fund_base;
                $companyRatio = $housingFundParams['company_ratio'] ?? 0;
                $employeeRatio = $housingFundParams['employee_ratio'] ?? 0;
                
                $amounts['housing_fund']['company'] = $base * $companyRatio;
                $amounts['housing_fund']['employee'] = $base * $employeeRatio;
            }
        }

        // 计算大额医疗保险金额（支持付款周期）
        // 注意：固定金额模式不需要基数，所以不能要求 employee_large_medical_base 必须有值
        if ($this->large_medical_insurance_config) {
            $largeMedicalConfig = json_decode($this->large_medical_insurance_config, true);
            if (is_array($largeMedicalConfig) && ($largeMedicalConfig['is_enabled'] ?? false)) {
                // 检查计算类型
                $calculationType = $largeMedicalConfig['calculation_type'] ?? 'base';
                $paymentCycle = $largeMedicalConfig['payment_cycle'] ?? 'month';
                
                // 判断是否为支付月份
                $isPaymentMonth = true;
                if ($paymentCycle === 'year' || $paymentCycle === 'yearly') {
                    // 按年付款：需要检查是否为支付月份
                    $personnel = \App\Models\InsurancePersonnel::where('employee_id', $this->employee_id)->first();
                    if ($personnel && $personnel->large_medical_payment_start_month && $personnel->large_medical_payment_start_year) {
                        // 检查月份是否匹配
                        if ($this->record_month != $personnel->large_medical_payment_start_month) {
                            $isPaymentMonth = false;
                        }
                        // 检查年份是否有效
                        if ($this->record_year < $personnel->large_medical_payment_start_year) {
                            $isPaymentMonth = false;
                        }
                    } else {
                        // 没有设置支付起始月份，默认当前月份为支付月份（首次入职）
                        $isPaymentMonth = true;
                    }
                }
                
                if ($isPaymentMonth) {
                    if ($calculationType === 'fixed') {
                        // 固定金额模式：直接使用配置中的金额
                        $amounts['large_medical']['company'] = floatval($largeMedicalConfig['company_amount'] ?? 0);
                        $amounts['large_medical']['employee'] = floatval($largeMedicalConfig['employee_amount'] ?? 0);
                    } else {
                        // 按基数模式：需要基数
                        if ($this->employee_large_medical_base) {
                            $baseAmount = floatval($this->employee_large_medical_base);
                            $companyRatio = floatval($largeMedicalConfig['company_ratio'] ?? 0);
                            $employeeRatio = floatval($largeMedicalConfig['employee_ratio'] ?? 0);
                            
                            $amounts['large_medical']['company'] = $baseAmount * $companyRatio;
                            $amounts['large_medical']['employee'] = $baseAmount * $employeeRatio;
                        }
                    }
                }
            }
        }

        // 计算其他保险金额
        if ($this->other_insurance_policies) {
            $otherInsurancePolicies = json_decode($this->other_insurance_policies, true);
            if (is_array($otherInsurancePolicies)) {
                foreach ($otherInsurancePolicies as $policy) {
                    $amounts['other_insurance']['total'] += $policy['employee_per_capita_cost'] ?? 0;
                }
            }
        }

        return $amounts;
    }
}
