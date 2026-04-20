<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "检查 salaries 表结构:\n\n";

$columns = DB::select('SHOW COLUMNS FROM salaries');

foreach ($columns as $col) {
    echo $col->Field . " - " . $col->Type . "\n";
}

echo "\n检查是否有 status 字段: ";
$hasStatus = false;
foreach ($columns as $col) {
    if ($col->Field === 'status') {
        $hasStatus = true;
        break;
    }
}

echo $hasStatus ? "✅ 有\n" : "❌ 没有\n";

if (!$hasStatus) {
    echo "\n⚠️ salaries 表没有 status 字段！需要添加。\n";
}
