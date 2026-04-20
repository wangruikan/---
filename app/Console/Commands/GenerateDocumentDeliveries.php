<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocumentDeliveryService;
use Illuminate\Support\Facades\Log;

class GenerateDocumentDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery:generate 
                            {--type=all : Type of generation: monthly, quarterly, or all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成资料交付记录（每月1日执行）';

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
        $type = $this->option('type');

        $this->info('开始生成资料交付记录...');
        Log::info('定时任务：生成资料交付记录开始', ['type' => $type]);

        try {
            if ($type === 'monthly' || $type === 'all') {
                $this->info('生成月度交付记录...');
                $this->deliveryService->generateMonthlyDeliveries();
                $this->info('✓ 月度交付记录生成完成');
            }

            if ($type === 'quarterly' || $type === 'all') {
                $this->info('生成季度交付记录...');
                $this->deliveryService->generateQuarterlyDeliveries();
                $this->info('✓ 季度交付记录生成完成');
            }

            $this->info('所有交付记录生成完成！');
            Log::info('定时任务：生成资料交付记录完成');

            return 0;
        } catch (\Exception $e) {
            $this->error('生成失败: ' . $e->getMessage());
            Log::error('定时任务：生成资料交付记录失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }
}

