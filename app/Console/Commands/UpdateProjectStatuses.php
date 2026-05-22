<?php

namespace App\Console\Commands;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateProjectStatuses extends Command
{
    protected $signature = 'project:update-statuses';

    protected $description = 'Update project statuses by end date';

    public function handle()
    {
        $today = Carbon::today('Asia/Shanghai')->toDateString();

        $completedCount = Project::whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

        $activeCount = Project::whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->where('status', '!=', 'active')
            ->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);

        $this->info("Project statuses updated. completed={$completedCount}, active={$activeCount}");

        return self::SUCCESS;
    }
}
