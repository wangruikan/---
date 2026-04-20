<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AttendanceSheet;
use App\Models\Project;

echo "=== 检查考勤表状态 ===\n\n";

// 获取最近的考勤表
$sheets = AttendanceSheet::with('project')
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get();

if ($sheets->isEmpty()) {
    echo "没有找到任何考勤表记录\n";
} else {
    echo "最近的考勤表记录：\n";
    echo str_repeat('-', 80) . "\n";
    
    foreach ($sheets as $sheet) {
        $statusText = match($sheet->status) {
            'draft' => '草稿',
            'submitted' => '审批中',
            'approved' => '已审批',
            'rejected' => '已拒绝',
            default => $sheet->status
        };
        
        echo "ID: {$sheet->id}\n";
        echo "  项目: {$sheet->project->name} (ID: {$sheet->project_id})\n";
        echo "  月份: {$sheet->month}\n";
        echo "  状态: {$statusText} ({$sheet->status})\n";
        echo "  账套: {$sheet->account_set_id}\n";
        echo "  创建时间: {$sheet->created_at}\n";
        echo "\n";
    }
}

echo "\n=== 检查 2026-01 月份的考勤表 ===\n\n";

$sheets202601 = AttendanceSheet::with('project')
    ->where('month', '2026-01')
    ->get();

if ($sheets202601->isEmpty()) {
    echo "2026-01 月份没有考勤表记录\n";
} else {
    foreach ($sheets202601 as $sheet) {
        $statusText = match($sheet->status) {
            'draft' => '草稿',
            'submitted' => '审批中',
            'approved' => '已审批',
            'rejected' => '已拒绝',
            default => $sheet->status
        };
        
        echo "项目: {$sheet->project->name}\n";
        echo "  状态: {$statusText}\n";
        echo "  账套: {$sheet->account_set_id}\n";
        
        if ($sheet->status === 'approved') {
            echo "  ✓ 可以生成工资表\n";
        } else {
            echo "  ✗ 不能生成工资表（状态：{$statusText}）\n";
        }
        echo "\n";
    }
}

echo "=== 检查完成 ===\n";
