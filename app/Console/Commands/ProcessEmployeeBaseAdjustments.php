<?php

namespace App\Console\Commands;

use App\Models\BaseAdjustment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEmployeeBaseAdjustments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'base:process-employee-adjustments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '处理员工个人的基数调整，将到达生效时间的调基记录应用到员工档案和参保人员表';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始处理员工基数调整...');
        
        // 查找所有待生效且已到达生效时间的调基记录
        $adjustments = BaseAdjustment::pending()
            ->effective()
            ->with(['employee'])
            ->get();
        
        if ($adjustments->isEmpty()) {
            $this->info('没有需要处理的调基记录');
            // 继续处理大额医疗保险配置
            $this->processLargeMedicalConfigEffective();
            return Command::SUCCESS;
        }
        
        $this->info("找到 {$adjustments->count()} 条待生效的调基记录");
        
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($adjustments as $adjustment) {
            try {
                if ($adjustment->apply()) {
                    $successCount++;
                    $this->info("✓ 员工 {$adjustment->employee->name} (ID: {$adjustment->employee_id}) 的调基已生效");
                    
                    Log::info('员工调基生效', [
                        'adjustment_id' => $adjustment->id,
                        'employee_id' => $adjustment->employee_id,
                        'employee_name' => $adjustment->employee->name,
                        'social_security_base' => $adjustment->new_social_security_base,
                        'medical_insurance_base' => $adjustment->new_medical_insurance_base,
                        'housing_fund_base' => $adjustment->new_housing_fund_base,
                        'large_medical_base' => $adjustment->new_large_medical_base,
                    ]);
                } else {
                    $failedCount++;
                    $this->error("✗ 员工 {$adjustment->employee->name} (ID: {$adjustment->employee_id}) 的调基处理失败");
                }
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ 处理调基记录 ID:{$adjustment->id} 时发生错误: {$e->getMessage()}");
                
                Log::error('员工调基处理失败', [
                    'adjustment_id' => $adjustment->id,
                    'employee_id' => $adjustment->employee_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        $this->info("\n处理完成:");
        $this->info("✓ 成功: {$successCount} 条");
        if ($failedCount > 0) {
            $this->error("✗ 失败: {$failedCount} 条");
        }
        
        // 自动触发补差计算
        if ($successCount > 0) {
            $this->info("\n正在自动计算补差...");
            $this->call('base:process-compensation');
        }
        
        // 处理大额医疗保险配置的生效
        $this->processLargeMedicalConfigEffective();
        
        return Command::SUCCESS;
    }
    
    /**
     * 处理大额医疗保险配置的生效
     */
    private function processLargeMedicalConfigEffective(): void
    {
        $this->info("\n开始处理大额医疗保险配置生效...");
        
        $configs = \App\Models\LargeMedicalInsuranceConfig::effectiveNow()->get();
        
        if ($configs->isEmpty()) {
            $this->info('没有需要生效的大额医疗保险配置');
            return;
        }
        
        $this->info("找到 {$configs->count()} 条待生效的配置");
        
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($configs as $config) {
            try {
                if ($config->applyPendingChanges()) {
                    $successCount++;
                    $this->info("✓ 配置 [{$config->region_name}] (ID: {$config->id}) 已生效");
                } else {
                    $failedCount++;
                    $this->error("✗ 配置 [{$config->region_name}] (ID: {$config->id}) 生效失败");
                }
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ 处理配置 ID:{$config->id} 时发生错误: {$e->getMessage()}");
                
                Log::error('大额医疗保险配置生效失败', [
                    'config_id' => $config->id,
                    'region_name' => $config->region_name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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

