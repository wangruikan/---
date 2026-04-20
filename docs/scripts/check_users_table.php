<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 检查 users 表结构 ===\n\n";

$columns = DB::select('SHOW COLUMNS FROM users');

echo "字段列表：\n";
foreach ($columns as $col) {
    echo sprintf("  %-20s %-20s %-10s %s\n", 
        $col->Field, 
        $col->Type, 
        $col->Null === 'YES' ? 'NULL' : 'NOT NULL',
        $col->Default ?? '(无默认值)'
    );
}

echo "\n是否有 role_id 字段: ";
$hasRoleId = false;
foreach ($columns as $col) {
    if ($col->Field === 'role_id') {
        $hasRoleId = true;
        break;
    }
}
echo $hasRoleId ? "✅ 有\n" : "❌ 没有\n";

if (!$hasRoleId) {
    echo "\n需要添加 role_id 字段！\n";
    echo "执行以下 SQL：\n";
    echo "ALTER TABLE users ADD COLUMN role_id INT UNSIGNED NULL COMMENT '角色ID' AFTER role;\n";
    echo "ALTER TABLE users ADD INDEX idx_role_id (role_id);\n";
}

echo "\n=== 检查完成 ===\n";
