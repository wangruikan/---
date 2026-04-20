<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AccountSet;
use App\Models\Project;
use App\Services\PendingTaskService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckBasisReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'basis:check-reminders 
                            {--date= : 指定日期 (Y-m-d 格式)}
                            {--time= : 指定时间 (H:i 格式)}
                            {--force : 强制执行，忽略时间检查}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查工资和考勤依据上传情况，生成当月待办任务（每月1日运行）';

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
        
        $this->info("开始检查依据上传情况 - 当前时间: {$now->toDateTimeString()}");
        
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
        
        $totalSalaryTasks = 0;
        $totalAttendanceTasks = 0;
        
        foreach ($accountSets as $accountSet) {
            $this->info("处理账套: {$accountSet->name} (ID: {$accountSet->id})");
            
            // 获取该账套下需要上传依据的项目
            $projects = Project::where('account_set_id', $accountSet->id)
                ->where(function($query) {
                    $query->where('requires_salary_basis', true)
                          ->orWhere('requires_attendance_basis', true);
                })
                ->get();
            
            $this->info("  找到 {$projects->count()} 个需要上传依据的项目");
            
            foreach ($projects as $project) {
                // 检查工资依据
                if ($project->requires_salary_basis) {
                    $tasks = PendingTaskService::createSalaryBasisTask(
                        $accountSet->id,
                        $project->id,
                        $currentMonth
                    );
                    
                    if ($tasks && is_array($tasks)) {
                        $count = count($tasks);
                        $totalSalaryTasks += $count;
                        $this->info("    项目 {$project->name}: 创建了 {$count} 个工资依据待办任务");
                    } else {
                        $this->comment("    项目 {$project->name}: 工资依据已上传或无需创建任务");
                    }
                }
                
                // 检查考勤依据
                if ($project->requires_attendance_basis) {
                    $tasks = PendingTaskService::createAttendanceBasisTask(
                        $accountSet->id,
                        $project->id,
                        $currentMonth
                    );
                    
                    if ($tasks && is_array($tasks)) {
                        $count = count($tasks);
                        $totalAttendanceTasks += $count;
                        $this->info("    项目 {$project->name}: 创建了 {$count} 个考勤依据待办任务");
                    } else {
                        $this->comment("    项目 {$project->name}: 考勤依据已上传或无需创建任务");
                    }
                }
            }
        }
        
        $this->info("检查完成！");
        $this->info("总计创建工资依据待办任务: {$totalSalaryTasks} 个");
        $this->info("总计创建考勤依据待办任务: {$totalAttendanceTasks} 个");
        
        Log::info('依据上传检查完成', [
            'check_month' => $currentMonth,
            'salary_tasks' => $totalSalaryTasks,
            'attendance_tasks' => $totalAttendanceTasks
        ]);
        
        return 0;
    }
}
