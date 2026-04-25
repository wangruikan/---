<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AssessmentRecord;
use Carbon\Carbon;

class AutoConfirmAssessmentRecords extends Command
{
    protected $signature = 'assessment:auto-confirm';
    protected $description = '每月15号后自动确认未完成且未申诉的考核记录';

    public function handle()
    {
        $today = Carbon::today();

        if ((int)$today->day < 15) {
            $this->info('今天未到15号，跳过自动确认');
            return 0;
        }

        $records = AssessmentRecord::with('latestAppeal')
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNull('actual_complete_date')
            ->get();

        $updatedCount = 0;

        foreach ($records as $record) {
            if ($record->latestAppeal) {
                continue;
            }

            $record->actual_complete_date = $today->copy()->endOfDay();
            $record->status = 'completed';
            $record->overdue_days = $record->calculateOverdueDays();
            $record->save();
            $updatedCount++;
        }

        $this->info("自动确认完成，更新 {$updatedCount} 条记录");

        return 0;
    }
}
