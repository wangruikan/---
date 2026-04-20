<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AttendanceSheet;
use App\Models\ApprovalInstance;
use App\Models\Project;

echo "=== 调试考勤审批状态 ===\n\n";

// 获取所有考勤表及其审批状态
$sheets = AttendanceSheet::with(['project', 'approvalInstance'])
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get();

if ($sheets->isEmpty()) {
    echo "没有找到考勤表记录\n";
    exit;
}

foreach ($sheets as $sheet) {
    echo "考勤表 ID: {$sheet->id}\n";
    echo "  项目: {$sheet->project->name} (ID: {$sheet->project_id})\n";
    echo "  月份: {$sheet->month}\n";
    echo "  考勤表状态: {$sheet->status}\n";
    echo "  账套: {$sheet->account_set_id}\n";
    
    // 查找关联的审批实例
    $instance = ApprovalInstance::where('business_type', '考勤申请')
        ->where('business_id', $sheet->id)
        ->first();
    
    if ($instance) {
        echo "  审批实例 ID: {$instance->id}\n";
        echo "  审批状态: {$instance->status}\n";
        echo "  当前步骤: {$instance->current_step}/{$instance->total_steps}\n";
        
        if ($instance->status === 'approved') {
            echo "  ✓ 审批已完成\n";
            
            if ($sheet->status === 'approved') {
                echo "  ✓ 考勤表状态正确（approved）\n";
            } else {
                echo "  ✗ 考勤表状态错误（应该是 approved，实际是 {$sheet->status}）\n";
                echo "  【问题】审批完成但考勤表状态未更新！\n";
            }
        } else {
            echo "  审批进行中或未完成（{$instance->status}）\n";
        }
    } else {
        echo "  未找到审批实例\n";
    }
    
    echo "\n";
}

echo "\n=== 检查 2026-01 月份可生成工资表的项目 ===\n\n";

$month = '2026-01';
$accountSetId = 1; // 默认账套

$approvedSheets = AttendanceSheet::where('account_set_id', $accountSetId)
    ->where('month', $month)
    ->where('status', 'approved')
    ->with('project')
    ->get();

echo "月份: {$month}\n";
echo "账套: {$accountSetId}\n";
echo "状态为 approved 的考勤表数量: {$approvedSheets->count()}\n\n";

if ($approvedSheets->isEmpty()) {
    echo "没有找到状态为 approved 的考勤表\n";
    
    // 检查是否有其他状态的考勤表
    $otherSheets = AttendanceSheet::where('account_set_id', $accountSetId)
        ->where('month', $month)
        ->with('project')
        ->get();
    
    if ($otherSheets->isNotEmpty()) {
        echo "\n但找到了以下考勤表：\n";
        foreach ($otherSheets as $sheet) {
            echo "  项目: {$sheet->project->name}, 状态: {$sheet->status}\n";
        }
    }
} else {
    echo "可以生成工资表的项目：\n";
    foreach ($approvedSheets as $sheet) {
        echo "  ✓ {$sheet->project->name}\n";
    }
}

echo "\n=== 检查完成 ===\n";
