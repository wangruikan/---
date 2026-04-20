<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== 检查 payment_requests 表结构 ===\n\n";

try {
    // 检查表是否存在
    if (!Schema::hasTable('payment_requests')) {
        echo "❌ payment_requests 表不存在！\n";
        exit(1);
    }
    
    echo "✅ payment_requests 表存在\n\n";
    
    // 获取所有列
    $columns = DB::select("SHOW COLUMNS FROM payment_requests");
    
    echo "=== 表字段列表 ===\n";
    foreach ($columns as $column) {
        echo sprintf("%-30s %-20s %s\n", 
            $column->Field, 
            $column->Type,
            $column->Comment ?? ''
        );
    }
    
    echo "\n=== 检查必需的付款表单字段 ===\n";
    $requiredFields = [
        'apply_date',
        'unit_name',
        'invoice_number',
        'payment_date',
        'summary',
        'invoice_amount',
        'invoice_type',
        'reimburser',
        'tax_amount',
        'tax_rate',
        'deduction_amount',
        'amount_excluding_tax',
    ];
    
    $existingColumns = array_column($columns, 'Field');
    
    foreach ($requiredFields as $field) {
        if (in_array($field, $existingColumns)) {
            echo "✅ $field - 存在\n";
        } else {
            echo "❌ $field - 缺失\n";
        }
    }
    
    echo "\n=== 检查 payment_summaries 表结构 ===\n";
    
    if (!Schema::hasTable('payment_summaries')) {
        echo "❌ payment_summaries 表不存在！\n";
        exit(1);
    }
    
    echo "✅ payment_summaries 表存在\n\n";
    
    $summaryColumns = DB::select("SHOW COLUMNS FROM payment_summaries");
    
    echo "=== 表字段列表 ===\n";
    foreach ($summaryColumns as $column) {
        echo sprintf("%-30s %-20s %s\n", 
            $column->Field, 
            $column->Type,
            $column->Comment ?? ''
        );
    }
    
    echo "\n=== 检查必需的付款表单字段 ===\n";
    $existingSummaryColumns = array_column($summaryColumns, 'Field');
    
    foreach ($requiredFields as $field) {
        if (in_array($field, $existingSummaryColumns)) {
            echo "✅ $field - 存在\n";
        } else {
            echo "❌ $field - 缺失\n";
        }
    }
    
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
