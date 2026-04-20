<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PaymentRequest;
use App\Models\PaymentSummary;
use Illuminate\Support\Facades\DB;

echo "=== 付款流程完整性检查 ===\n\n";

try {
    // 1. 检查最近的付款申请记录
    echo "1. 检查最近的付款申请记录\n";
    echo str_repeat('-', 60) . "\n";
    
    $recentRequests = PaymentRequest::orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    if ($recentRequests->isEmpty()) {
        echo "⚠️  没有找到付款申请记录\n\n";
    } else {
        foreach ($recentRequests as $request) {
            echo sprintf("ID: %d | 类型: %s | 状态: %s | 创建时间: %s\n",
                $request->id,
                $request->payment_type,
                $request->status,
                $request->created_at->format('Y-m-d H:i:s')
            );
            
            // 检查付款表单字段
            $fields = [
                'apply_date' => $request->apply_date,
                'unit_name' => $request->unit_name,
                'invoice_number' => $request->invoice_number,
                'payment_date' => $request->payment_date,
                'summary' => $request->summary,
                'invoice_amount' => $request->invoice_amount,
                'invoice_type' => $request->invoice_type,
                'reimburser' => $request->reimburser,
                'tax_amount' => $request->tax_amount,
                'tax_rate' => $request->tax_rate,
                'deduction_amount' => $request->deduction_amount,
                'amount_excluding_tax' => $request->amount_excluding_tax,
            ];
            
            $filledCount = 0;
            foreach ($fields as $field => $value) {
                if (!empty($value)) {
                    $filledCount++;
                }
            }
            
            echo "  付款表单字段填写: {$filledCount}/12\n";
            
            if ($filledCount > 0) {
                echo "  已填写字段:\n";
                foreach ($fields as $field => $value) {
                    if (!empty($value)) {
                        $displayValue = is_string($value) && strlen($value) > 30 
                            ? substr($value, 0, 30) . '...' 
                            : $value;
                        echo "    - {$field}: {$displayValue}\n";
                    }
                }
            }
            
            echo "\n";
        }
    }
    
    // 2. 检查最近的出款汇总记录
    echo "\n2. 检查最近的出款汇总记录\n";
    echo str_repeat('-', 60) . "\n";
    
    $recentSummaries = PaymentSummary::orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    if ($recentSummaries->isEmpty()) {
        echo "⚠️  没有找到出款汇总记录\n\n";
    } else {
        foreach ($recentSummaries as $summary) {
            echo sprintf("ID: %d | 类型: %s | 类目: %s | 月份: %s | 创建时间: %s\n",
                $summary->id,
                $summary->payment_type,
                $summary->category ?? '未设置',
                $summary->month,
                $summary->created_at->format('Y-m-d H:i:s')
            );
            
            // 检查付款表单字段
            $fields = [
                'apply_date' => $summary->apply_date,
                'unit_name' => $summary->unit_name,
                'invoice_number' => $summary->invoice_number,
                'payment_date' => $summary->payment_date,
                'summary' => $summary->summary,
                'invoice_amount' => $summary->invoice_amount,
                'invoice_type' => $summary->invoice_type,
                'reimburser' => $summary->reimburser,
                'tax_amount' => $summary->tax_amount,
                'tax_rate' => $summary->tax_rate,
                'deduction_amount' => $summary->deduction_amount,
                'amount_excluding_tax' => $summary->amount_excluding_tax,
            ];
            
            $filledCount = 0;
            foreach ($fields as $field => $value) {
                if (!empty($value)) {
                    $filledCount++;
                }
            }
            
            echo "  付款表单字段填写: {$filledCount}/12\n";
            
            if ($filledCount > 0) {
                echo "  已填写字段:\n";
                foreach ($fields as $field => $value) {
                    if (!empty($value)) {
                        $displayValue = is_string($value) && strlen($value) > 30 
                            ? substr($value, 0, 30) . '...' 
                            : $value;
                        echo "    - {$field}: {$displayValue}\n";
                    }
                }
            }
            
            echo "\n";
        }
    }
    
    // 3. 检查字段映射完整性
    echo "\n3. 检查字段映射完整性\n";
    echo str_repeat('-', 60) . "\n";
    
    $requiredFields = [
        'apply_date' => '申请日期',
        'unit_name' => '单位名称',
        'invoice_number' => '发票号码',
        'payment_date' => '打款日期',
        'summary' => '摘要',
        'invoice_amount' => '开票金额',
        'invoice_type' => '发票类型',
        'reimburser' => '报销人',
        'tax_amount' => '税金',
        'tax_rate' => '税率',
        'deduction_amount' => '扣除额',
        'amount_excluding_tax' => '不含税金额',
    ];
    
    // 检查 PaymentRequest 模型
    $requestFillable = (new PaymentRequest())->getFillable();
    echo "PaymentRequest 模型 fillable 字段检查:\n";
    foreach ($requiredFields as $field => $label) {
        $exists = in_array($field, $requestFillable);
        $status = $exists ? '✅' : '❌';
        echo "  {$status} {$field} ({$label})\n";
    }
    
    echo "\n";
    
    // 检查 PaymentSummary 模型
    $summaryFillable = (new PaymentSummary())->getFillable();
    echo "PaymentSummary 模型 fillable 字段检查:\n";
    foreach ($requiredFields as $field => $label) {
        $exists = in_array($field, $summaryFillable);
        $status = $exists ? '✅' : '❌';
        echo "  {$status} {$field} ({$label})\n";
    }
    
    // 4. 检查类目字段
    echo "\n\n4. 检查类目字段设置\n";
    echo str_repeat('-', 60) . "\n";
    
    $summariesWithCategory = PaymentSummary::whereNotNull('category')
        ->select('payment_type', 'category', DB::raw('COUNT(*) as count'))
        ->groupBy('payment_type', 'category')
        ->get();
    
    if ($summariesWithCategory->isEmpty()) {
        echo "⚠️  没有找到设置了类目的出款汇总记录\n";
    } else {
        echo "类目统计:\n";
        foreach ($summariesWithCategory as $item) {
            echo sprintf("  %s → %s: %d 条记录\n",
                $item->payment_type,
                $item->category,
                $item->count
            );
        }
    }
    
    echo "\n=== 检查完成 ===\n";
    
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
