<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InsurancePersonnel;
use App\Models\InsuranceDetailRecord;
use Carbon\Carbon;

class BackfillMonthlyInsuranceDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insurance:backfill-monthly-details {year} {month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '将当前 insurance_personnel 表的数据保存为指定月份的归档记录（用于补充历史数据）';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        // 验证月份
        if ($month < 1 || $month > 12) {
            $this->error("月份必须在 1-12 之间");
            return Command::FAILURE;
        }

        $this->info("准备将当前参保人员数据保存为 {$year}年{$month}月 的归档记录...");
        
        // 询问确认（如果不是非交互模式）
        if (!$this->option('no-interaction')) {
            if (!$this->confirm("确认要将当前数据保存为 {$year}年{$month}月 的归档吗？")) {
                $this->info("操作已取消");
                return Command::SUCCESS;
            }
        }

        // 获取所有活跃的参保人员信息
        $personnelList = InsurancePersonnel::where('status', 'active')
            ->with(['employee', 'project'])
            ->get();

        $this->info("找到 " . $personnelList->count() . " 条参保人员信息");

        $successCount = 0;
        $errorCount = 0;
        $updatedCount = 0;

        foreach ($personnelList as $personnel) {
            try {
                // 检查是否已存在该月份的归档
                $existingRecord = InsuranceDetailRecord::where('employee_id', $personnel->employee_id)
                    ->where('project_id', $personnel->project_id)
                    ->where('account_set_id', $personnel->account_set_id)
                    ->where('record_year', $year)
                    ->where('record_month', $month)
                    ->first();

                if ($existingRecord) {
                    // 更新现有记录 - 强制保存所有其他保险数据
                    $this->updateRecordWithAllData($existingRecord, $personnel, $year, $month);
                    $updatedCount++;
                    $this->line("↻ 员工: {$personnel->employee_name} - 更新了 {$year}年{$month}月 的归档记录");
                } else {
                    // 创建新记录 - 强制保存所有其他保险数据
                    $this->createRecordWithAllData($personnel, $year, $month);
                    $successCount++;
                    $this->line("✓ 员工: {$personnel->employee_name} - 创建了 {$year}年{$month}月 的归档记录");
                }
            } catch (\Exception $e) {
                $this->error("✗ 员工: {$personnel->employee_name} - 保存失败: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->info("\n保存完成!");
        $this->info("新建: {$successCount} 条");
        $this->info("更新: {$updatedCount} 条");
        $this->info("失败: {$errorCount} 条");
        
        $this->newLine();
        $this->info("✓ 当前 insurance_personnel 表的数据已保存为 {$year}年{$month}月 的归档记录");
        $this->info("✓ 现在可以在前端选择 {$year}年{$month}月 查看这些数据了");

        return Command::SUCCESS;
    }

    /**
     * 创建归档记录（强制保存所有数据，包括所有其他保险）
     */
    private function createRecordWithAllData($personnel, $year, $month)
    {
        $record = InsuranceDetailRecord::create([
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
            'other_insurance_policies' => $personnel->other_insurance_policies, // 强制保存所有
            'large_medical_insurance_config' => $personnel->large_medical_insurance_config,
            'status' => 'generated',
            'generated_at' => now(),
        ]);

        // 计算金额
        $record->calculateAmounts();
        
        return $record;
    }

    /**
     * 更新归档记录（强制保存所有数据，包括所有其他保险）
     */
    private function updateRecordWithAllData($record, $personnel, $year, $month)
    {
        $record->update([
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
            'other_insurance_policies' => $personnel->other_insurance_policies, // 强制保存所有
            'large_medical_insurance_config' => $personnel->large_medical_insurance_config,
            'generated_at' => now(),
        ]);

        // 重新计算金额
        $record->calculateAmounts();
        
        return $record;
    }
}

