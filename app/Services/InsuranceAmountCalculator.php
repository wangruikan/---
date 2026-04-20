<?php

namespace App\Services;

class InsuranceAmountCalculator
{
    /**
     * 应用基数上下限约束
     * 
     * @param float $employeeBase 员工设定的基数
     * @param float|null $minBase 下限基数
     * @param float|null $maxBase 上限基数
     * @return float 约束后的基数
     */
    public static function applyBaseConstraints($employeeBase, $minBase, $maxBase)
    {
        // 如果没有设置上下限，直接返回原基数
        if ($minBase === null && $maxBase === null) {
            return $employeeBase;
        }
        
        $constrainedBase = $employeeBase;
        
        // 应用下限约束：若职工对应基数低于下限，按下限计算金额
        if ($minBase !== null && $constrainedBase < $minBase) {
            $constrainedBase = $minBase;
        }
        
        // 应用上限约束：若高于上限，按上限计算金额
        if ($maxBase !== null && $constrainedBase > $maxBase) {
            $constrainedBase = $maxBase;
        }
        
        return $constrainedBase;
    }

    /**
     * 计算社保金额（应用上下限约束）
     *
     * @param float $employeeBase 员工社保基数
     * @param array $socialSecurityTypes 社保类型配置数组
     * @return array ['company' => float, 'employee' => float, 'constrained_base' => float]
     */
    public static function calculateSocialSecurityAmount($employeeBase, $socialSecurityTypes)
    {
        $companyAmount = 0;
        $employeeAmount = 0;
        $finalConstrainedBase = $employeeBase;

        if (!is_array($socialSecurityTypes) || empty($socialSecurityTypes)) {
            return ['company' => 0, 'employee' => 0, 'constrained_base' => $employeeBase];
        }

        // 使用第一个类型的上下限来约束基数（通常同一地区的上下限是一致的）
        $firstType = reset($socialSecurityTypes);
        $minBase = $firstType['min_base_amount'] ?? null;
        $maxBase = $firstType['max_base_amount'] ?? null;
        
        // 应用上下限约束获得最终基数
        $finalConstrainedBase = self::applyBaseConstraints($employeeBase, $minBase, $maxBase);

        foreach ($socialSecurityTypes as $type) {
            $companyRatio = floatval($type['company_ratio'] ?? 0);
            $employeeRatio = floatval($type['employee_ratio'] ?? 0);
            
            $companyAmount += $finalConstrainedBase * $companyRatio;
            $employeeAmount += $finalConstrainedBase * $employeeRatio;
        }

        return [
            'company' => $companyAmount,
            'employee' => $employeeAmount,
            'constrained_base' => $finalConstrainedBase
        ];
    }

    /**
     * 计算医保金额（应用上下限约束）
     *
     * @param float $employeeBase 员工医保基数
     * @param array $medicalInsuranceTypes 医保类型配置数组
     * @return array ['company' => float, 'employee' => float, 'constrained_base' => float]
     */
    public static function calculateMedicalInsuranceAmount($employeeBase, $medicalInsuranceTypes)
    {
        $companyAmount = 0;
        $employeeAmount = 0;
        $finalConstrainedBase = $employeeBase;

        if (!is_array($medicalInsuranceTypes) || empty($medicalInsuranceTypes)) {
            return ['company' => 0, 'employee' => 0, 'constrained_base' => $employeeBase];
        }

        // 使用第一个类型的上下限来约束基数（通常同一地区的上下限是一致的）
        $firstType = reset($medicalInsuranceTypes);
        $minBase = $firstType['min_base_amount'] ?? null;
        $maxBase = $firstType['max_base_amount'] ?? null;
        
        // 应用上下限约束获得最终基数
        $finalConstrainedBase = self::applyBaseConstraints($employeeBase, $minBase, $maxBase);

        foreach ($medicalInsuranceTypes as $type) {
            $companyRatio = floatval($type['company_ratio'] ?? 0);
            $employeeRatio = floatval($type['employee_ratio'] ?? 0);
            
            $companyAmount += $finalConstrainedBase * $companyRatio;
            $employeeAmount += $finalConstrainedBase * $employeeRatio;
        }

        return [
            'company' => $companyAmount,
            'employee' => $employeeAmount,
            'constrained_base' => $finalConstrainedBase
        ];
    }

    /**
     * 计算公积金金额（应用上下限约束）
     *
     * @param float $employeeBase 员工公积金基数
     * @param array $housingFundConfig 公积金配置
     * @return array ['company' => float, 'employee' => float, 'constrained_base' => float]
     */
    public static function calculateHousingFundAmount($employeeBase, $housingFundConfig)
    {
        if (!is_array($housingFundConfig) || empty($housingFundConfig)) {
            return ['company' => 0, 'employee' => 0, 'constrained_base' => $employeeBase];
        }

        $minBase = $housingFundConfig['min_base_amount'] ?? null;
        $maxBase = $housingFundConfig['max_base_amount'] ?? null;
        
        // 应用上下限约束
        $constrainedBase = self::applyBaseConstraints($employeeBase, $minBase, $maxBase);
        
        $companyRatio = floatval($housingFundConfig['company_ratio'] ?? 0);
        $employeeRatio = floatval($housingFundConfig['employee_ratio'] ?? 0);
        
        return [
            'company' => $constrainedBase * $companyRatio,
            'employee' => $constrainedBase * $employeeRatio,
            'constrained_base' => $constrainedBase
        ];
    }
}


