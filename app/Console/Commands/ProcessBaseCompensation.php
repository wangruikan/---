<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaseAdjustment;
use App\Models\InsurancePersonnel;
use App\Models\InsuranceCompensationRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBaseCompensation extends Command
{
    protected $signature = 'base:process-compensation {--date= : 指定处理日期，格式 YYYY-MM-DD}';
    protected $description = '处理社保基数补差计算';
    private Carbon $referenceDate;

    public function handle()
    {
        $this->info('开始处理基数补差...');
        $dateOption = $this->option('date');
        try {
            if ($dateOption) {
                $today = Carbon::createFromFormat('Y-m-d', $dateOption);
                if (!$today || $today->format('Y-m-d') !== $dateOption) {
                    throw new \InvalidArgumentException('invalid date');
                }
                $today = $today->startOfDay();
            } else {
                $today = Carbon::today();
            }
        } catch (\Throwable $exception) {
            $this->error('日期格式错误，请使用 YYYY-MM-DD');
            return Command::FAILURE;
        }
        $this->referenceDate = $today->copy();

        $this->info('处理基数补差日期: ' . $today->toDateString());
        
        try {
            // 查找所有已生效的基数调整记录（已到达生效日期的）
            $adjustments = BaseAdjustment::where('status', 'applied')
                ->where(function($query) use ($today) {
                    $query->where(function($q) use ($today) {
                        $q->whereNotNull('social_security_effective_date')
                          ->where('social_security_effective_date', '<=', $today);
                    })
                    ->orWhere(function($q) use ($today) {
                        $q->whereNotNull('medical_insurance_effective_date')
                          ->where('medical_insurance_effective_date', '<=', $today);
                    })
                    ->orWhere(function($q) use ($today) {
                        $q->whereNotNull('housing_fund_effective_date')
                          ->where('housing_fund_effective_date', '<=', $today);
                    });
                })
                ->get();

            $processedCount = 0;
            
            foreach ($adjustments as $adjustment) {
                $employee = Employee::find($adjustment->employee_id);
                if (!$employee) continue;

                // 获取当前参保人员记录
                $personnel = InsurancePersonnel::where('employee_id', $employee->id)
                    ->where('account_set_id', $adjustment->account_set_id)
                    ->first();
                
                if (!$personnel) continue;

                // 处理社保补差（检查生效日期）
                if ($adjustment->new_social_security_base 
                    && $adjustment->social_security_effective_date
                    && Carbon::parse($adjustment->social_security_effective_date) <= $today) {
                    $this->processSocialSecurityCompensation($adjustment, $employee, $personnel);
                    $processedCount++;
                }

                // 处理医保补差（检查生效日期）
                if ($adjustment->new_medical_insurance_base 
                    && $adjustment->medical_insurance_effective_date
                    && Carbon::parse($adjustment->medical_insurance_effective_date) <= $today) {
                    $this->processMedicalInsuranceCompensation($adjustment, $employee, $personnel);
                    $processedCount++;
                }
                
                // 处理公积金补差（检查生效日期）
                if ($adjustment->new_housing_fund_base 
                    && $adjustment->housing_fund_effective_date
                    && Carbon::parse($adjustment->housing_fund_effective_date) <= $today) {
                    $this->processHousingFundCompensation($adjustment, $employee, $personnel);
                    $processedCount++;
                }
            }

            $this->info("补差处理完成！共处理 {$processedCount} 条记录");
            Log::info('基数补差处理完成', ['processed_count' => $processedCount]);
            
        } catch (\Exception $e) {
            $this->error('处理补差时出错: ' . $e->getMessage());
            Log::error('基数补差处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 处理社保补差
     */
    private function processSocialSecurityCompensation($adjustment, $employee, $personnel)
    {
        // 获取旧基数的生效时间（查找上一次的基数调整记录）
        // 关键：按生效日期比较，不按ID比较（因为可能后创建的记录先生效）
        $oldAdjustment = BaseAdjustment::where('employee_id', $employee->id)
            ->whereNotNull('social_security_effective_date')
            ->where('social_security_effective_date', '<', $adjustment->social_security_effective_date)
            ->where('status', 'applied')
            ->orderBy('social_security_effective_date', 'desc')
            ->first();

        // 获取原始基数
        $oldBaseOriginal = $oldAdjustment ? $oldAdjustment->new_social_security_base : $employee->social_security_base;
        $newBaseOriginal = $adjustment->new_social_security_base;
        
        // ✅ 关键修改：使用各自调基记录中保存的历史上下限
        // 旧基数使用旧调基记录的上下限
        $oldMinBase = $oldAdjustment ? $oldAdjustment->social_security_min_base : null;
        $oldMaxBase = $oldAdjustment ? $oldAdjustment->social_security_max_base : null;
        
        // 新基数使用新调基记录的上下限
        $newMinBase = $adjustment->social_security_min_base;
        $newMaxBase = $adjustment->social_security_max_base;
        
        // 如果调基记录中没有保存上下限，则从当前地区配置读取
        if (($oldMinBase === null || $oldMaxBase === null || $newMinBase === null || $newMaxBase === null) && $employee->social_security_region_id) {
            $socialRegion = \App\Models\SocialSecurityRegion::find($employee->social_security_region_id);
            if ($socialRegion) {
                $oldMinBase = $oldMinBase ?? $socialRegion->min_base_amount;
                $oldMaxBase = $oldMaxBase ?? $socialRegion->max_base_amount;
                $newMinBase = $newMinBase ?? $socialRegion->min_base_amount;
                $newMaxBase = $newMaxBase ?? $socialRegion->max_base_amount;
            }
        }
        
        // 应用各自的上下限约束
        $oldBase = $this->applyBaseLimits($oldBaseOriginal, $oldMinBase, $oldMaxBase);
        $newBase = $this->applyBaseLimits($newBaseOriginal, $newMinBase, $newMaxBase);
        
        // 如果约束后的新旧基数相同，不需要补差
        if ($oldBase == $newBase) {
            $this->info("员工 {$employee->name} 的社保基数约束后相同（旧:{$oldBaseOriginal}→{$oldBase}, 新:{$newBaseOriginal}→{$newBase}），无需补差");
            return;
        }
        
        $this->info("员工 {$employee->name} 社保补差基数：旧{$oldBaseOriginal}→{$oldBase}（约束后）, 新{$newBaseOriginal}→{$newBase}（约束后）, 差额:" . ($newBase - $oldBase));

        // ✅ 计算补差月数：只补本年度（从新生效日期所在年的1月到当前月前一个月）
        $newEffectiveDate = Carbon::parse($adjustment->social_security_effective_date);
        $compensationYear = $newEffectiveDate->year;  // 使用新生效日期的年份
        $currentMonth = $this->referenceDate->copy();
        
        // 补差起始月：取 [新生效日期所在年的1月, 旧生效日期] 的较大值
        $oldEffectiveDate = $oldAdjustment 
            ? Carbon::parse($oldAdjustment->social_security_effective_date)
            : Carbon::parse($employee->insurance_start_date ?? $this->referenceDate->copy()->subMonths(1));
        
        $yearStartMonth = Carbon::parse("{$compensationYear}-01-01");
        $startMonth = $oldEffectiveDate->greaterThan($yearStartMonth) ? $oldEffectiveDate : $yearStartMonth;
        
        // 补差结束月：取 [当前月-1, 新生效日期-1] 的较小值
        $currentMonthMinusOne = $currentMonth->copy()->subMonth();
        $newEffectiveDateMinusOne = $newEffectiveDate->copy()->subMonth();
        $endMonth = $newEffectiveDateMinusOne->lessThan($currentMonthMinusOne) ? $newEffectiveDateMinusOne : $currentMonthMinusOne;
        
        // 如果起始月晚于或等于结束月，不需要补差
        if ($startMonth->greaterThanOrEqualTo($endMonth)) {
            $this->info("员工 {$employee->name} 的社保补差期间无效（起始:{$startMonth->format('Y-m')}, 结束:{$endMonth->format('Y-m')}），跳过");
            return;
        }
        
        // 计算补差月数：包含起始月和结束月
        $compensationMonths = ($endMonth->year - $startMonth->year) * 12 + ($endMonth->month - $startMonth->month) + 1;

        // 计算补差金额
        $socialSecurityTypes = json_decode($personnel->social_security_types, true) ?? [];
        $compensatedTypes = [];
        
        foreach ($socialSecurityTypes as $type) {
            // 安全获取费率，支持多种可能的字段名
            // 注意：数据库中存储的是小数形式(如0.16表示16%)，不需要再除以100
            $companyRate = floatval($type['company_ratio'] ?? $type['company_rate'] ?? $type['companyRate'] ?? $type['unit_rate'] ?? 0);
            $personalRate = floatval($type['employee_ratio'] ?? $type['personal_rate'] ?? $type['personalRate'] ?? $type['employee_rate'] ?? 0);
            
            // 计算补差金额：(新基数 - 旧基数) × 费率 × 补差月数
            $companyCompensation = ($newBase - $oldBase) * $companyRate * $compensationMonths;
            $personalCompensation = ($newBase - $oldBase) * $personalRate * $compensationMonths;
            
            // 获取保险名称
            $insuranceName = $type['name'] ?? $type['type_name'] ?? '未知险种';
            
            // ✅ 工伤保险：四舍五入保留2位小数
            // 其他保险：直接截断保留2位小数（不四舍五入）
            if (strpos($insuranceName, '工伤') !== false || strpos($insuranceName, '工伤保险') !== false) {
                // 工伤保险：四舍五入
                $companyAmount = round($companyCompensation, 2);
                $personalAmount = round($personalCompensation, 2);
            } else {
                // 其他保险：直接截断（向下取整到2位小数）
                $companyAmount = floor($companyCompensation * 100) / 100;
                $personalAmount = floor($personalCompensation * 100) / 100;
            }
            
            $compensatedTypes[] = [
                'name' => $insuranceName,
                'company_base' => $newBase,
                'personal_base' => $newBase,
                'company_rate' => $companyRate * 100, // 转换为百分比显示
                'personal_rate' => $personalRate * 100, // 转换为百分比显示
                'company_amount' => $companyAmount,
                'personal_amount' => $personalAmount
            ];
        }

        // 创建补差记录
        // 注意：由于 insurance_personnel 表有唯一索引，我们需要先检查是否已存在相同的补差记录
        // 如果存在则更新，不存在则创建
        $compensationData = [
            'account_set_id' => $adjustment->account_set_id,
            'project_id' => $personnel->project_id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_id_number' => $employee->id_number,
            'employee_gender' => $employee->gender === 'male' ? 1 : 0,
            'employee_birth_date' => $employee->birth_date,
            'employee_phone' => $employee->phone,
            'employee_status' => $employee->status,
            'social_security_region_id' => $personnel->social_security_region_id,
            'is_compensation' => 1,
            'compensation_months' => $compensationMonths,
            'compensation_start_month' => $startMonth->format('Y-m'),
            'compensation_end_month' => $endMonth->format('Y-m'),
            'old_base' => $oldBase,
            'new_base' => $newBase,
            'employee_social_security_base' => $newBase,
            'social_security_types' => json_encode($compensatedTypes),
            'status' => 'active',
            'updated_at' => now()
        ];
        
        try {
            // 查找是否已存在相同时段的补差记录（在 insurance_personnel 表中）
            $existing = InsurancePersonnel::where('employee_id', $employee->id)
                ->where('project_id', $personnel->project_id)
                ->where('account_set_id', $adjustment->account_set_id)
                ->where('is_compensation', 1)
                ->where('compensation_start_month', $startMonth->format('Y-m'))
                ->where('compensation_end_month', $endMonth->format('Y-m'))
                ->first();
            
            if ($existing) {
                // 已存在相同的补差记录，更新
                $existing->update($compensationData);
                $this->info("已更新员工 {$employee->name} 的社保补差记录：{$compensationMonths}个月，旧基数{$oldBase}，新基数{$newBase}");
            } else {
                // 不存在补差记录，创建新的补差记录到 insurance_personnel 表
                $compensationData['created_at'] = now();
                InsurancePersonnel::create($compensationData);
                $this->info("已为员工 {$employee->name} 生成社保补差记录：{$compensationMonths}个月，旧基数{$oldBase}，新基数{$newBase}");
            }
            
            Log::info('社保补差记录处理完成', [
                'employee' => $employee->name,
                'months' => $compensationMonths,
                'old_base' => $oldBase,
                'new_base' => $newBase
            ]);
        } catch (\Exception $e) {
            // 如果失败，记录错误并继续
            \Log::error('创建社保补差记录失败', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("创建社保补差记录失败: " . $e->getMessage());
            // 不再抛出异常，继续处理下一条记录
            return;
        }
    }

    /**
     * 处理医保补差
     */
    private function processMedicalInsuranceCompensation($adjustment, $employee, $personnel)
    {
        // 医保补差逻辑与社保类似
        // 关键：按生效日期比较，不按ID比较
        $oldAdjustment = BaseAdjustment::where('employee_id', $employee->id)
            ->whereNotNull('medical_insurance_effective_date')
            ->where('medical_insurance_effective_date', '<', $adjustment->medical_insurance_effective_date)
            ->where('status', 'applied')
            ->orderBy('medical_insurance_effective_date', 'desc')
            ->first();

        // 获取原始基数
        $oldBaseOriginal = $oldAdjustment ? $oldAdjustment->new_medical_insurance_base : $employee->medical_insurance_base;
        $newBaseOriginal = $adjustment->new_medical_insurance_base;
        
        // ✅ 关键修改：使用各自调基记录中保存的历史上下限
        // 旧基数使用旧调基记录的上下限
        $oldMinBase = $oldAdjustment ? $oldAdjustment->medical_insurance_min_base : null;
        $oldMaxBase = $oldAdjustment ? $oldAdjustment->medical_insurance_max_base : null;
        
        // 新基数使用新调基记录的上下限
        $newMinBase = $adjustment->medical_insurance_min_base;
        $newMaxBase = $adjustment->medical_insurance_max_base;
        
        // 如果调基记录中没有保存上下限，则从当前地区配置读取
        if (($oldMinBase === null || $oldMaxBase === null || $newMinBase === null || $newMaxBase === null) && $employee->medical_insurance_region_id) {
            $medicalRegion = \App\Models\MedicalInsuranceRegion::find($employee->medical_insurance_region_id);
            if ($medicalRegion) {
                $oldMinBase = $oldMinBase ?? $medicalRegion->min_base_amount;
                $oldMaxBase = $oldMaxBase ?? $medicalRegion->max_base_amount;
                $newMinBase = $newMinBase ?? $medicalRegion->min_base_amount;
                $newMaxBase = $newMaxBase ?? $medicalRegion->max_base_amount;
            }
        }
        
        // 应用各自的上下限约束
        $oldBase = $this->applyBaseLimits($oldBaseOriginal, $oldMinBase, $oldMaxBase);
        $newBase = $this->applyBaseLimits($newBaseOriginal, $newMinBase, $newMaxBase);
        
        // 如果约束后的新旧基数相同，不需要补差
        if ($oldBase == $newBase) {
            $this->info("员工 {$employee->name} 的医保基数约束后相同（旧:{$oldBaseOriginal}→{$oldBase}, 新:{$newBaseOriginal}→{$newBase}），无需补差");
            return;
        }
        
        $this->info("员工 {$employee->name} 医保补差基数：旧{$oldBaseOriginal}→{$oldBase}（约束后）, 新{$newBaseOriginal}→{$newBase}（约束后）, 差额:" . ($newBase - $oldBase));

        // ✅ 计算补差月数：只补本年度（从新生效日期所在年的1月到当前月前一个月）
        $newEffectiveDate = Carbon::parse($adjustment->medical_insurance_effective_date);
        $compensationYear = $newEffectiveDate->year;  // 使用新生效日期的年份
        $currentMonth = $this->referenceDate->copy();
        
        $oldEffectiveDate = $oldAdjustment 
            ? Carbon::parse($oldAdjustment->medical_insurance_effective_date)
            : Carbon::parse($employee->insurance_start_date ?? $this->referenceDate->copy()->subMonths(1));
        
        $yearStartMonth = Carbon::parse("{$compensationYear}-01-01");
        $startMonth = $oldEffectiveDate->greaterThan($yearStartMonth) ? $oldEffectiveDate : $yearStartMonth;
        
        $currentMonthMinusOne = $currentMonth->copy()->subMonth();
        $newEffectiveDateMinusOne = $newEffectiveDate->copy()->subMonth();
        $endMonth = $newEffectiveDateMinusOne->lessThan($currentMonthMinusOne) ? $newEffectiveDateMinusOne : $currentMonthMinusOne;
        
        if ($startMonth->greaterThanOrEqualTo($endMonth)) {
            $this->info("员工 {$employee->name} 的医保补差期间无效（起始:{$startMonth->format('Y-m')}, 结束:{$endMonth->format('Y-m')}），跳过");
            return;
        }
        
        // 计算补差月数：包含起始月和结束月
        $compensationMonths = ($endMonth->year - $startMonth->year) * 12 + ($endMonth->month - $startMonth->month) + 1;

        // 计算医保补差金额
        $medicalTypes = json_decode($personnel->medical_insurance_types, true) ?? [];
        $compensatedTypes = [];
        
        foreach ($medicalTypes as $type) {
            // 安全获取费率，支持多种可能的字段名
            // 注意：数据库中存储的是小数形式(如0.16表示16%)，不需要再除以100
            $companyRate = floatval($type['company_ratio'] ?? $type['company_rate'] ?? $type['companyRate'] ?? $type['unit_rate'] ?? 0);
            $personalRate = floatval($type['employee_ratio'] ?? $type['personal_rate'] ?? $type['personalRate'] ?? $type['employee_rate'] ?? 0);
            
            // 计算补差金额：(新基数 - 旧基数) × 费率 × 补差月数
            $companyCompensation = ($newBase - $oldBase) * $companyRate * $compensationMonths;
            $personalCompensation = ($newBase - $oldBase) * $personalRate * $compensationMonths;
            
            // 获取保险名称
            $insuranceName = $type['name'] ?? $type['type_name'] ?? '未知险种';
            
            // ✅ 工伤保险：四舍五入保留2位小数
            // 其他保险：直接截断保留2位小数（不四舍五入）
            if (strpos($insuranceName, '工伤') !== false || strpos($insuranceName, '工伤保险') !== false) {
                // 工伤保险：四舍五入
                $companyAmount = round($companyCompensation, 2);
                $personalAmount = round($personalCompensation, 2);
            } else {
                // 其他保险：直接截断（向下取整到2位小数）
                $companyAmount = floor($companyCompensation * 100) / 100;
                $personalAmount = floor($personalCompensation * 100) / 100;
            }
            
            $compensatedTypes[] = [
                'name' => $insuranceName,
                'company_base' => $newBase,
                'personal_base' => $newBase,
                'company_rate' => $companyRate * 100, // 转换为百分比显示
                'personal_rate' => $personalRate * 100, // 转换为百分比显示
                'company_amount' => $companyAmount,
                'personal_amount' => $personalAmount
            ];
        }

        // 创建医保补差记录
        $compensationData = [
            'account_set_id' => $adjustment->account_set_id,
            'project_id' => $personnel->project_id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_id_number' => $employee->id_number,
            'employee_gender' => $employee->gender === 'male' ? 1 : 0,
            'employee_birth_date' => $employee->birth_date,
            'employee_phone' => $employee->phone,
            'employee_status' => $employee->status,
            'medical_insurance_region_id' => $personnel->medical_insurance_region_id,
            'is_compensation' => 1,
            'compensation_months' => $compensationMonths,
            'compensation_start_month' => $startMonth->format('Y-m'),
            'compensation_end_month' => $endMonth->format('Y-m'),
            'old_base' => $oldBase,
            'new_base' => $newBase,
            'employee_medical_insurance_base' => $newBase,
            'medical_insurance_types' => json_encode($compensatedTypes),
            'status' => 'active',
            'updated_at' => now()
        ];
        
        try {
            // 查找是否已存在相同时段的医保补差记录（在 insurance_personnel 表中）
            $existing = InsurancePersonnel::where('employee_id', $employee->id)
                ->where('project_id', $personnel->project_id)
                ->where('account_set_id', $adjustment->account_set_id)
                ->where('is_compensation', 1)
                ->where('compensation_start_month', $startMonth->format('Y-m'))
                ->where('compensation_end_month', $endMonth->format('Y-m'))
                ->first();
            
            if ($existing) {
                // 已存在相同的医保补差记录，更新
                $existing->update($compensationData);
                $this->info("已更新员工 {$employee->name} 的医保补差记录：{$compensationMonths}个月");
            } else {
                // 不存在医保补差记录，创建新的补差记录到 insurance_personnel 表
                $compensationData['created_at'] = now();
                InsurancePersonnel::create($compensationData);
                $this->info("已为员工 {$employee->name} 生成医保补差记录：{$compensationMonths}个月");
            }
        } catch (\Exception $e) {
            \Log::error('创建医保补差记录失败', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("创建医保补差记录失败: " . $e->getMessage());
            // 不再抛出异常，继续处理下一条记录
            return;
        }
    }

    /**
     * 处理公积金补差
     */
    private function processHousingFundCompensation($adjustment, $employee, $personnel)
    {
        // 公积金补差逻辑与社保类似
        // 关键：按生效日期比较，不按ID比较
        $oldAdjustment = BaseAdjustment::where('employee_id', $employee->id)
            ->whereNotNull('housing_fund_effective_date')
            ->where('housing_fund_effective_date', '<', $adjustment->housing_fund_effective_date)
            ->where('status', 'applied')
            ->orderBy('housing_fund_effective_date', 'desc')
            ->first();

        // 获取原始基数
        $oldBaseOriginal = $oldAdjustment ? $oldAdjustment->new_housing_fund_base : $employee->housing_fund_base;
        $newBaseOriginal = $adjustment->new_housing_fund_base;
        
        // 使用各自调基记录中保存的历史上下限
        $oldMinBase = $oldAdjustment ? $oldAdjustment->housing_fund_min_base : null;
        $oldMaxBase = $oldAdjustment ? $oldAdjustment->housing_fund_max_base : null;
        
        $newMinBase = $adjustment->housing_fund_min_base;
        $newMaxBase = $adjustment->housing_fund_max_base;
        
        // 如果调基记录中没有保存上下限，则从当前地区配置读取（暂时用社保的）
        if (($oldMinBase === null || $oldMaxBase === null || $newMinBase === null || $newMaxBase === null) && $employee->social_security_region_id) {
            $socialRegion = \App\Models\SocialSecurityRegion::find($employee->social_security_region_id);
            if ($socialRegion) {
                $oldMinBase = $oldMinBase ?? $socialRegion->min_base_amount;
                $oldMaxBase = $oldMaxBase ?? $socialRegion->max_base_amount;
                $newMinBase = $newMinBase ?? $socialRegion->min_base_amount;
                $newMaxBase = $newMaxBase ?? $socialRegion->max_base_amount;
            }
        }
        
        // 应用各自的上下限约束
        $oldBase = $this->applyBaseLimits($oldBaseOriginal, $oldMinBase, $oldMaxBase);
        $newBase = $this->applyBaseLimits($newBaseOriginal, $newMinBase, $newMaxBase);
        
        // 如果约束后的新旧基数相同，不需要补差
        if ($oldBase == $newBase) {
            $this->info("员工 {$employee->name} 的公积金基数约束后相同（旧:{$oldBaseOriginal}→{$oldBase}, 新:{$newBaseOriginal}→{$newBase}），无需补差");
            return;
        }
        
        $this->info("员工 {$employee->name} 公积金补差基数：旧{$oldBaseOriginal}→{$oldBase}（约束后）, 新{$newBaseOriginal}→{$newBase}（约束后）, 差额:" . ($newBase - $oldBase));

        // ✅ 计算补差月数：只补本年度（从新生效日期所在年的1月到当前月前一个月）
        $newEffectiveDate = Carbon::parse($adjustment->housing_fund_effective_date);
        $compensationYear = $newEffectiveDate->year;  // 使用新生效日期的年份
        $currentMonth = $this->referenceDate->copy();
        
        $oldEffectiveDate = $oldAdjustment 
            ? Carbon::parse($oldAdjustment->housing_fund_effective_date)
            : Carbon::parse($employee->insurance_start_date ?? $this->referenceDate->copy()->subMonths(1));
        
        $yearStartMonth = Carbon::parse("{$compensationYear}-01-01");
        $startMonth = $oldEffectiveDate->greaterThan($yearStartMonth) ? $oldEffectiveDate : $yearStartMonth;
        
        $currentMonthMinusOne = $currentMonth->copy()->subMonth();
        $newEffectiveDateMinusOne = $newEffectiveDate->copy()->subMonth();
        $endMonth = $newEffectiveDateMinusOne->lessThan($currentMonthMinusOne) ? $newEffectiveDateMinusOne : $currentMonthMinusOne;
        
        if ($startMonth->greaterThanOrEqualTo($endMonth)) {
            $this->info("员工 {$employee->name} 的公积金补差期间无效（起始:{$startMonth->format('Y-m')}, 结束:{$endMonth->format('Y-m')}），跳过");
            return;
        }
        
        // 计算补差月数：包含起始月和结束月
        $compensationMonths = ($endMonth->year - $startMonth->year) * 12 + ($endMonth->month - $startMonth->month) + 1;

        // 计算公积金补差金额（公积金一般是公司和个人各12%）
        $companyRate = 0.12; // 公司比例
        $personalRate = 0.12; // 个人比例
        
        // 计算补差金额：(新基数 - 旧基数) × 费率 × 补差月数
        $companyCompensation = ($newBase - $oldBase) * $companyRate * $compensationMonths;
        $personalCompensation = ($newBase - $oldBase) * $personalRate * $compensationMonths;
        
        // 公积金截断到2位小数
        $companyAmount = floor($companyCompensation * 100) / 100;
        $personalAmount = floor($personalCompensation * 100) / 100;
        
        $compensatedDetails = [
            [
                'name' => '住房公积金',
                'company_base' => $newBase,
                'personal_base' => $newBase,
                'company_rate' => $companyRate * 100,
                'personal_rate' => $personalRate * 100,
                'company_amount' => $companyAmount,
                'personal_amount' => $personalAmount
            ]
        ];

        // 创建公积金补差记录数据
        $compensationData = [
            'account_set_id' => $adjustment->account_set_id,
            'project_id' => $personnel->project_id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_id_number' => $employee->id_number,
            'employee_gender' => $employee->gender === 'male' ? 1 : 0,
            'employee_birth_date' => $employee->birth_date,
            'employee_phone' => $employee->phone,
            'employee_status' => $employee->status,
            'housing_fund_region_id' => $personnel->housing_fund_region_id,
            'housing_fund_config_id' => $personnel->housing_fund_config_id,
            'is_compensation' => 1,
            'compensation_months' => $compensationMonths,
            'compensation_start_month' => $startMonth->format('Y-m'),
            'compensation_end_month' => $endMonth->format('Y-m'),
            'old_base' => $oldBase,
            'new_base' => $newBase,
            'employee_housing_fund_base' => $newBase,
            'housing_fund_params' => json_encode($compensatedDetails),
            'status' => 'active',
            'updated_at' => now()
        ];

        try {
            // 查找是否已存在相同时段的公积金补差记录（在 insurance_personnel 表中）
            $existing = InsurancePersonnel::where('employee_id', $employee->id)
                ->where('project_id', $personnel->project_id)
                ->where('account_set_id', $adjustment->account_set_id)
                ->where('is_compensation', 1)
                ->where('compensation_start_month', $startMonth->format('Y-m'))
                ->where('compensation_end_month', $endMonth->format('Y-m'))
                ->first();
            
            if ($existing) {
                $existing->update($compensationData);
                $this->info("已更新员工 {$employee->name} 的公积金补差记录：{$compensationMonths}个月");
            } else {
                $compensationData['created_at'] = now();
                InsurancePersonnel::create($compensationData);
                $this->info("已为员工 {$employee->name} 生成公积金补差记录：{$compensationMonths}个月");
            }
        } catch (\Exception $e) {
            \Log::error('创建公积金补差记录失败', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("创建公积金补差记录失败: " . $e->getMessage());
            return;
        }
    }

    /**
     * 应用基数上下限约束
     * 
     * @param float $baseAmount 原始基数
     * @param float|null $minLimit 最低基数限制
     * @param float|null $maxLimit 最高基数限制
     * @return float 约束后的基数
     */
    private function applyBaseLimits($baseAmount, $minLimit, $maxLimit)
    {
        // 如果基数为0或负数，直接返回
        if ($baseAmount <= 0) {
            return $baseAmount;
        }

        // 应用下限约束
        if ($minLimit !== null && $baseAmount < $minLimit) {
            return $minLimit;
        }

        // 应用上限约束
        if ($maxLimit !== null && $baseAmount > $maxLimit) {
            return $maxLimit;
        }

        // 在上下限范围内，返回原值
        return $baseAmount;
    }
}
