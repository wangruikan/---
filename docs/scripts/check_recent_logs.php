<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OperationLog;

echo "=== 最近5条操作日志 ===\n\n";

$logs = OperationLog::orderBy('created_at', 'desc')->limit(10)->get();

foreach ($logs as $log) {
    echo "ID: {$log->id}\n";
    echo "操作人: {$log->user_name}\n";
    echo "操作: {$log->action}\n";
    echo "描述: {$log->description}\n";
    echo "时间: {$log->created_at}\n";
    
    if ($log->old_values) {
        echo "旧值: " . json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
    
    if ($log->new_values) {
        echo "新值: " . json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
    
    echo str_repeat('-', 80) . "\n\n";
}
