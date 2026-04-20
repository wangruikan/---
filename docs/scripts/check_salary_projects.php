<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Project;
use App\Models\AttendanceSheet;

echo "=== 检查工资表项目可用性 ===\n\n";

// 获取所有账套
$accountSets = DB::table('account_sets')->get();

foreach ($accountSets as $accountSet) {
    echo "账套: {$accountSet->name} (ID: {$accountSet->id})\n";
    echo str_repeat('-', 60) . "\n";
    
    // 获取该账套的所有项目
    $projects = Project::where('account_set_id', $accountSet->id)->get();
    
    if ($projects->isEmpty()) {
        echo "  该账套没有项目\n\n";
        continue;
    }
    
    foreach ($projects as $project) {
        echo "  项目: {$project->name} (ID: {$project->id})\n";
        echo "    状态: {$project->status}\n";
        
        // 检查是否需要考勤
        $requireAttendance = $project->require_attendance ?? $project->requires_attendance ?? true;
        echo "    需要考勤: " . ($requireAttendance ? '是' : '否') . "\n";
        
        if ($requireAttendance) {
            // 检查最近3个月的考勤审批情况
            echo "    最近考勤审批情况:\n";
            
            $recentMonths = [];
            for ($i = 0; $i < 3; $i++) {
                $month = date('Y-m', strtotime("-$i months"));
                $recentMonths[] = $month;
            }
            
            foreach ($recentMonths as $month) {
                $attendance = AttendanceSheet::where('account_set_id', $accountSet->id)
                    ->where('project_id', $project->id)
                    ->where('month', $month)
                    ->first();
                
                if ($attendance) {
                    $statusText = match($attendance->status) {
                        'draft' => '草稿',
                        'submitted' => '审批中',
                        'approved' => '已审批',
                        'rejected' => '已拒绝',
                        default => $attendance->status
                    };
                    echo "      {$month}: {$statusText}\n";
                    
                    if ($attendance->status === 'approved') {
                        echo "        ✓ 可以生成 {$month} 的工资表\n";
                    } else {
                        echo "        ✗ 不能生成 {$month} 的工资表（考勤未审批）\n";
                    }
                } else {
                    echo "      {$month}: 无考勤记录\n";
                    echo "        ✗ 不能生成 {$month} 的工资表（无考勤记录）\n";
                }
            }
        } else {
            echo "    ✓ 无需考勤，可直接生成任意月份工资表\n";
        }
        
        echo "\n";
    }
    
    echo "\n";
}

echo "=== 检查完成 ===\n";
