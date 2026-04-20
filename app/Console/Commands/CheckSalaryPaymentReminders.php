<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckSalaryPaymentReminders extends Command
{
    protected $signature = 'salary:check-payment-reminders {--date=}';
    protected $description = '检查工资发放提醒（发放日期前一天提醒）';

    public function handle()
    {
        $this->info('开始检查工资发放提醒...');

        // 获取今天和明天的日期
        $today = $this->option('date') 
            ? Carbon::parse($this->option('date'))
            : Carbon::today();
        
        $tomorrow = $today->copy()->addDay();
        $tomorrowDay = $tomorrow->day;
        $year = $tomorrow->year;
        $month = $tomorrow->month;
        
        $this->info("今天：{$today->toDateString()}");
        $this->info("明天：{$tomorrow->toDateString()}（{$tomorrowDay}号）");

        // 获取所有账套
        $accountSets = \App\Models\AccountSet::all();

        foreach ($accountSets as $accountSet) {
            $this->info("处理账套: {$accountSet->name}");

            // 查找明天需要发放工资的项目
            $projects = Project::where('account_set_id', $accountSet->id)
                ->where('status', 'active')
                ->where('salary_payment_date', $tomorrowDay)
                ->get();

            if ($projects->count() === 0) {
                $this->info("  → 没有项目需要发放工资");
                continue;
            }

            $this->info("  → 找到 {$projects->count()} 个项目需要发放工资");

            // 检查是否已经有提醒
            $existingReminder = Notification::where('type', 'salary_payment_reminder')
                ->where('data', 'LIKE', '%"account_set_id":' . $accountSet->id . '%')
                ->where('data', 'LIKE', '%"year":"' . $year . '"%')
                ->where('data', 'LIKE', '%"month":"' . $month . '"%')
                ->where('data', 'LIKE', '%"day":"' . $tomorrowDay . '"%')
                ->where('is_read', false)
                ->exists();

            if ($existingReminder) {
                $this->info("  → 已有提醒，跳过");
                continue;
            }

            // 获取第一个审批节点的用户
            $users = User::whereHas('accountSets', function($query) use ($accountSet) {
                    $query->where('account_sets.id', $accountSet->id)
                          ->where('account_set_users.approval_level', 1);
                })
                ->get();

            if ($users->count() === 0) {
                $this->warn("  → 警告：没有找到第一审批节点的用户，跳过");
                continue;
            }

            $this->info("  → 找到 {$users->count()} 个第一审批节点用户");

            // 构建项目列表
            $projectNames = $projects->pluck('name')->toArray();
            $projectIds = $projects->pluck('id')->toArray();
            $projectList = implode('、', $projectNames);

            // 为每个用户创建提醒
            foreach ($users as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'salary_payment_reminder',
                    'title' => '工资发放提醒',
                    'content' => "以下项目将于{$tomorrow->format('Y年m月d日')}发放工资：{$projectList}",
                    'data' => [
                        'account_set_id' => $accountSet->id,
                        'year' => (string)$year,
                        'month' => (string)$month,
                        'day' => (string)$tomorrowDay,
                        'payment_date' => $tomorrow->toDateString(),
                        'project_ids' => $projectIds,
                        'project_names' => $projectNames,
                    ],
                    'is_read' => false,
                ]);
                $this->info("    - 为用户 {$user->name} 创建提醒");
            }

            $this->info("  ✓ 已为账套 {$accountSet->name} 创建 {$users->count()} 条工资发放提醒");
        }

        $this->info('检查完成！');
        return 0;
    }
}
