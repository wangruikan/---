<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\InvoiceApplication;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckInvoiceReminders extends Command
{
    protected $signature = 'invoice:check-reminders {--account_set_id=} {--year=} {--month=}';
    protected $description = '检查未开票项目并发送提醒';

    public function handle()
    {
        $this->info('开始检查未开票项目...');

        $accountSetId = $this->option('account_set_id');
        $year = $this->option('year');
        $month = $this->option('month');

        // 获取所有账套或指定账套
        if ($accountSetId) {
            $accountSets = [\App\Models\AccountSet::find($accountSetId)];
        } else {
            $accountSets = \App\Models\AccountSet::all();
        }

        // 使用指定的年月，如果没有指定则使用当前年月
        $currentYear = $year ?: date('Y');
        $currentMonth = $month ?: date('n');
        
        $this->info("检查期间：{$currentYear}年{$currentMonth}月");

        foreach ($accountSets as $accountSet) {
            if (!$accountSet) {
                continue;
            }

            $this->info("处理账套: {$accountSet->name}");

            // 获取进行中的项目
            $projects = Project::where('account_set_id', $accountSet->id)
                ->where('status', 'active')
                ->get();

            $this->info("找到 {$projects->count()} 个进行中的项目");

            foreach ($projects as $project) {
                $this->info("检查项目: {$project->name} (状态: {$project->status})");
                // 检查本月是否有发票申请
                $hasInvoice = InvoiceApplication::where('account_set_id', $accountSet->id)
                    ->where('project_name', $project->name)
                    ->where('year', $currentYear)
                    ->where('month', $currentMonth)
                    ->exists();

                if ($hasInvoice) {
                    $this->info("  → 已开票，跳过");
                    // 已开票，删除之前的提醒（如果有）
                    Notification::where('type', 'invoice_reminder')
                        ->where('data', 'LIKE', '%"project_id":' . $project->id . '%')
                        ->where('data', 'LIKE', '%"year":"' . $currentYear . '"%')
                        ->where('data', 'LIKE', '%"month":"' . $currentMonth . '"%')
                        ->delete();
                    continue;
                }

                $this->info("  → 未开票");

                // 检查是否有任何人已提交未开票原因（不限用户）
                $hasReasonSubmitted = Notification::where('type', 'invoice_reason_submitted')
                    ->where('data', 'LIKE', '%"project_id":' . $project->id . '%')
                    ->where('data', 'LIKE', '%"year":"' . $currentYear . '"%')
                    ->where('data', 'LIKE', '%"month":"' . $currentMonth . '"%')
                    ->exists();

                if ($hasReasonSubmitted) {
                    $this->info("  → 已有人提交原因，跳过");
                    // 已有人提交原因，不再提醒任何人
                    continue;
                }

                // 获取有开票权限的用户（第2、3、4审批节点的审批人和管理员）
                $users = User::whereHas('accountSets', function($query) use ($accountSet) {
                        $query->where('account_sets.id', $accountSet->id);
                    })
                    ->where(function($query) {
                        $query->whereIn('role', ['admin', 'super_admin'])
                              ->orWhereHas('accountSets', function($q) {
                                  $q->whereIn('account_set_users.approval_level', [2, 3, 4]);
                              });
                    })
                    ->get();

                $this->info("  → 找到 {$users->count()} 个有开票权限的用户");

                if ($users->count() === 0) {
                    $this->warn("  → 警告：没有找到有开票权限的用户，跳过");
                    continue;
                }

                // 为每个用户创建提醒
                foreach ($users as $user) {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'invoice_reminder',
                        'title' => '未开票提醒',
                        'content' => "项目【{$project->name}】{$currentYear}年{$currentMonth}月未开票，请及时处理或填写未开票原因",
                        'data' => [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'year' => $currentYear,
                            'month' => $currentMonth,
                            'account_set_id' => $accountSet->id,
                        ],
                        'is_read' => false,
                    ]);
                    $this->info("    - 为用户 {$user->name} 创建提醒");
                }

                $this->info("  ✓ 已为项目 {$project->name} 创建 {$users->count()} 条未开票提醒");
            }
        }

        $this->info('检查完成！');
        return 0;
    }
}
