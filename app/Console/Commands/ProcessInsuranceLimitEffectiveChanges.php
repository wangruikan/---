<?php

namespace App\Console\Commands;

use App\Models\InsuranceLimitPendingChange;
use App\Models\SocialSecurityRegion;
use App\Models\MedicalInsuranceRegion;
use App\Models\HousingFundConfig;
use App\Services\BaseLimitCompensationService;
use App\Services\InsuranceChangeDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessInsuranceLimitEffectiveChanges extends Command
{
    protected $signature = 'insurance:process-limit-effective';

    protected $description = '处理社保/医保/公积金上下限待生效变更';

    public function handle()
    {
        $this->info('开始处理上下限待生效变更...');

        $pendingChanges = InsuranceLimitPendingChange::due()->get();
        if ($pendingChanges->isEmpty()) {
            $this->info('没有需要生效的上下限变更');
            return Command::SUCCESS;
        }

        $this->info("找到 {$pendingChanges->count()} 条待生效记录");

        $successCount = 0;
        $failedCount = 0;

        foreach ($pendingChanges as $pending) {
            try {
                DB::transaction(function () use ($pending) {
                    $this->applyPendingChange($pending);
                });

                $successCount++;
                $this->info("✓ 已生效 pending_id={$pending->id}");
            } catch (\Throwable $e) {
                $failedCount++;
                $pending->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                $this->error("✗ 生效失败 pending_id={$pending->id}: {$e->getMessage()}");
                Log::error('上下限待生效处理失败', [
                    'pending_id' => $pending->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('处理完成');
        $this->info("✓ 成功: {$successCount} 条");
        if ($failedCount > 0) {
            $this->error("✗ 失败: {$failedCount} 条");
        }

        return Command::SUCCESS;
    }

    private function applyPendingChange(InsuranceLimitPendingChange $pending): void
    {
        switch ($pending->target_type) {
            case 'social_security_region':
                $this->applySocialSecurityRegion($pending);
                break;
            case 'medical_insurance_region':
                $this->applyMedicalInsuranceRegion($pending);
                break;
            case 'housing_fund_config':
                $this->applyHousingFundConfig($pending);
                break;
            default:
                throw new \RuntimeException('未知 target_type: ' . $pending->target_type);
        }

        $pending->update([
            'status' => 'applied',
            'applied_at' => now(),
            'error_message' => null,
        ]);
    }

    private function applySocialSecurityRegion(InsuranceLimitPendingChange $pending): void
    {
        $region = SocialSecurityRegion::findOrFail($pending->target_id);
        $oldData = $region->toArray();
        $oldMin = $region->min_base_amount;
        $oldMax = $region->max_base_amount;

        $region->update([
            'min_base_amount' => $pending->pending_min_base_amount,
            'max_base_amount' => $pending->pending_max_base_amount,
        ]);

        $newMin = $region->min_base_amount;
        $newMax = $region->max_base_amount;

        if ((string)$oldMin !== (string)$newMin || (string)$oldMax !== (string)$newMax) {
            $compensationService = app(BaseLimitCompensationService::class);
            $compensationService->calculateSocialSecurityCompensation(
                $region->id,
                $oldMin,
                $oldMax,
                $newMin,
                $newMax
            );

            $detectionService = app(InsuranceChangeDetectionService::class);
            $detectionService->detectAndImport('social_security', $oldData, $region->toArray(), $region->id);
        }
    }

    private function applyMedicalInsuranceRegion(InsuranceLimitPendingChange $pending): void
    {
        $region = MedicalInsuranceRegion::findOrFail($pending->target_id);
        $oldData = $region->toArray();

        $region->update([
            'min_base_amount' => $pending->pending_min_base_amount,
            'max_base_amount' => $pending->pending_max_base_amount,
        ]);

        $detectionService = app(InsuranceChangeDetectionService::class);
        $detectionService->detectAndImport('medical_insurance', $oldData, $region->toArray(), $region->id);
    }

    private function applyHousingFundConfig(InsuranceLimitPendingChange $pending): void
    {
        $config = HousingFundConfig::findOrFail($pending->target_id);
        $oldData = $config->toArray();
        $oldMin = $config->min_base_amount;
        $oldMax = $config->max_base_amount;

        $config->update([
            'min_base_amount' => $pending->pending_min_base_amount,
            'max_base_amount' => $pending->pending_max_base_amount,
        ]);

        $newMin = $config->min_base_amount;
        $newMax = $config->max_base_amount;

        if ((string)$oldMin !== (string)$newMin || (string)$oldMax !== (string)$newMax) {
            $compensationService = app(BaseLimitCompensationService::class);
            $compensationService->calculateHousingFundCompensation(
                $config->id,
                $oldMin,
                $oldMax,
                $newMin,
                $newMax
            );

            $detectionService = app(InsuranceChangeDetectionService::class);
            $detectionService->detectAndImport('housing_fund', $oldData, $config->toArray(), $config->region_id);
        }
    }
}
