<?php

namespace App\Console\Commands;

use App\Models\BaseAdjustment;
use App\Models\LargeMedicalInsuranceConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEmployeeBaseAdjustments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'base:process-employee-adjustments {--date= : 指定处理日期，格式 YYYY-MM-DD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '处理员工基数调整，并应用到期的大额医疗待生效配置';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateOption = $this->option('date');
        try {
            if ($dateOption) {
                $targetDate = Carbon::createFromFormat('Y-m-d', $dateOption);
                if (!$targetDate || $targetDate->format('Y-m-d') !== $dateOption) {
                    throw new \InvalidArgumentException('invalid date');
                }
                $targetDate = $targetDate->startOfDay();
            } else {
                $targetDate = Carbon::today();
            }
        } catch (\Throwable $exception) {
            $this->error('日期格式错误，请使用 YYYY-MM-DD');
            return Command::FAILURE;
        }

        $targetDateString = $targetDate->toDateString();
        $this->info("开始处理员工基数调整... (日期: {$targetDateString})");

        $adjustments = BaseAdjustment::pending()
            ->effective($targetDateString)
            ->with(['employee'])
            ->get();

        if ($adjustments->isEmpty()) {
            $this->info('没有需要处理的调基记录');
            $this->processLargeMedicalConfigEffective($targetDate);
            return Command::SUCCESS;
        }

        $this->info("找到 {$adjustments->count()} 条待生效的调基记录");

        $successCount = 0;
        $failedCount = 0;

        foreach ($adjustments as $adjustment) {
            try {
                if ($adjustment->apply(null, false, $targetDate)) {
                    $successCount++;
                    $employeeName = optional($adjustment->employee)->name ?: '未知员工';
                    $this->info("✓ 员工 {$employeeName} (ID: {$adjustment->employee_id}) 的调基已生效");

                    Log::info('员工调基生效', [
                        'adjustment_id' => $adjustment->id,
                        'employee_id' => $adjustment->employee_id,
                        'employee_name' => $employeeName,
                        'social_security_base' => $adjustment->new_social_security_base,
                        'medical_insurance_base' => $adjustment->new_medical_insurance_base,
                        'housing_fund_base' => $adjustment->new_housing_fund_base,
                        'large_medical_base' => $adjustment->new_large_medical_base,
                        'target_date' => $targetDateString,
                    ]);
                } else {
                    $failedCount++;
                    $employeeName = optional($adjustment->employee)->name ?: '未知员工';
                    $this->error("✗ 员工 {$employeeName} (ID: {$adjustment->employee_id}) 的调基处理失败");
                }
            } catch (\Throwable $exception) {
                $failedCount++;
                $this->error("✗ 处理调基记录 ID:{$adjustment->id} 时发生错误: {$exception->getMessage()}");

                Log::error('员工调基处理失败', [
                    'adjustment_id' => $adjustment->id,
                    'employee_id' => $adjustment->employee_id,
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                    'target_date' => $targetDateString,
                ]);
            }
        }

        $this->info("\n处理完成:");
        $this->info("✓ 成功: {$successCount} 条");
        if ($failedCount > 0) {
            $this->error("✗ 失败: {$failedCount} 条");
        }

        if ($successCount > 0) {
            $this->info("\n正在自动计算补差...");
            $this->call('base:process-compensation', ['--date' => $targetDateString]);
        }

        $this->processLargeMedicalConfigEffective($targetDate);

        return Command::SUCCESS;
    }

    /**
     * 处理大额医疗保险配置的生效
     */
    private function processLargeMedicalConfigEffective(Carbon $targetDate): void
    {
        $targetDateString = $targetDate->toDateString();
        $this->info("\n开始处理大额医疗保险配置生效... (日期: {$targetDateString})");

        $configs = LargeMedicalInsuranceConfig::hasPending()
            ->whereDate('effective_date', '<=', $targetDateString)
            ->get();

        if ($configs->isEmpty()) {
            $this->info('没有需要生效的大额医疗保险配置');
            return;
        }

        $this->info("找到 {$configs->count()} 条待生效的配置");

        $successCount = 0;
        $failedCount = 0;

        foreach ($configs as $config) {
            try {
                if ($config->applyPendingChanges($targetDate)) {
                    $successCount++;
                    $this->info("✓ 配置 [{$config->region_name}] (ID: {$config->id}) 已生效");
                } else {
                    $failedCount++;
                    $this->error("✗ 配置 [{$config->region_name}] (ID: {$config->id}) 生效失败");
                }
            } catch (\Throwable $exception) {
                $failedCount++;
                $this->error("✗ 处理配置 ID:{$config->id} 时发生错误: {$exception->getMessage()}");

                Log::error('大额医疗保险配置生效失败', [
                    'config_id' => $config->id,
                    'region_name' => $config->region_name,
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                    'target_date' => $targetDateString,
                ]);
            }
        }

        $this->info("\n大额医疗保险配置处理完成:");
        $this->info("✓ 成功: {$successCount} 条");
        if ($failedCount > 0) {
            $this->error("✗ 失败: {$failedCount} 条");
        }
    }
}
