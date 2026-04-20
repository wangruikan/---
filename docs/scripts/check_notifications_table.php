<?php

/**
 * 检查 notifications 表结构
 * 
 * 使用方法：
 * php docs/scripts/check_notifications_table.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== 检查 notifications 表结构 ===\n\n";

// 检查表是否存在
if (!Schema::hasTable('notifications')) {
    echo "错误：notifications 表不存在\n";
    exit(1);
}

echo "notifications 表存在\n\n";

// 获取表结构
$columns = DB::select("SHOW COLUMNS FROM notifications");

echo "字段列表：\n";
foreach ($columns as $column) {
    echo "- {$column->Field}\n";
    echo "  类型: {$column->Type}\n";
    echo "  允许NULL: {$column->Null}\n";
    echo "  默认值: " . ($column->Default ?? 'NULL') . "\n\n";
}

// 检查是否有 data 字段
$hasDataColumn = false;
foreach ($columns as $column) {
    if ($column->Field === 'data') {
        $hasDataColumn = true;
        echo "✓ 找到 data 字段，类型: {$column->Type}\n";
        break;
    }
}

if (!$hasDataColumn) {
    echo "✗ 未找到 data 字段！\n";
    echo "\n需要添加 data 字段，请执行以下 SQL：\n";
    echo "ALTER TABLE `notifications` ADD COLUMN `data` TEXT NULL AFTER `content`;\n";
}

echo "\n完成！\n";
