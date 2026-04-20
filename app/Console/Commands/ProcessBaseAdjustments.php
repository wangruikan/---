<?php

namespace App\Console\Commands;

use App\Models\SocialSecurityRegion;
use App\Models\HousingFund;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessBaseAdjustments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'base:adjust';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '处理社保和公积金的基数调整，将到期的调整基数应用到实际基数';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始处理基数调整...');
        
        $processedCount = 0;
        
        // 处理社保基数调整
        $socialSecurityRegions = SocialSecurityRegion::whereNotNull('adjustment_base')
            ->whereNotNull('effective_date')
            ->where('effective_date', '<=', now()->toDateString())
            ->get();
            
        foreach ($socialSecurityRegions as $region) {
            if ($region->applyAdjustment()) {
                $processedCount++;
                $this->info("社保地区 {$region->name} 的基数调整已生效");
                Log::info("社保基数调整生效", [
                    'region_id' => $region->id,
                    'region_name' => $region->name,
                    'effective_date' => $region->effective_date
                ]);
            }
        }
        
        // 处理公积金基数调整
        $housingFunds = HousingFund::whereNotNull('adjustment_base')
            ->whereNotNull('effective_date')
            ->where('effective_date', '<=', now()->toDateString())
            ->get();
            
        foreach ($housingFunds as $fund) {
            if ($fund->applyAdjustment()) {
                $processedCount++;
                $this->info("公积金地区 {$fund->region_name} 的基数调整已生效");
                Log::info("公积金基数调整生效", [
                    'fund_id' => $fund->id,
                    'region_name' => $fund->region_name,
                    'effective_date' => $fund->effective_date
                ]);
            }
        }
        
        $this->info("基数调整处理完成，共处理 {$processedCount} 条记录");
        
        return Command::SUCCESS;
    }
}
