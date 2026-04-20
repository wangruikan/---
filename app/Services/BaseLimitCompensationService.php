<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\BaseAdjustment;
use App\Models\InsuranceCompensationRecord;
use App\Models\InsurancePersonnel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 上下限变更补差计算服务
 * 
 * 当修改保险上下限时，立即计算并生成补差记录
 * 
 * 补差逻辑：旧基数（旧上下限约束）vs 当前基数（新上下限约束）
 * - 旧基数：查找今年之前最后一次调基的基数
 * - 当前基数：员工表的当前基数（会在调基生效时自动更新）
 */
class BaseLimitCompensationService
{
    /**
     * 当社保上下限变更时，计算所有受影响员工的补差
     * 
     * @param int $regionId 地区ID
     * @param float $oldMinBase 旧下限
     * @param float $oldMaxBase 旧上限
     * @param float $newMinBase 新下限
     * @param float $newMaxBase 新上限
     * @return array 处理结果
     */
    public function calculateSocialSecurityCompensation($regionId, $oldMinBase, $oldMaxBase, $newMinBase, $newMaxBase)
    {
        Log::info('开始计算社保上下限变更补差', [
            'region_id' => $regionId,
            'old_limits' => [$oldMinBase, $oldMaxBase],
            'new_limits' => [$newMinBase, $newMaxBase]
        ]);

        $affectedCount = 0;
        $compensationCount = 0;

        // 查找使用该地区社保的所有员工
        $employees = Employee::where('social_security_region_id', $regionId)->get();

        foreach ($employees as $employee) {
            $affectedCount++;
            
            // ✅ 新逻辑：查找旧基数和新基数
            $currentMonth = Carbon::now()->startOfMonth();
            
            // 1. 查找旧基数：本月之前最近一次生效的基数
            $lastAdjustmentBeforeThisMonth = BaseAdjustment::where('employee_id', $employee->id)
                ->where('status', 'applied')
                ->whereNotNull('social_security_effective_date')
                ->where('social_security_effective_date', '<', $currentMonth)  // 本月之前
                ->orderBy('social_security_effective_date', 'desc')
                ->first();

            if ($lastAdjustmentBeforeThisMonth) {
                $oldBase = $lastAdjustmentBeforeThisMonth->new_social_security_base;
                $effectiveDate = Carbon::parse($lastAdjustmentBeforeThisMonth->social_security_effective_date);
            } else {
                // 如果没有历史调基记录，使用员工当前基数
                $oldBase = $employee->social_security_base;
                $effectiveDate = Carbon::parse(Carbon::now()->year . '-01-01');
            }
            
            // 2. 查找新基数：本月是否有生效的基数
            $thisMonthAdjustment = BaseAdjustment::where('employee_id', $employee->id)
                ->where('status', 'applied')
                ->whereNotNull('social_security_effective_date')
                ->whereYear('social_security_effective_date', Carbon::now()->year)
                ->whereMonth('social_security_effective_date', Carbon::now()->month)
                ->orderBy('social_security_effective_date', 'desc')
                ->first();
            
            if ($thisMonthAdjustment) {
                // 本月有调基，使用本月的基数作为新基数
                $currentBase = $thisMonthAdjustment->new_social_security_base;
            } else {
                // 本月没有调基，使用旧基数作为新基数（但会用新上下限约束）
                $currentBase = $oldBase;
            }

            // 应用旧上下限约束到旧基数
            $oldBaseConstrained = $this->applyConstraints($oldBase, $oldMinBase, $oldMaxBase);
            
            // 应用新上下限约束到当前基数
            $newBaseConstrained = $this->applyConstraints($currentBase, $newMinBase, $newMaxBase);

            // 记录详细日志
            Log::info('修改上下限补差计算', [
                'employee' => $employee->name,
                'old_base_original' => $oldBase,
                'old_base_constrained' => $oldBaseConstrained,
                'old_limits' => [$oldMinBase, $oldMaxBase],
                'current_base_original' => $currentBase,
                'new_base_constrained' => $newBaseConstrained,
                'new_limits' => [$newMinBase, $newMaxBase],
                'difference' => $newBaseConstrained - $oldBaseConstrained
            ]);

            // 如果约束后的基数不同，需要补差
            if ($oldBaseConstrained != $newBaseConstrained) {
                $result = $this->createCompensationRecord(
                    $employee,
                    'social_security',
                    $oldBase,
                    $currentBase,
                    $oldBaseConstrained,
                    $newBaseConstrained,
                    $effectiveDate,
                    now(),
                    '社保上下限调整补差',
                    $employee->social_security_region_id
                );

                if ($result) {
                    $compensationCount++;
                }
            }
        }

        return [
            'affected_count' => $affectedCount,
            'compensation_count' => $compensationCount
        ];
    }

    /**
     * 当医保上下限变更时，不计算补差
     * 
     * ⚠️ 需求变更：医保不需要补差
     * 
     * @return array
     */
    public function calculateMedicalInsuranceCompensation($regionId, $oldMinBase, $oldMaxBase, $newMinBase, $newMaxBase)
    {
        Log::info('医保上下限变更（不计算补差）', [
            'region_id' => $regionId,
            'old_limits' => [$oldMinBase, $oldMaxBase],
            'new_limits' => [$newMinBase, $newMaxBase],
            'note' => '医保不需要补差，跳过'
        ]);

        // 医保不需要补差，直接返回
        return [
            'affected_count' => 0,
            'compensation_count' => 0,
            'skipped' => true,
            'reason' => '医保不需要补差'
        ];
    }

    /**
     * 当公积金上下限变更时，计算补差
     */
    public function calculateHousingFundCompensation($configId, $oldMinBase, $oldMaxBase, $newMinBase, $newMaxBase)
    {
        Log::info('开始计算公积金上下限变更补差', [
            'config_id' => $configId,
            'old_limits' => [$oldMinBase, $oldMaxBase],
            'new_limits' => [$newMinBase, $newMaxBase]
        ]);

        $affectedCount = 0;
        $compensationCount = 0;

        $employees = Employee::where('housing_fund_config_id', $configId)->get();

        foreach ($employees as $employee) {
            $affectedCount++;
            
            // ✅ 新逻辑：查找旧基数和新基数
            $currentMonth = Carbon::now()->startOfMonth();
            
            // 1. 查找旧基数：本月之前最近一次生效的基数
            $lastAdjustmentBeforeThisMonth = BaseAdjustment::where('employee_id', $employee->id)
                ->where('status', 'applied')
                ->whereNotNull('housing_fund_effective_date')
                ->where('housing_fund_effective_date', '<', $currentMonth)  // 本月之前
                ->orderBy('housing_fund_effective_date', 'desc')
                ->first();

            if ($lastAdjustmentBeforeThisMonth) {
                $oldBase = $lastAdjustmentBeforeThisMonth->new_housing_fund_base;
                $effectiveDate = Carbon::parse($lastAdjustmentBeforeThisMonth->housing_fund_effective_date);
            } else {
                // 如果没有历史调基记录，使用员工当前基数
                $oldBase = $employee->housing_fund_base;
                $effectiveDate = Carbon::parse(Carbon::now()->year . '-01-01');
            }
            
            // 2. 查找新基数：本月是否有生效的基数
            $thisMonthAdjustment = BaseAdjustment::where('employee_id', $employee->id)
                ->where('status', 'applied')
                ->whereNotNull('housing_fund_effective_date')
                ->whereYear('housing_fund_effective_date', Carbon::now()->year)
                ->whereMonth('housing_fund_effective_date', Carbon::now()->month)
                ->orderBy('housing_fund_effective_date', 'desc')
                ->first();
            
            if ($thisMonthAdjustment) {
                // 本月有调基，使用本月的基数作为新基数
                $currentBase = $thisMonthAdjustment->new_housing_fund_base;
            } else {
                // 本月没有调基，使用旧基数作为新基数（但会用新上下限约束）
                $currentBase = $oldBase;
            }

            $oldBaseConstrained = $this->applyConstraints($oldBase, $oldMinBase, $oldMaxBase);
            $newBaseConstrained = $this->applyConstraints($currentBase, $newMinBase, $newMaxBase);

            if ($oldBaseConstrained != $newBaseConstrained) {
                $result = $this->createCompensationRecord(
                    $employee,
                    'housing_fund',
                    $oldBase,
                    $currentBase,
                    $oldBaseConstrained,
                    $newBaseConstrained,
                    $effectiveDate,
                    now(),
                    '公积金上下限调整补差',
                    null,
                    $configId
                );

                if ($result) {
                    $compensationCount++;
                }
            }
        }

        return [
            'affected_count' => $affectedCount,
            'compensation_count' => $compensationCount
        ];
    }

    /**
     * 应用上下限约束
     */
    private function applyConstraints($base, $minBase, $maxBase)
    {
        $constrained = $base;

        if ($minBase !== null && $constrained < $minBase) {
            $constrained = $minBase;
        }

        if ($maxBase !== null && $constrained > $maxBase) {
            $constrained = $maxBase;
        }

        return $constrained;
    }

    /**
     * 创建补差记录
     */
    private function createCompensationRecord(
        $employee,
        $compensationType,
        $oldBase,
        $newBase,
        $oldBaseConstrained,
        $newBaseConstrained,
        $startDate,
        $endDate,
        $remark,
        $regionId = null,
        $configId = null
    ) {
        try {
            // ✅ 补差期间：每年固定从1月到修改上下限的前一个月
            $currentYear = Carbon::now()->year;
            $startMonth = Carbon::parse("{$currentYear}-01-01");  // 固定从今年1月开始
            $endMonth = Carbon::now()->subMonth()->endOfMonth();   // 到修改前一个月
            
            // 如果起始月晚于或等于结束月，不需要补差（比如1月修改上下限）
            if ($startMonth->gte($endMonth)) {
                Log::info('补差期间无效，跳过', [
                    'employee_id' => $employee->id,
                    'start_month' => $startMonth->format('Y-m'),
                    'end_month' => $endMonth->format('Y-m')
                ]);
                return false;
            }

            $compensationMonths = $startMonth->diffInMonths($endMonth) + 1;

            // ✅ 获取保险配置并生成按类型分组的补差详情
            $compensationDetails = [];
            $companyTotal = 0;
            $personalTotal = 0;
            
            $baseDiff = $newBaseConstrained - $oldBaseConstrained;

            // 根据保险类型生成详情
            switch ($compensationType) {
                case 'social_security':
                    // ✅ 从参保人员表获取员工实际参保的险种
                    $personnel = InsurancePersonnel::where('employee_id', $employee->id)
                        ->where('status', 'active')
                        ->first();
                    
                    if ($personnel && $personnel->social_security_types) {
                        // 解析参保险种配置
                        $enrolledTypes = is_string($personnel->social_security_types) 
                            ? json_decode($personnel->social_security_types, true) 
                            : $personnel->social_security_types;
                        
                        if (is_array($enrolledTypes)) {
                            foreach ($enrolledTypes as $typeConfig) {
                                // 从配置中提取比例
                                $companyRatio = floatval($typeConfig['company_ratio'] ?? 0);
                                $employeeRatio = floatval($typeConfig['employee_ratio'] ?? 0);
                                
                                $companyAmount = $baseDiff * $companyRatio * $compensationMonths;
                                $personalAmount = $baseDiff * $employeeRatio * $compensationMonths;
                                
                                $compensationDetails[] = [
                                    'name' => $typeConfig['name'] ?? '未知险种',
                                    'company_amount' => round($companyAmount, 2),
                                    'personal_amount' => round($personalAmount, 2),
                                    'company_ratio' => $companyRatio,
                                    'employee_ratio' => $employeeRatio
                                ];
                                
                                $companyTotal += $companyAmount;
                                $personalTotal += $personalAmount;
                            }
                        }
                    }
                    break;

                case 'housing_fund':
                    $config = $employee->housingFundConfig;
                    if ($config) {
                        $companyAmount = $baseDiff * floatval($config->company_ratio ?? 0) * $compensationMonths;
                        $personalAmount = $baseDiff * floatval($config->employee_ratio ?? 0) * $compensationMonths;
                        
                        $compensationDetails[] = [
                            'name' => $config->config_name,
                            'company_amount' => round($companyAmount, 2),
                            'personal_amount' => round($personalAmount, 2),
                            'company_ratio' => floatval($config->company_ratio ?? 0),
                            'employee_ratio' => floatval($config->employee_ratio ?? 0)
                        ];
                        
                        $companyTotal += $companyAmount;
                        $personalTotal += $personalAmount;
                    }
                    break;
            }

            $companyTotal = round($companyTotal, 2);
            $personalTotal = round($personalTotal, 2);
            $totalAmount = $companyTotal + $personalTotal;

            // ✅ 检查本月是否已存在补差记录（按补差期间查找）
            $existing = InsuranceCompensationRecord::where('employee_id', $employee->id)
                ->where('compensation_type', $compensationType)
                ->where('compensation_start_month', $startMonth->format('Y-m'))
                ->where('compensation_end_month', $endMonth->format('Y-m'))
                ->first();

            if ($existing) {
                // ✅ 更新现有记录（本月多次修改上下限时更新补差明细）
                $existing->update([
                    'old_base' => $oldBaseConstrained,  // ✅ 直接保存约束后的值
                    'new_base' => $newBaseConstrained,  // ✅ 直接保存约束后的值
                    'compensation_months' => $compensationMonths,
                    'compensation_details' => json_encode($compensationDetails),
                    'company_total' => $companyTotal,
                    'personal_total' => $personalTotal,
                    'total_amount' => $totalAmount,
                    'remark' => $remark,
                    'updated_at' => now()
                ]);
                
                Log::info('补差记录更新成功', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'compensation_type' => $compensationType,
                    'action' => 'updated',
                    'old_base' => $oldBase,
                    'new_base' => $newBase,
                    'old_constrained' => $oldBaseConstrained,
                    'new_constrained' => $newBaseConstrained,
                    'months' => $compensationMonths,
                    'total_amount' => $totalAmount
                ]);
            } else {
                // ✅ 创建新记录（直接保存约束后的值到old_base和new_base）
                InsuranceCompensationRecord::create([
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'employee_id_number' => $employee->id_number,
                    'project_id' => is_array($employee->project_ids) ? ($employee->project_ids[0] ?? null) : (json_decode($employee->project_ids, true)[0] ?? null),
                    'account_set_id' => $employee->account_set_id,
                    'compensation_type' => $compensationType,
                    'old_base' => $oldBaseConstrained,  // ✅ 直接保存约束后的值
                    'new_base' => $newBaseConstrained,  // ✅ 直接保存约束后的值
                    'compensation_start_month' => $startMonth->format('Y-m'),
                    'compensation_end_month' => $endMonth->format('Y-m'),
                    'compensation_months' => $compensationMonths,
                    'compensation_details' => json_encode($compensationDetails),
                    'company_total' => $companyTotal,
                    'personal_total' => $personalTotal,
                    'total_amount' => $totalAmount,
                    'region_id' => $regionId,
                    'config_id' => $configId,
                    'status' => 'pending',
                    'remark' => $remark,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                Log::info('补差记录创建成功', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'compensation_type' => $compensationType,
                    'action' => 'created',
                    'old_base' => $oldBase,
                    'new_base' => $newBase,
                    'old_constrained' => $oldBaseConstrained,
                    'new_constrained' => $newBaseConstrained,
                    'months' => $compensationMonths,
                    'total_amount' => $totalAmount
                ]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('创建补差记录失败', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

}
