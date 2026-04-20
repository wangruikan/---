<?php

namespace App\Services;

use App\Models\InsurancePersonnel;
use App\Models\InsuranceDetailRecord;
use Illuminate\Support\Facades\Log;

/**
 * 大额医疗保险付款周期服务
 * 处理年付和月付两种模式的逻辑
 */
class LargeMedicalPaymentCycleService
{
    /**
     * 判断是否为支付月份
     * 
     * @param int $employeeId 员工ID
     * @param int $year 年份
     * @param int $month 月份
     * @return bool 是否为支付月份
     */
    public function isPaymentMonth($employeeId, $year, $month)
    {
        try {
            // 获取员工的大额医疗保险配置
            $personnel = InsurancePersonnel::where('employee_id', $employeeId)->first();
            
            if (!$personnel || !$personnel->large_medical_insurance_enabled) {
                return false;
            }
            
            // 解析配置
            $config = json_decode($personnel->large_medical_insurance_config, true);
            $paymentCycle = $config['payment_cycle'] ?? 'monthly';
            
            // 兼容 'yearly' 和 'year' 两种格式
            if ($paymentCycle === 'yearly' || $paymentCycle === 'year') {
                // 年付模式：只有支付月份才生成金额
                return $this->isYearlyPaymentMonth($personnel, $year, $month);
            } else {
                // 月付模式：每月都生成金额
                return true;
            }
            
        } catch (\Exception $e) {
            Log::error('判断大额医疗保险支付月份失败', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 判断年付模式下的支付月份
     * 
     * @param InsurancePersonnel $personnel 参保人员记录
     * @param int $year 年份
     * @param int $month 月份
     * @return bool 是否为支付月份
     */
    private function isYearlyPaymentMonth($personnel, $year, $month)
    {
        // 如果没有设置支付起始月份，使用当前月份
        if (!$personnel->large_medical_payment_start_month || !$personnel->large_medical_payment_start_year) {
            return false;
        }
        
        $paymentStartMonth = $personnel->large_medical_payment_start_month;
        $paymentStartYear = $personnel->large_medical_payment_start_year;
        
        // 检查月份是否匹配
        if ($month != $paymentStartMonth) {
            return false;
        }
        
        // 检查年份差值
        $yearDiff = $year - $paymentStartYear;
        if ($yearDiff < 0) {
            return false;
        }
        
        // 第一年总是支付月份
        if ($yearDiff == 0) {
            return true;
        }
        
        // 后续年份，必须是整年（年付模式）
        return $yearDiff > 0;
    }
    
    /**
     * 计算大额医疗保险金额
     * 
     * @param int $employeeId 员工ID
     * @param int $year 年份
     * @param int $month 月份
     * @return array 包含公司缴纳和员工缴纳金额
     */
    public function calculateLargeMedicalAmount($employeeId, $year, $month)
    {
        try {
            // 获取员工的大额医疗保险配置
            $personnel = InsurancePersonnel::where('employee_id', $employeeId)->first();
            
            if (!$personnel || !$personnel->large_medical_insurance_enabled) {
                return [
                    'company_amount' => 0.00,
                    'employee_amount' => 0.00,
                    'total_amount' => 0.00
                ];
            }
            
            // 判断是否为支付月份
            if (!$this->isPaymentMonth($employeeId, $year, $month)) {
                return [
                    'company_amount' => 0.00,
                    'employee_amount' => 0.00,
                    'total_amount' => 0.00
                ];
            }
            
            // 解析配置
            $config = json_decode($personnel->large_medical_insurance_config, true);
            $calculationType = $config['calculation_type'] ?? 'base';
            
            if ($calculationType === 'fixed') {
                // 固定金额模式
                $companyAmount = floatval($config['company_amount'] ?? 0);
                $employeeAmount = floatval($config['employee_amount'] ?? 0);
            } else {
                // 按基数模式
                $baseAmount = floatval($personnel->employee_large_medical_base ?? 0);
                $companyRatio = floatval($config['company_ratio'] ?? 0);
                $employeeRatio = floatval($config['employee_ratio'] ?? 0);
                
                $companyAmount = $baseAmount * $companyRatio;
                $employeeAmount = $baseAmount * $employeeRatio;
            }
            
            return [
                'company_amount' => $companyAmount,
                'employee_amount' => $employeeAmount,
                'total_amount' => $companyAmount + $employeeAmount
            ];
            
        } catch (\Exception $e) {
            Log::error('计算大额医疗保险金额失败', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            
            return [
                'company_amount' => 0.00,
                'employee_amount' => 0.00,
                'total_amount' => 0.00
            ];
        }
    }
    
    /**
     * 设置支付起始时间
     * 
     * @param int $employeeId 员工ID
     * @param int $year 年份
     * @param int $month 月份
     * @return bool 是否设置成功
     */
    public function setPaymentStartTime($employeeId, $year, $month)
    {
        try {
            $personnel = InsurancePersonnel::where('employee_id', $employeeId)->first();
            
            if (!$personnel) {
                return false;
            }
            
            $personnel->update([
                'large_medical_payment_start_month' => $month,
                'large_medical_payment_start_year' => $year,
                'large_medical_last_payment_month' => $month,
                'large_medical_last_payment_year' => $year
            ]);
            
            Log::info('设置大额医疗保险支付起始时间', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('设置大额医疗保险支付起始时间失败', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 更新支付历史
     * 
     * @param int $employeeId 员工ID
     * @param int $year 年份
     * @param int $month 月份
     * @return bool 是否更新成功
     */
    public function updatePaymentHistory($employeeId, $year, $month)
    {
        try {
            $personnel = InsurancePersonnel::where('employee_id', $employeeId)->first();
            
            if (!$personnel) {
                return false;
            }
            
            $personnel->update([
                'large_medical_last_payment_month' => $month,
                'large_medical_last_payment_year' => $year
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('更新大额医疗保险支付历史失败', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 获取支付状态信息
     * 
     * @param int $employeeId 员工ID
     * @return array 支付状态信息
     */
    public function getPaymentStatus($employeeId)
    {
        try {
            $personnel = InsurancePersonnel::where('employee_id', $employeeId)->first();
            
            if (!$personnel) {
                return null;
            }
            
            $config = json_decode($personnel->large_medical_insurance_config, true);
            
            return [
                'enabled' => $personnel->large_medical_insurance_enabled,
                'payment_cycle' => $config['payment_cycle'] ?? 'monthly',
                'payment_cycle_text' => ($config['payment_cycle'] ?? 'monthly') === 'yearly' ? '年付' : '月付',
                'calculation_type' => $config['calculation_type'] ?? 'base',
                'calculation_type_text' => ($config['calculation_type'] ?? 'base') === 'fixed' ? '固定金额' : '按基数',
                'payment_start_month' => $personnel->large_medical_payment_start_month,
                'payment_start_year' => $personnel->large_medical_payment_start_year,
                'last_payment_month' => $personnel->large_medical_last_payment_month,
                'last_payment_year' => $personnel->large_medical_last_payment_year
            ];
            
        } catch (\Exception $e) {
            Log::error('获取大额医疗保险支付状态失败', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 生成支付月份列表
     * 
     * @param int $employeeId 员工ID
     * @param int $startYear 开始年份
     * @param int $endYear 结束年份
     * @return array 支付月份列表
     */
    public function generatePaymentMonths($employeeId, $startYear, $endYear)
    {
        try {
            $personnel = InsurancePersonnel::where('employee_id', $employeeId)->first();
            
            if (!$personnel || !$personnel->large_medical_insurance_enabled) {
                return [];
            }
            
            $config = json_decode($personnel->large_medical_insurance_config, true);
            $paymentCycle = $config['payment_cycle'] ?? 'monthly';
            
            $paymentMonths = [];
            
            if ($paymentCycle === 'yearly') {
                // 年付模式：只有支付月份
                $paymentStartMonth = $personnel->large_medical_payment_start_month;
                $paymentStartYear = $personnel->large_medical_payment_start_year;
                
                if ($paymentStartMonth && $paymentStartYear) {
                    for ($year = $startYear; $year <= $endYear; $year++) {
                        if ($year >= $paymentStartYear) {
                            $paymentMonths[] = [
                                'year' => $year,
                                'month' => $paymentStartMonth,
                                'is_payment_month' => true
                            ];
                        }
                    }
                }
            } else {
                // 月付模式：每月都是支付月份
                for ($year = $startYear; $year <= $endYear; $year++) {
                    for ($month = 1; $month <= 12; $month++) {
                        $paymentMonths[] = [
                            'year' => $year,
                            'month' => $month,
                            'is_payment_month' => true
                        ];
                    }
                }
            }
            
            return $paymentMonths;
            
        } catch (\Exception $e) {
            Log::error('生成大额医疗保险支付月份列表失败', [
                'employee_id' => $employeeId,
                'start_year' => $startYear,
                'end_year' => $endYear,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
