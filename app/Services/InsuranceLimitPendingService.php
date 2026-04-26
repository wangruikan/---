<?php

namespace App\Services;

use App\Models\InsuranceLimitPendingChange;
use Illuminate\Support\Facades\DB;

class InsuranceLimitPendingService
{
    public function savePendingChange(
        string $targetType,
        int $targetId,
        int $accountSetId,
        $minBase,
        $maxBase,
        string $effectiveDate,
        ?int $operatorId = null
    ): InsuranceLimitPendingChange {
        return DB::transaction(function () use ($targetType, $targetId, $accountSetId, $minBase, $maxBase, $effectiveDate, $operatorId) {
            $existing = InsuranceLimitPendingChange::where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->where('status', 'pending')
                ->first();

            if ($existing) {
                $existing->update([
                    'account_set_id' => $accountSetId,
                    'pending_min_base_amount' => $minBase,
                    'pending_max_base_amount' => $maxBase,
                    'effective_date' => $effectiveDate,
                    'created_by' => $operatorId,
                    'error_message' => null,
                ]);

                return $existing->fresh();
            }

            return InsuranceLimitPendingChange::create([
                'target_type' => $targetType,
                'target_id' => $targetId,
                'account_set_id' => $accountSetId,
                'pending_min_base_amount' => $minBase,
                'pending_max_base_amount' => $maxBase,
                'effective_date' => $effectiveDate,
                'status' => 'pending',
                'created_by' => $operatorId,
            ]);
        });
    }

    public function getPendingByTarget(string $targetType, int $targetId): ?InsuranceLimitPendingChange
    {
        return InsuranceLimitPendingChange::where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('status', 'pending')
            ->first();
    }

    public function cancelPendingChange(string $targetType, int $targetId): bool
    {
        $pending = $this->getPendingByTarget($targetType, $targetId);
        if (!$pending) {
            return false;
        }

        $pending->update(['status' => 'cancelled']);
        return true;
    }
}
