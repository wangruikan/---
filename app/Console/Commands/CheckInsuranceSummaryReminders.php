<?php

namespace App\Console\Commands;

use App\Models\PaymentDueDateConfig;
use App\Models\ProcessApproval;
use App\Models\Notification;
use App\Models\User;
use App\Models\ApprovalRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckInsuranceSummaryReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insurance:check-summary-reminders 
                            {--date= : 指定检查日期(格式:Y-m-d),不指定则使用今天}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查社保汇总表审核提醒（缴费前一天必须完成审核）';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 获取指定日期或使用今天
        $dateOption = $this->option('date');
        
        if ($dateOption) {
            try {
                $today = Carbon::parse($dateOption);
                $this->info("使用指定日期: {$today->toDateString()}");
            } catch (\Exception $e) {
                $this->error("日期格式错误: {$dateOption}");
                $this->error("请使用格式: Y-m-d (例如: 2026-03-14)");
                return 1;
            }
        } else {
            $today = Carbon::now();
            $this->info("使用当前日期: {$today->toDateString()}");
        }
        
        $this->info('开始检查社保汇总表审核提醒...');
        
        // 计算明天的日期（缴费日期）
        $tomorrow = $today->copy()->addDay();
        $tomorrowYear = $tomorrow->year;
        $tomorrowMonth = $tomorrow->month;
        $tomorrowDay = $tomorrow->day;
        
        $this->info("检查明天 {$tomorrow->toDateString()} 是否有缴费");
        
        // 获取所有账套的缴费配置
        $dueDateConfigs = PaymentDueDateConfig::where('month', $tomorrowMonth)
            ->where('due_day', $tomorrowDay)
            ->get();
        
        if ($dueDateConfigs->isEmpty()) {
            $this->info('明天没有配置缴费日期');
            return 0;
        }
        
        $this->info("找到 {$dueDateConfigs->count()} 个账套明天需要缴费");
        
        // 按账套分组处理
        foreach ($dueDateConfigs as $config) {
            $accountSetId = $config->account_set_id;
            $paymentType = $config->payment_type;
            $paymentTypeText = $paymentType === 'social_security' ? '社保' : '公积金';
            
            $this->info("检查账套 #{$accountSetId} 的{$paymentTypeText}汇总审核");
            
            // 查找该账套、该月份、该类型的未审批完成的保险汇总申请
            $pendingSummaries = $this->findPendingSummaries(
                $accountSetId,
                $paymentType,
                $tomorrowYear,
                $tomorrowMonth
            );
            
            if ($pendingSummaries->isEmpty()) {
                $this->info("没有未审批完成的{$paymentTypeText}汇总申请");
                continue;
            }
            
            $this->info("找到 {$pendingSummaries->count()} 条未审批完成的{$paymentTypeText}汇总申请");
            
            // 发送提醒（合并成一条消息）
            $this->sendCombinedReminder(
                $accountSetId,
                $paymentType,
                $pendingSummaries,
                $tomorrow,
                $today
            );
        }
        
        $this->info('社保汇总表审核提醒检查完成');
        return 0;
    }

    /**
     * 查找未审批完成的保险汇总申请
     */
    private function findPendingSummaries($accountSetId, $paymentType, $year, $month)
    {
        // 确定类别
        $category = $paymentType === 'social_security' ? 'social_insurance' : 'housing_fund';
        
        // 月份格式：Y-m
        $monthValue = sprintf('%04d-%02d', $year, $month);
        
        // 查找该账套、该月份、该类型的保险汇总申请
        $query = ProcessApproval::where('account_set_id', $accountSetId)
            ->where('category', $category)
            ->where('month', $monthValue)
            ->where('status', '!=', 'draft')  // 排除草稿状态
            ->with(['approvalInstance', 'initiator']);
        
        // 筛选未审批完成的（status不是approved）
        $query->whereHas('approvalInstance', function($q) {
            $q->where('status', '!=', 'approved');
        });
        
        return $query->get();
    }

    /**
     * 发送合并提醒（多个未审批的汇总申请合并成一条消息）
     */
    private function sendCombinedReminder($accountSetId, $paymentType, $pendingSummaries, $dueDate, $today)
    {
        $paymentTypeText = $paymentType === 'social_security' ? '社保' : '公积金';
        $dueDateText = $dueDate->format('Y年m月d日');
        
        // 获取第一审批节点的用户
        $firstApproverIds = $this->getFirstApprovers($accountSetId);
        
        if (empty($firstApproverIds)) {
            $this->warn("账套 #{$accountSetId} 没有找到第一审批节点的用户");
            return;
        }
        
        // 检查是否已经发送过提醒（避免重复）
        $existingNotification = Notification::where('type', 'insurance_summary_reminder')
            ->where('is_read', false)
            ->where('data', 'LIKE', '%"account_set_id":' . $accountSetId . '%')
            ->where('data', 'LIKE', '%"payment_type":"' . $paymentType . '"%')
            ->where('data', 'LIKE', '%"year":"' . $dueDate->year . '"%')
            ->where('data', 'LIKE', '%"month":"' . $dueDate->month . '"%')
            ->where('created_at', '>=', $today->startOfDay())
            ->first();
        
        if ($existingNotification) {
            $this->info("今天已发送过提醒，跳过");
            return;
        }
        
        // 构建提醒内容
        $summaryTitles = $pendingSummaries->pluck('title')->toArray();
        $summaryList = implode('、', $summaryTitles);
        
        $title = "{$paymentTypeText}汇总审核提醒";
        $content = "以下{$paymentTypeText}汇总申请需要在缴费日期（{$dueDateText}）前完成审核：{$summaryList}，请及时处理。";
        
        // 为每个第一审批节点的用户创建通知
        foreach ($firstApproverIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => 'insurance_summary_reminder',
                'title' => $title,
                'content' => $content,
                'is_read' => false,
                'data' => json_encode([
                    'account_set_id' => $accountSetId,
                    'payment_type' => $paymentType,
                    'year' => (string)$dueDate->year,
                    'month' => (string)$dueDate->month,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'summary_ids' => $pendingSummaries->pluck('id')->toArray(),
                ]),
            ]);
            
            $this->info("已发送提醒给用户 #{$userId}");
        }
    }

    /**
     * 获取第一审批节点的用户
     */
    private function getFirstApprovers($accountSetId)
    {
        // 查找该账套下第一审批节点（approval_level = 1）的用户
        // approval_level 在 account_set_users 中间表中
        $users = User::whereHas('accountSets', function($query) use ($accountSetId) {
                $query->where('account_sets.id', $accountSetId)
                      ->where('account_set_users.approval_level', 1);
            })
            ->pluck('id')
            ->toArray();
        
        return $users;
    }
}
