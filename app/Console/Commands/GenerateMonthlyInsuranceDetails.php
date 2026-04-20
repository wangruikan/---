<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InsurancePersonnel;
use App\Models\InsuranceDetailRecord;
use Carbon\Carbon;

class GenerateMonthlyInsuranceDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insurance:generate-monthly-details {--year=} {--month=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成指定月份的参保明细记录';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 获取参数，如果没有指定则使用当前月份
        // 因为在月末执行，保存的是当月的数据快照
        $year = $this->option('year') ?: Carbon::now()->year;
        $month = $this->option('month') ?: Carbon::now()->month;

        $this->info("开始生成 {$year}年{$month}月 的参保明细记录...");

        // 获取所有活跃的参保人员信息
        $personnelList = InsurancePersonnel::where('status', 'active')
            ->with(['employee', 'project'])
            ->get();

        $this->info("找到 " . $personnelList->count() . " 条参保人员信息");

        $successCount = 0;
        $errorCount = 0;

        foreach ($personnelList as $personnel) {
            try {
                // 生成明细记录
                $record = InsuranceDetailRecord::generateFromPersonnel($personnel, $year, $month);
                
                $this->line("✓ 员工: {$personnel->employee_name}, 项目: " . 
                    ($personnel->project ? $personnel->project->name : '无') . 
                    " - 明细记录已生成/更新");
                
                $successCount++;
            } catch (\Exception $e) {
                $this->error("✗ 员工: {$personnel->employee_name} - 生成失败: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->info("\n生成完成!");
        $this->info("成功: {$successCount} 条");
        $this->info("失败: {$errorCount} 条");

        return Command::SUCCESS;
    }
}
