<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== 检查 category 字段 ===\n\n";

// 检查 payment_summaries 表
if (Schema::hasColumn('payment_summaries', 'category')) {
    echo "✅ payment_summaries 表有 category 字段\n";
    
    // 查看字段详情
    $columns = DB::select("SHOW COLUMNS FROM payment_summaries WHERE Field = 'category'");
    if (!empty($columns)) {
        $col = $columns[0];
        echo "   类型: {$col->Type}\n";
        echo "   默认值: " . ($col->Default ?? 'NULL') . "\n";
        echo "   注释: " . ($col->Comment ?? '无') . "\n";
    }
} else {
    echo "❌ payment_summaries 表没有 category 字段\n";
    echo "\n需要添加 category 字段的 SQL:\n";
    echo "ALTER TABLE payment_summaries ADD COLUMN category VARCHAR(50) NULL COMMENT '类目：报销/差旅/采购/项目/其他' AFTER payment_type;\n";
}

echo "\n";

// 检查 payment_requests 表
if (Schema::hasColumn('payment_requests', 'category')) {
    echo "✅ payment_requests 表有 category 字段\n";
    
    // 查看字段详情
    $columns = DB::select("SHOW COLUMNS FROM payment_requests WHERE Field = 'category'");
    if (!empty($columns)) {
        $col = $columns[0];
        echo "   类型: {$col->Type}\n";
        echo "   默认值: " . ($col->Default ?? 'NULL') . "\n";
        echo "   注释: " . ($col->Comment ?? '无') . "\n";
    }
} else {
    echo "❌ payment_requests 表没有 category 字段\n";
}
