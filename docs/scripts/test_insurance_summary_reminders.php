<?php

/**
 * 测试社保汇总表审核提醒功能
 * 
 * 使用方法：
 * php docs/scripts/test_insurance_summary_reminders.php --date=2026-03-14
 * 
 * 说明：
 * - --date 参数表示"今天"的日期，系统会检查"明天"是否有缴费
 * - 例如：--date=2026-03-14 表示今天是14号，检查15号是否有缴费
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PaymentDueDateConfig;
use App\Models\ProcessApproval;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

// 解析命令行参数
$options = getopt('', ['date:']);
$dateOption = $options['date'] ?? null;

if ($dateOption) {
    try {
        $today = Carbon::parse($dateOption);
        echo "使用指定日期: {$today->toDateString()}\n";
    } catch (\Exception $e) {
        echo "日期格式错误: {$dateOption}\n";
        echo "请使用格式: Y-m-d (例如: 2026-03-14)\n";
        exit(1);
    }
} else {
    $today = Carbon::now();
    echo "使用当前日期: {$today->toDateString()}\n";
}

echo "\n=== 测试社保汇总表审核提醒功能 ===\n\n";

// 计算明天的日期（缴费日期）
$tomorrow = $today->copy()->addDay();
$tomorrowYear = $tomorrow->year;
$tomorrowMonth = $tomorrow->month;
$tomorrowDay = $tomorrow->day;

echo "检查明天 {$tomorrow->toDateString()} 是否有缴费\n\n";

// 1. 查找明天是否有缴费配置
echo "1. 查找缴费配置\n";
$dueDateConfigs = PaymentDueDateConfig::where('month', $tomorrowMonth)
    ->where('due_day', $tomorrowDay)
    ->get();

if ($dueDateConfigs->isEmpty()) {
    echo "   ✓ 明天没有配置缴费日期\n";
    exit(0);
}

echo "   ✓ 找到 {$dueDateConfigs->count()} 个账套明天需要缴费\n\n";

// 2. 遍历每个账套
foreach ($dueDateConfigs as $config) {
    $accountSetId = $config->account_set_id;
    $paymentType = $config->payment_type;
    $paymentTypeText = $paymentType === 'social_security' ? '社保' : '公积金';
    
    echo "2. 检查账套 #{$accountSetId} 的{$paymentTypeText}汇总审核\n";
    
    // 确定类别
    $category = $paymentType === 'social_security' ? 'social_insurance' : 'housing_fund';
    
    // 月份格式：Y-m
    $monthValue = sprintf('%04d-%02d', $tomorrowYear, $tomorrowMonth);
    
    echo "   - 查找月份: {$monthValue}\n";
    echo "   - 类别: {$category}\n";
    
    // 查找该账套、该月份、该类型的保险汇总申请
    $allSummaries = ProcessApproval::where('account_set_id', $accountSetId)
        ->where('category', $category)
        ->where('month', $monthValue)
        ->where('status', '!=', 'draft')  // 排除草稿状态
        ->with(['approvalInstance', 'initiator'])
        ->get();
    
    echo "   - 找到 {$allSummaries->count()} 条{$paymentTypeText}汇总申请\n";
    
    if ($allSummaries->isEmpty()) {
        echo "   ✓ 没有{$paymentTypeText}汇总申请\n\n";
        continue;
    }
    
    // 显示所有汇总申请的状态
    foreach ($allSummaries as $summary) {
        $status = $summary->approvalInstance ? $summary->approvalInstance->status : 'no_instance';
        echo "   - 汇总申请 #{$summary->id}: {$summary->title} - 状态: {$status}\n";
    }
    
    // 筛选未审批完成的
    $pendingSummaries = $allSummaries->filter(function($summary) {
        return $summary->approvalInstance && $summary->approvalInstance->status !== 'approved';
    });
    
    echo "   - 未审批完成: {$pendingSummaries->count()} 条\n";
    
    if ($pendingSummaries->isEmpty()) {
        echo "   ✓ 所有{$paymentTypeText}汇总申请已审批完成\n\n";
        continue;
    }
    
    echo "   ⚠ 需要提醒审批\n\n";
    
    // 3. 查找第一审批节点的用户
    echo "3. 查找第一审批节点的用户\n";
    $firstApprovers = User::whereHas('accountSets', function($query) use ($accountSetId) {
            $query->where('account_sets.id', $accountSetId)
                  ->where('account_set_users.approval_level', 1);
        })
        ->get();
    
    if ($firstApprovers->isEmpty()) {
        echo "   ✗ 没有找到第一审批节点的用户\n\n";
        continue;
    }
    
    echo "   ✓ 找到 {$firstApprovers->count()} 个第一审批节点的用户\n";
    foreach ($firstApprovers as $user) {
        echo "   - 用户 #{$user->id}: {$user->name}\n";
    }
    echo "\n";
    
    // 4. 检查是否已经发送过提醒
    echo "4. 检查是否已经发送过提醒\n";
    $existingNotification = Notification::where('type', 'insurance_summary_reminder')
        ->where('is_read', false)
        ->where('data', 'LIKE', '%"account_set_id":' . $accountSetId . '%')
        ->where('data', 'LIKE', '%"payment_type":"' . $paymentType . '"%')
        ->where('data', 'LIKE', '%"year":"' . $tomorrowYear . '"%')
        ->where('data', 'LIKE', '%"month":"' . $tomorrowMonth . '"%')
        ->where('created_at', '>=', $today->startOfDay())
        ->first();
    
    if ($existingNotification) {
        echo "   ✓ 今天已发送过提醒（通知 #{$existingNotification->id}）\n";
        echo "   - 发送给用户: #{$existingNotification->user_id}\n";
        echo "   - 标题: {$existingNotification->title}\n";
        echo "   - 内容: {$existingNotification->content}\n\n";
        continue;
    }
    
    echo "   ✓ 今天还没有发送过提醒\n\n";
    
    // 5. 显示将要发送的提醒内容
    echo "5. 将要发送的提醒内容\n";
    $summaryTitles = $pendingSummaries->pluck('title')->toArray();
    $summaryList = implode('、', $summaryTitles);
    $dueDateText = $tomorrow->format('Y年m月d日');
    
    $title = "{$paymentTypeText}汇总审核提醒";
    $content = "以下{$paymentTypeText}汇总申请需要在缴费日期（{$dueDateText}）前完成审核：{$summaryList}，请及时处理。";
    
    echo "   标题: {$title}\n";
    echo "   内容: {$content}\n";
    echo "   发送给: {$firstApprovers->count()} 个用户\n\n";
}

echo "=== 测试完成 ===\n";
