<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocumentDeliveryService;
use Illuminate\Support\Facades\Log;

class CheckPendingDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery:check-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查并提醒未交付的记录（每月月底执行）';

    protected $deliveryService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(DocumentDeliveryService $deliveryService)
    {
        parent::__construct();
        $this->deliveryService = $deliveryService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始检查未交付记录...');
        Log::info('定时任务：检查未交付记录开始');

        try {
            $this->deliveryService->checkAndRemindPending();
            
            $this->info('✓ 未交付检查完成');
            Log::info('定时任务：检查未交付记录完成');

            return 0;
        } catch (\Exception $e) {
            $this->error('检查失败: ' . $e->getMessage());
            Log::error('定时任务：检查未交付记录失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }
}

