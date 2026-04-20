<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\LargeMedicalInsuranceConfig;

class DebugLargeMedicalInsurance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:large-medical-insurance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '调试大额医疗保险配置问题';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=== 开始调试大额医疗保险配置 ===");
        
        // 1. 检查大额医疗保险配置表
        $configs = LargeMedicalInsuranceConfig::all();
        $this->info("大额医疗保险配置总数: " . $configs->count());
        
        foreach ($configs as $config) {
            $this->line("配置 #{$config->id}: {$config->region_name} - 账套ID: {$config->account_set_id}");
        }
        
        // 2. 检查项目
        $projects = Project::all();
        $this->info("\n项目总数: " . $projects->count());
        
        foreach ($projects as $project) {
            $this->info("\n=== 项目: {$project->name} (ID: {$project->id}) ===");
            
            // 检查项目的大额医疗保险配置
            try {
                $projectConfigs = $project->largeMedicalInsuranceConfigs()->get();
                $this->line("绑定的大额医疗保险配置数量: " . $projectConfigs->count());
                
                foreach ($projectConfigs as $config) {
                    $this->line("  - 配置: {$config->region_name} (ID: {$config->id})");
                }
                
                if ($projectConfigs->count() === 0) {
                    $this->warn("  ❌ 该项目没有绑定大额医疗保险配置！");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ 获取项目大额医疗保险配置失败: " . $e->getMessage());
            }
        }
        
        $this->info("\n=== 调试完成 ===");
    }
}
