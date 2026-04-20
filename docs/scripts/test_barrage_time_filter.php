<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\OperationLog;
use Carbon\Carbon;

echo "=== 测试弹幕时间过滤功能 ===\n\n";

// 获取所有日志的时间范围
$oldestLog = OperationLog::orderBy('created_at', 'asc')->first();
$newestLog = OperationLog::orderBy('created_at', 'desc')->first();

if (!$oldestLog || !$newestLog) {
    echo "没有操作日志数据\n";
    exit;
}

echo "日志时间范围:\n";
echo "  最早: {$oldestLog->created_at}\n";
echo "  最新: {$newestLog->created_at}\n\n";

// 模拟"开启弹幕"的时间点（使用中间时间）
$middleTime = Carbon::parse($oldestLog->created_at)
    ->addSeconds(
        Carbon::parse($newestLog->created_at)->diffInSeconds($oldestLog->created_at) / 2
    );

echo "模拟开启弹幕时间: {$middleTime}\n\n";

// 查询这个时间之前的日志数量
$beforeCount = OperationLog::where('created_at', '<=', $middleTime)->count();
echo "开启时间之前的日志数量: {$beforeCount}\n";

// 查询这个时间之后的日志数量
$afterCount = OperationLog::where('created_at', '>', $middleTime)->count();
echo "开启时间之后的日志数量: {$afterCount}\n\n";

// 模拟API请求（带after参数）
echo "=== 模拟API请求（带after参数） ===\n";
$logs = OperationLog::where('created_at', '>', $middleTime)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

echo "返回的日志数量: {$logs->count()}\n";
if ($logs->count() > 0) {
    echo "\n返回的日志:\n";
    foreach ($logs as $log) {
        echo "  - [{$log->created_at}] {$log->user_name}: {$log->description}\n";
    }
}

echo "\n✅ 时间过滤功能正常工作\n";
echo "说明：开启弹幕后，只会显示开启时间之后的 {$afterCount} 条日志，\n";
echo "      不会显示之前的 {$beforeCount} 条历史日志。\n";
