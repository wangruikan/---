<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckProjectEndReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:check-end-reminders {--days=30 : 提前多少天提醒}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查项目结束日期，给账套成员创建项目即将结束提醒事项';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 30;
        }

        $today = now()->startOfDay();
        $deadline = now()->addDays($days)->endOfDay();

        $projects = Project::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereDate('end_date', '<=', $deadline->toDateString())
            ->get();

        $this->info("扫描到 {$projects->count()} 个 {$days} 天内即将结束的项目");

        $createdCount = 0; // 通知条数
        $existsCount = 0;

        foreach ($projects as $project) {
            if (!$project->account_set_id) {
                continue;
            }

            $users = User::whereHas('accountSets', function ($query) use ($project) {
                    $query->where('account_sets.id', $project->account_set_id);
                })
                ->where('is_active', true)
                ->get();

            foreach ($users as $user) {
                $endDate = $project->end_date instanceof \Carbon\Carbon
                    ? $project->end_date->toDateString()
                    : (string) $project->end_date;

                $exists = Notification::where('user_id', $user->id)
                    ->where('type', 'project_end_reminder')
                    ->where('data', 'LIKE', '%"project_id":' . $project->id . '%')
                    ->where('data', 'LIKE', '%"end_date":"' . $endDate . '"%')
                    ->where('is_read', false)
                    ->exists();

                if ($exists) {
                    $existsCount++;
                    continue;
                }

                $daysLeft = max(0, now()->startOfDay()->diffInDays($project->end_date, false));
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'project_end_reminder',
                    'title' => '项目即将结束提醒',
                    'content' => "项目「{$project->name}」将于 {$endDate} 结束（剩余 {$daysLeft} 天），请提前安排。",
                    'data' => [
                        'account_set_id' => $project->account_set_id,
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'end_date' => $endDate,
                        'days_left' => $daysLeft,
                    ],
                    'is_read' => false,
                ]);

                $createdCount++;
            }
        }

        Log::info('项目即将结束提醒检查完成', [
            'days' => $days,
            'projects_count' => $projects->count(),
            'created_tasks' => $createdCount,
            'existing_tasks' => $existsCount,
        ]);

        $this->info("创建待办 {$createdCount} 条，已存在 {$existsCount} 条");

        return self::SUCCESS;
    }
}
