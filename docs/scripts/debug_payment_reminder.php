<?php

/**
 * 调试社保公积金缴费提醒 - 查看为什么找不到未回执的付款申请
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PaymentRequest;
use App\Models\ProcessApproval;
use Carbon\Carbon;

echo "=== 调试社保公积金缴费提醒 ===\n\n";

// 1. 查看所有保险类型的付款申请
echo "1. 查看所有保险类型的付款申请\n";
echo "-------------------\n";

$allInsurancePayments = PaymentRequest::where('payment_type', 'insurance')
    ->with(['insuranceSummary'])
    ->get();

echo "找到 {$allInsurancePayments->count()} 条保险付款申请\n\n";

foreach ($allInsurancePayments as $payment) {
    echo "付款申请 ID: {$payment->id}\n";
    echo "  账套ID: {$payment->account_set_id}\n";
    echo "  状态: {$payment->status}\n";
    echo "  发票状态: {$payment->invoice_status}\n";
    echo "  月份: {$payment->month}\n";
    echo "  保险汇总ID: {$payment->insurance_summary_id}\n";
    
    if ($payment->insuranceSummary) {
        echo "  汇总标题: {$payment->insuranceSummary->title}\n";
        echo "  汇总类型: {$payment->insuranceSummary->category}\n";
        echo "  汇总月份: {$payment->insuranceSummary->month}\n";
    } else {
        echo "  ⚠️ 未找到关联的保险汇总\n";
    }
    echo "\n";
}

// 2. 查看符合条件的付款申请（已审批通过且未完成回执）
echo "2. 查看符合条件的付款申请\n";
echo "-------------------\n";

$pendingPayments = PaymentRequest::where('payment_type', 'insurance')
    ->where('status', 'approved')
    ->whereIn('invoice_status', ['pending_invoice', 'invoice_uploaded'])
    ->with(['insuranceSummary'])
    ->get();

echo "找到 {$pendingPayments->count()} 条符合条件的付款申请\n\n";

foreach ($pendingPayments as $payment) {
    echo "付款申请 ID: {$payment->id}\n";
    echo "  账套ID: {$payment->account_set_id}\n";
    echo "  状态: {$payment->status}\n";
    echo "  发票状态: {$payment->invoice_status}\n";
    
    if ($payment->insuranceSummary) {
        echo "  汇总标题: {$payment->insuranceSummary->title}\n";
        echo "  汇总类型: {$payment->insuranceSummary->category}\n";
        echo "  汇总月份: {$payment->insuranceSummary->month}\n";
    }
    echo "\n";
}

// 3. 测试查询逻辑（模拟定时任务的查询）
echo "3. 测试查询逻辑（2026年5月）\n";
echo "-------------------\n";

$accountSetId = 1;
$year = 2026;
$month = 5;

// 社保
echo "查询社保付款申请:\n";
$socialSecurityPayments = PaymentRequest::where('account_set_id', $accountSetId)
    ->where('payment_type', 'insurance')
    ->where('status', 'approved')
    ->whereIn('invoice_status', ['pending_invoice', 'invoice_uploaded'])
    ->whereHas('insuranceSummary', function($q) use ($year, $month) {
        $monthText = $year . '年' . $month . '月';
        $q->where('title', 'LIKE', "%{$monthText}%");
        
        $q->where(function($query) {
            $query->where('category', 'social_insurance')
                  ->orWhere('category', 'social_security')
                  ->orWhere('title', 'LIKE', '%社保%')
                  ->orWhere('title', 'LIKE', '%社会保险%');
        });
    })
    ->with(['insuranceSummary'])
    ->get();

echo "找到 {$socialSecurityPayments->count()} 条社保付款申请\n";
foreach ($socialSecurityPayments as $payment) {
    echo "  - ID: {$payment->id}, 标题: {$payment->insuranceSummary->title}\n";
}

// 公积金
echo "\n查询公积金付款申请:\n";
$housingFundPayments = PaymentRequest::where('account_set_id', $accountSetId)
    ->where('payment_type', 'insurance')
    ->where('status', 'approved')
    ->whereIn('invoice_status', ['pending_invoice', 'invoice_uploaded'])
    ->whereHas('insuranceSummary', function($q) use ($year, $month) {
        $monthText = $year . '年' . $month . '月';
        $q->where('title', 'LIKE', "%{$monthText}%");
        
        $q->where(function($query) {
            $query->where('category', 'housing_fund')
                  ->orWhere('title', 'LIKE', '%公积金%')
                  ->orWhere('title', 'LIKE', '%住房公积金%');
        });
    })
    ->with(['insuranceSummary'])
    ->get();

echo "找到 {$housingFundPayments->count()} 条公积金付款申请\n";
foreach ($housingFundPayments as $payment) {
    echo "  - ID: {$payment->id}, 标题: {$payment->insuranceSummary->title}\n";
}

// 4. 查看所有保险汇总
echo "\n4. 查看所有保险汇总\n";
echo "-------------------\n";

$allSummaries = ProcessApproval::all();
echo "找到 {$allSummaries->count()} 条保险汇总\n\n";

foreach ($allSummaries as $summary) {
    echo "汇总 ID: {$summary->id}\n";
    echo "  标题: {$summary->title}\n";
    echo "  类型: {$summary->category}\n";
    echo "  月份: {$summary->month}\n";
    echo "  状态: {$summary->status}\n";
    echo "\n";
}

echo "=== 调试完成 ===\n";
