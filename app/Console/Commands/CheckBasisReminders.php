<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AccountSet;
use App\Models\Project;
use App\Models\BasisRecord;
use App\Services\PendingTaskService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
    protected $description = '每月自动创建工资/考勤依据记录（待上传），并生成待办任务';

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
        
        $this->info("开始自动创建依据记录 - 当前时间: {$now->toDateTimeString()}");
        
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
        
        $totalSalaryRecords = 0;
        $totalAttendanceRecords = 0;
        $totalSalaryTasks = 0;
        $totalAttendanceTasks = 0;
        
        foreach ($accountSets as $accountSet) {
            $this->info("处理账套: {$accountSet->name} (ID: {$accountSet->id})");

            $creatorId = $this->resolveCreatorId($accountSet->id);
            if (!$creatorId) {
                $this->warn("  账套 {$accountSet->name} 未找到可用用户，跳过");
                continue;
            }
            
            // 获取该账套下需要上传依据的项目
            $projects = Project::where('account_set_id', $accountSet->id)
                ->where('status', 'active')
                ->where(function($query) {
                    $query->where('requires_salary_basis', true)
                          ->orWhere('requires_attendance_basis', true);
                })
                ->get();
            
            $this->info("  找到 {$projects->count()} 个需要上传依据的项目");
            
            foreach ($projects as $project) {
                // 检查工资依据
                if ($project->requires_salary_basis) {
                    $salaryRecord = BasisRecord::firstOrCreate(
                        [
                            'account_set_id' => $accountSet->id,
                            'project_id' => $project->id,
                            'type' => 'salary',
                            'month' => $currentMonth,
                        ],
                        [
                            'description' => '系统自动创建，待上传工资依据附件',
                            'created_by' => $creatorId,
                        ]
                    );

                    if ($salaryRecord->wasRecentlyCreated) {
                        $totalSalaryRecords++;
                        $this->info("    项目 {$project->name}: 自动创建工资依据记录");
                    }

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
                    $attendanceRecord = BasisRecord::firstOrCreate(
                        [
                            'account_set_id' => $accountSet->id,
                            'project_id' => $project->id,
                            'type' => 'attendance',
                            'month' => $currentMonth,
                        ],
                        [
                            'description' => '系统自动创建，待上传考勤依据附件',
                            'created_by' => $creatorId,
                        ]
                    );

                    if ($attendanceRecord->wasRecentlyCreated) {
                        $totalAttendanceRecords++;
                        $this->info("    项目 {$project->name}: 自动创建考勤依据记录");
                    }

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
        $this->info("总计自动创建工资依据记录: {$totalSalaryRecords} 个");
        $this->info("总计自动创建考勤依据记录: {$totalAttendanceRecords} 个");
        $this->info("总计创建工资依据待办任务: {$totalSalaryTasks} 个");
        $this->info("总计创建考勤依据待办任务: {$totalAttendanceTasks} 个");
        
        Log::info('依据上传检查完成', [
            'check_month' => $currentMonth,
            'salary_records' => $totalSalaryRecords,
            'attendance_records' => $totalAttendanceRecords,
            'salary_tasks' => $totalSalaryTasks,
            'attendance_tasks' => $totalAttendanceTasks
        ]);
        
        return 0;
    }

    /**
     * 获取账套内一个可用用户ID，作为系统自动记录的创建人
     */
    private function resolveCreatorId(int $accountSetId): ?int
    {
        $userId = DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->where('users.is_active', true)
            ->orderByRaw('CASE WHEN account_set_users.approval_level IS NULL THEN 999 ELSE account_set_users.approval_level END ASC')
            ->value('account_set_users.user_id');

        return $userId ? (int) $userId : null;
    }
}
