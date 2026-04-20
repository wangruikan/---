<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AccountSet;
use App\Models\Project;
use App\Services\PendingTaskService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckSheetReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sheet:check-reminders 
                            {--date= : 指定日期 (Y-m-d 格式)}
                            {--time= : 指定时间 (H:i 格式)}
                            {--force : 强制执行，忽略时间检查}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查考勤表和工资表提交情况，生成当月待办任务（每月1日运行）';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        
        // 处理参数
        if ($this->option('date')) {
            $now = Carbon::createFromFormat('Y-m-d', $this->option('date'));
        }
        
        if ($this->option('time')) {
            $time = explode(':', $this->option('time'));
            $now->setTime($time[0], $time[1]);
        }
        
        $force = $this->option('force');
        
        $this->info("开始检查考勤表和工资表提交情况 - 当前时间: {$now->toDateTimeString()}");
        
        // 检查是否是每月1日早上8点
        if (!$force && ($now->day != 1 || $now->hour != 8)) {
            $this->warn('当前不是每月1日早上8点，跳过检查（使用 --force 强制执行）');
            return 0;
        }
        
        // 计算当月的月份（3月1日创建3月的待办）
        $currentMonth = $now->format('Y-m');
        $this->info("检查月份: {$currentMonth}");
        
        // 获取所有账套
        $accountSets = AccountSet::all();
        $this->info("找到 {$accountSets->count()} 个账套");
        
        $totalAttendanceTasks = 0;
        $totalSalaryTasks = 0;
        
        foreach ($accountSets as $accountSet) {
            $this->info("处理账套: {$accountSet->name} (ID: {$accountSet->id})");
            
            // 获取该账套下状态为"进行中"的项目
            $projects = Project::where('account_set_id', $accountSet->id)
                ->where('status', 'active')
                ->get();
            
            $this->info("  找到 {$projects->count()} 个进行中的项目");
            
            foreach ($projects as $project) {
                // 检查考勤表（需要检查项目是否开启考勤表功能）
                if ($project->require_attendance) {
                    $task = PendingTaskService::createAttendanceSheetTask(
                        $accountSet->id,
                        $project->id,
                        $currentMonth
                    );
                    
                    if ($task) {
                        $totalAttendanceTasks++;
                        $this->info("    项目 {$project->name}: 创建了考勤表待办任务");
                    } else {
                        $this->comment("    项目 {$project->name}: 考勤表已提交或无需创建任务");
                    }
                } else {
                    $this->comment("    项目 {$project->name}: 未开启考勤表功能，跳过");
                }
                
                // 检查工资表（所有进行中的项目都需要）
                $task = PendingTaskService::createSalarySheetTask(
                    $accountSet->id,
                    $project->id,
                    $currentMonth
                );
                
                if ($task) {
                    $totalSalaryTasks++;
                    $this->info("    项目 {$project->name}: 创建了工资表待办任务");
                } else {
                    $this->comment("    项目 {$project->name}: 工资表已提交或无需创建任务");
                }
            }
        }
        
        $this->info("检查完成！");
        $this->info("总计创建考勤表待办任务: {$totalAttendanceTasks} 个");
        $this->info("总计创建工资表待办任务: {$totalSalaryTasks} 个");
        
        Log::info('考勤表和工资表检查完成', [
            'check_month' => $currentMonth,
            'attendance_tasks' => $totalAttendanceTasks,
            'salary_tasks' => $totalSalaryTasks
        ]);
        
        return 0;
    }
}
