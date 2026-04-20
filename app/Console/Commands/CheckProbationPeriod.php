<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\ContractReminder;
use App\Models\AssessmentRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckProbationPeriod extends Command
{
    protected $signature = 'probation:check {--date=}';
    protected $description = '每日检查试用期到期情况，提醒和考核';

    public function handle()
    {
        $checkDate = $this->option('date') 
            ? Carbon::parse($this->option('date')) 
            : Carbon::today();
        
        $this->info("检查日期: {$checkDate->toDateString()}");
        
        // 获取所有在职且有试用期的员工
        $employees = Employee::where('contract_status', 'active')
            ->whereNotNull('probation_end_date')
            ->get();
        
        $this->info("找到 {$employees->count()} 名试用期员工");
        
        $reminders = [
            'advance_7days' => 0,
            'advance_3days' => 0,
            'due_today' => 0,
            'escalated' => 0
        ];
        
        foreach ($employees as $employee) {
            $result = $this->checkEmployeeProbation($employee, $checkDate);
            if ($result) {
                $reminders[$result]++;
            }
        }
        
        $this->info("\n检查完成:");
        $this->info("  提前7天提醒: {$reminders['advance_7days']}");
        $this->info("  提前3天提醒: {$reminders['advance_3days']}");
        $this->info("  当天提醒: {$reminders['due_today']}");
        $this->info("  升级考核: {$reminders['escalated']}");
        
        return 0;
    }
    
    /**
     * 检查单个员工的试用期情况
     */
    private function checkEmployeeProbation($employee, $checkDate)
    {
        $probationEnd = Carbon::parse($employee->probation_end_date);
        $daysUntilEnd = $checkDate->diffInDays($probationEnd, false);
        
        // 提前7天提醒
        if ($daysUntilEnd == 7) {
            $this->createProbationReminder($employee, $checkDate, 'advance_7days');
            return 'advance_7days';
        }
        
        // 提前3天提醒
        if ($daysUntilEnd == 3) {
            $this->createProbationReminder($employee, $checkDate, 'advance_3days');
            return 'advance_3days';
        }
        
        // 当天提醒
        if ($daysUntilEnd == 0) {
            $this->createProbationReminder($employee, $checkDate, 'due_today');
            return 'due_today';
        }
        
        // 注释：暂不启用超期升级为考核功能
        // 超期3天未处理 → 升级为考核
        // if ($daysUntilEnd == -3) {
        //     $this->escalateProbationToAssessment($employee, $checkDate);
        //     return 'escalated';
        // }
        
        return null;
    }
    
    /**
     * 创建试用期提醒记录
     */
    private function createProbationReminder($employee, $checkDate, $reminderStage)
    {
        // 检查是否已存在相同的提醒记录
        $existingReminder = ContractReminder::where('employee_id', $employee->id)
            ->where('reminder_type', 'probation_period')
            ->where('reminder_date', $checkDate->format('Y-m-d'))
            ->where('status', '!=', 'resolved')
            ->first();
        
        if ($existingReminder) {
            return; // 已存在，不重复创建
        }
        
        // 获取业务人员信息（第一个审批节点）
        $handler = $this->getBusinessHandler($employee);
        
        // 生成描述
        $stageTexts = [
            'advance_7days' => '试用期将于7天后到期',
            'advance_3days' => '试用期将于3天后到期',
            'due_today' => '试用期今天到期'
        ];
        
        $description = "员工 {$employee->name} {$stageTexts[$reminderStage]}（{$employee->probation_end_date}），请及时处理转正、延长试用期或辞退手续。";
        
        ContractReminder::create([
            'account_set_id' => $employee->account_set_id,
            'employee_id' => $employee->id,
            'reminder_type' => 'probation_period',
            'employee_name' => $employee->name,
            'contract_start_date' => $employee->hire_date,
            'contract_end_date' => $employee->probation_end_date,
            'status' => 'pending',
            'description' => $description,
            'handler_id' => $handler['id'],
            'handler_name' => $handler['name'],
            'reminder_date' => $checkDate->format('Y-m-d'),
        ]);
        
        Log::info("创建试用期提醒", [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'stage' => $reminderStage,
            'probation_end_date' => $employee->probation_end_date
        ]);
    }
    
    /**
     * 升级为考核记录
     */
    private function escalateProbationToAssessment($employee, $checkDate)
    {
        // 检查是否已存在考核记录
        $existingAssessment = AssessmentRecord::where('business_type', 'probation_management')
            ->where('business_id', $employee->id)
            ->where('account_set_id', $employee->account_set_id)
            ->where('deadline_date', '>=', $checkDate)
            ->first();
        
        if ($existingAssessment) {
            return; // 已存在，不重复创建
        }
        
        // 获取业务人员信息（第一个审批节点）
        $handler = $this->getBusinessHandler($employee);
        
        // 计算截止日期（7个工作日后）
        $deadline = $this->calculateWorkingDays($checkDate, 7);
        
        $businessName = "员工 {$employee->name} 试用期到期未处理";
        $remark = "试用期于 {$employee->probation_end_date} 到期，超期3天仍未处理转正、延长试用期或辞退手续。";
        
        AssessmentRecord::create([
            'account_set_id' => $employee->account_set_id,
            'business_type' => 'probation_management',
            'business_id' => $employee->id,
            'business_name' => $businessName,
            'handler_id' => $handler['id'],
            'handler_name' => $handler['name'],
            'deadline_date' => $deadline,
            'status' => 'pending',
            'remark' => $remark
        ]);
        
        // 更新提醒记录状态
        ContractReminder::where('employee_id', $employee->id)
            ->where('reminder_type', 'probation_period')
            ->where('status', 'pending')
            ->update([
                'status' => 'escalated',
                'is_escalated' => true,
                'escalation_date' => $checkDate->format('Y-m-d'),
                'remark' => '试用期到期超期3天未处理，已升级为考核记录'
            ]);
        
        Log::info("试用期升级为考核", [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'probation_end_date' => $employee->probation_end_date,
            'handler_id' => $handler['id'],
            'handler_name' => $handler['name']
        ]);
    }
    
    /**
     * 获取业务人员信息（从账套获取第一个审批人）
     */
    private function getBusinessHandler($employee)
    {
        $firstApprover = DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $employee->account_set_id)
            ->whereNotNull('account_set_users.approval_level')
            ->orderBy('account_set_users.approval_level')
            ->select('users.id as user_id', 'users.name as user_name')
            ->first();

        if (!$firstApprover) {
            return [
                'id' => 0,
                'name' => '业务人员'
            ];
        }

        return [
            'id' => $firstApprover->user_id,
            'name' => $firstApprover->user_name
        ];
    }
    
    /**
     * 计算工作日（排除周末）
     */
    private function calculateWorkingDays($startDate, $days)
    {
        $date = $startDate->copy();
        $addedDays = 0;
        
        while ($addedDays < $days) {
            $date->addDay();
            // 如果不是周末，计数
            if (!$date->isWeekend()) {
                $addedDays++;
            }
        }
        
        return $date->format('Y-m-d');
    }
}
