<?php

namespace App\Console\Commands;

use App\Models\PaymentRequest;
use App\Services\PendingTaskService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePaymentSupplementWindow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:expire-supplement-window';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '关闭超出72小时的付款申请候补资料入口，并完成对应待办';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredAt = now()->subHours(PaymentRequest::SUPPLEMENT_WINDOW_HOURS);

        $candidates = PaymentRequest::where('upload_later', 1)
            ->where('created_at', '<=', $expiredAt)
            ->get();

        $closedCount = 0;
        $completedTaskCount = 0;

        foreach ($candidates as $paymentRequest) {
            if (!$paymentRequest->supportsSupplementAttachment()) {
                continue;
            }

            $paymentRequest->update(['upload_later' => 0]);
            $closedCount++;

            $completedTaskCount += PendingTaskService::checkAndCompletePaymentSupplementTask($paymentRequest, true);
        }

        $this->info("候补入口关闭完成：{$closedCount} 条，完成待办：{$completedTaskCount} 条");

        Log::info('payment:expire-supplement-window 执行完成', [
            'closed_count' => $closedCount,
            'completed_task_count' => $completedTaskCount,
            'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
        ]);

        return Command::SUCCESS;
    }
}

