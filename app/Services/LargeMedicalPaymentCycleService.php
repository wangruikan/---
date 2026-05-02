<?php

namespace App\Services;

use App\Models\InsurancePersonnel;
use Illuminate\Support\Facades\Log;

/**
 * 大额医疗保险付款周期服务
 */
class LargeMedicalPaymentCycleService
{
    /**
     * 判断指定年月是否为该员工的大额医疗缴费月
     */
    public function isPaymentMonth($employeeId, $year, $month, $projectId = null, $accountSetId = null)
    {
        try {
            $personnel = $this->findCurrentPersonnel($employeeId, $projectId, $accountSetId);

            if (!$personnel || !$personnel->large_medical_insurance_enabled) {
                return false;
            }

            $config = json_decode($personnel->large_medical_insurance_config, true) ?: [];
            $paymentCycle = $this->normalizePaymentCycle($config['payment_cycle'] ?? null);

            if ($this->isYearlyCycle($paymentCycle)) {
                return $this->isYearlyPaymentMonth($personnel, (int) $year, (int) $month);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('判断大额医疗缴费月份失败', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 计算大额医疗金额
     */
    public function calculateLargeMedicalAmount($employeeId, $year, $month, $projectId = null, $accountSetId = null)
    {
        try {
            $personnel = $this->findCurrentPersonnel($employeeId, $projectId, $accountSetId);

            if (!$personnel || !$personnel->large_medical_insurance_enabled) {
                return [
                    'company_amount' => 0.00,
                    'employee_amount' => 0.00,
                    'total_amount' => 0.00,
                ];
            }

            if (!$this->isPaymentMonth($employeeId, $year, $month, $projectId, $accountSetId)) {
                return [
                    'company_amount' => 0.00,
                    'employee_amount' => 0.00,
                    'total_amount' => 0.00,
                ];
            }

            $config = json_decode($personnel->large_medical_insurance_config, true) ?: [];
            $calculationType = $config['calculation_type'] ?? 'base';

            if ($calculationType === 'fixed') {
                $companyAmount = (float) ($config['company_amount'] ?? 0);
                $employeeAmount = (float) ($config['employee_amount'] ?? 0);
            } else {
                $baseAmount = (float) ($personnel->employee_large_medical_base ?? 0);
                $companyRatio = (float) ($config['company_ratio'] ?? 0);
                $employeeRatio = (float) ($config['employee_ratio'] ?? 0);

                $companyAmount = $baseAmount * $companyRatio;
                $employeeAmount = $baseAmount * $employeeRatio;
            }

            return [
                'company_amount' => $companyAmount,
                'employee_amount' => $employeeAmount,
                'total_amount' => $companyAmount + $employeeAmount,
            ];
        } catch (\Exception $e) {
            Log::error('计算大额医疗金额失败', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage(),
            ]);

            return [
                'company_amount' => 0.00,
                'employee_amount' => 0.00,
                'total_amount' => 0.00,
            ];
        }
    }

    /**
     * 设置付款起始时间
     */
    public function setPaymentStartTime($employeeId, $year, $month, $projectId = null, $accountSetId = null)
    {
        try {
            $personnel = $this->findCurrentPersonnel($employeeId, $projectId, $accountSetId);

            if (!$personnel) {
                return false;
            }

            $personnel->update([
                'large_medical_payment_start_month' => (int) $month,
                'large_medical_payment_start_year' => (int) $year,
                'large_medical_last_payment_month' => (int) $month,
                'large_medical_last_payment_year' => (int) $year,
            ]);

            Log::info('设置大额医疗付款起始时间', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('设置大额医疗付款起始时间失败', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 更新付款历史
     */
    public function updatePaymentHistory($employeeId, $year, $month, $projectId = null, $accountSetId = null)
    {
        try {
            $personnel = $this->findCurrentPersonnel($employeeId, $projectId, $accountSetId);

            if (!$personnel) {
                return false;
            }

            $personnel->update([
                'large_medical_last_payment_month' => (int) $month,
                'large_medical_last_payment_year' => (int) $year,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('更新大额医疗付款历史失败', [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 获取付款状态
     */
    public function getPaymentStatus($employeeId, $projectId = null, $accountSetId = null)
    {
        try {
            $personnel = $this->findCurrentPersonnel($employeeId, $projectId, $accountSetId);

            if (!$personnel) {
                return null;
            }

            $config = json_decode($personnel->large_medical_insurance_config, true) ?: [];
            $paymentCycle = $this->normalizePaymentCycle($config['payment_cycle'] ?? null);

            return [
                'enabled' => $personnel->large_medical_insurance_enabled,
                'payment_cycle' => $paymentCycle,
                'payment_cycle_text' => $this->isYearlyCycle($paymentCycle) ? '按年' : '按月',
                'calculation_type' => $config['calculation_type'] ?? 'base',
                'calculation_type_text' => ($config['calculation_type'] ?? 'base') === 'fixed' ? '固定金额' : '按基数',
                'payment_start_month' => $personnel->large_medical_payment_start_month,
                'payment_start_year' => $personnel->large_medical_payment_start_year,
                'last_payment_month' => $personnel->large_medical_last_payment_month,
                'last_payment_year' => $personnel->large_medical_last_payment_year,
            ];
        } catch (\Exception $e) {
            Log::error('获取大额医疗付款状态失败', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 生成缴费月份列表
     */
    public function generatePaymentMonths($employeeId, $startYear, $endYear, $projectId = null, $accountSetId = null)
    {
        try {
            $personnel = $this->findCurrentPersonnel($employeeId, $projectId, $accountSetId);

            if (!$personnel || !$personnel->large_medical_insurance_enabled) {
                return [];
            }

            $config = json_decode($personnel->large_medical_insurance_config, true) ?: [];
            $paymentCycle = $this->normalizePaymentCycle($config['payment_cycle'] ?? null);

            $paymentMonths = [];

            if ($this->isYearlyCycle($paymentCycle)) {
                $paymentStartMonth = (int) $personnel->large_medical_payment_start_month;
                $paymentStartYear = (int) $personnel->large_medical_payment_start_year;

                if ($paymentStartMonth > 0 && $paymentStartYear > 0) {
                    for ($year = (int) $startYear; $year <= (int) $endYear; $year++) {
                        if ($year >= $paymentStartYear) {
                            $paymentMonths[] = [
                                'year' => $year,
                                'month' => $paymentStartMonth,
                                'is_payment_month' => true,
                            ];
                        }
                    }
                }
            } else {
                for ($year = (int) $startYear; $year <= (int) $endYear; $year++) {
                    for ($month = 1; $month <= 12; $month++) {
                        $paymentMonths[] = [
                            'year' => $year,
                            'month' => $month,
                            'is_payment_month' => true,
                        ];
                    }
                }
            }

            return $paymentMonths;
        } catch (\Exception $e) {
            Log::error('生成大额医疗缴费月份列表失败', [
                'employee_id' => $employeeId,
                'start_year' => $startYear,
                'end_year' => $endYear,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Resolve current personnel row.
     */
    private function findCurrentPersonnel($employeeId, $projectId = null, $accountSetId = null)
    {
        $query = InsurancePersonnel::where('employee_id', $employeeId);

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        if ($accountSetId !== null) {
            $query->where('account_set_id', $accountSetId);
        }

        if ($projectId !== null || $accountSetId !== null) {
            $query->where(function ($q) {
                $q->whereNull('is_compensation')->orWhere('is_compensation', 0);
            });
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id')->first();
    }

    private function isYearlyPaymentMonth($personnel, $year, $month)
    {
        $startMonth = (int) $personnel->large_medical_payment_start_month;
        $startYear = (int) $personnel->large_medical_payment_start_year;

        // 首次启用但未设置起始时间时，保持当前行为：按月可缴
        if ($startMonth <= 0 || $startYear <= 0) {
            return true;
        }

        if ((int) $month !== $startMonth) {
            return false;
        }

        return (int) $year >= $startYear;
    }

    private function normalizePaymentCycle($paymentCycle)
    {
        $cycle = strtolower(trim((string) $paymentCycle));

        if ($cycle === 'year' || $cycle === 'yearly') {
            return 'yearly';
        }

        if ($cycle === 'month' || $cycle === 'monthly') {
            return 'monthly';
        }

        return 'monthly';
    }

    private function isYearlyCycle($paymentCycle)
    {
        return $this->normalizePaymentCycle($paymentCycle) === 'yearly';
    }
}
