<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

class RecalculateRetirementDates extends Command
{
    protected $signature = 'employees:recalculate-retirement-dates';
    protected $description = '根据2025年延迟退休政策重新计算所有员工的退休日期';

    public function handle()
    {
        $this->info('开始重新计算员工退休日期...');

        $employees = Employee::whereNotNull('birth_date')
            ->whereNotNull('gender')
            ->where('is_retired', false)
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            $newDate = $employee->calculateRetirementDate();
            if ($newDate) {
                $oldDate = $employee->retirement_date?->format('Y-m-d') ?? '无';
                $employee->retirement_date = $newDate;
                $employee->saveQuietly(); // 静默保存，不触发事件
                
                $this->line("员工 {$employee->name}: {$oldDate} → {$newDate->format('Y-m-d')}");
                $count++;
            }
        }

        $this->info("完成！共更新 {$count} 名员工的退休日期。");
        return 0;
    }
}
