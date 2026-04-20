<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\ContractReminder;
use App\Models\AccountSet;
use App\Models\ApprovalRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckMonthlyContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contract:check-monthly {--account-set-id=} {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '月末检查员工合同签订情况和解除协议情况';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始月末合同检查...');
        
        $accountSetId = $this->option('account-set-id');
        $checkDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now();
        
        $this->info("检查日期: {$checkDate->format('Y-m-d')}");
        
        $results = [];
        
        if ($accountSetId) {
            $accountSets = AccountSet::where('id', $accountSetId)->get();
        } else {
            $accountSets = AccountSet::all();
        }
        
        foreach ($accountSets as $accountSet) {
            $this->info("正在检查账套: {$accountSet->name} (ID: {$accountSet->id})");
            $result = $this->checkAccountSetContracts($accountSet, $checkDate);
            $results[] = $result;
        }
        
        // 输出总结
        $totalReminders = array_sum(array_column($results, 'total_reminders'));
        $this->info("检查完成!");
        $this->info("总计生成提醒记录: {$totalReminders}");
        
        return 0;
    }
    
    /**
     * 检查指定账套的合同情况
     */
    private function checkAccountSetContracts($accountSet, $checkDate)
    {
        $totalReminders = 0;
        
        // 获取所有员工
        $employees = Employee::where('account_set_id', $accountSet->id)->get();
        
        $this->info("  找到员工: {$employees->count()} 人");
        
        foreach ($employees as $employee) {
            $reminders = $this->checkEmployeeContract($employee, $checkDate);
            $totalReminders += count($reminders);
            
            if (!empty($reminders)) {
                $this->info("    员工: {$employee->name} - 生成 " . count($reminders) . " 条提醒");
            }
        }
        
        return [
            'account_set_id' => $accountSet->id,
            'account_set_name' => $accountSet->name,
            'total_reminders' => $totalReminders
        ];
    }
    
    /**
     * 检查单个员工的合同情况
     */
    private function checkEmployeeContract($employee, $checkDate)
    {
        $reminders = [];
        
        // 1. 检查是否需要签订劳动合同
        if ($this->needsLaborContract($employee, $checkDate)) {
            $reminder = $this->createLaborContractReminder($employee, $checkDate);
            if ($reminder) {
                $reminders[] = $reminder;
            }
        }
        
        // 2. 检查是否需要解除协议合同
        if ($this->needsTerminationAgreement($employee, $checkDate)) {
            $reminder = $this->createTerminationAgreementReminder($employee, $checkDate);
            if ($reminder) {
                $reminders[] = $reminder;
            }
        }
        
        // 3. 检查是否需要退休解除协议合同
        if ($this->needsRetirementAgreement($employee, $checkDate)) {
            $reminder = $this->createRetirementAgreementReminder($employee, $checkDate);
            if ($reminder) {
                $reminders[] = $reminder;
            }
        }
        
        return $reminders;
    }
    
    /**
     * 检查是否需要签订劳动合同
     */
    private function needsLaborContract($employee, $checkDate)
    {
        // 检查当前时间是否在合同有效期内
        if (!$employee->contract_start_date || !$employee->contract_end_date) {
            return false;
        }
        
        $contractStart = Carbon::parse($employee->contract_start_date);
        $contractEnd = Carbon::parse($employee->contract_end_date);
        
        // 当前时间必须在合同有效期内
        if (!$checkDate->between($contractStart, $contractEnd)) {
            return false;
        }
        
        // 检查是否已有劳动合同（创建时间在合同有效期内）
        $hasLaborContract = EmployeeContract::where('employee_id', $employee->id)
            ->where('contract_type', 'labor')
            ->where('status', '!=', 'rejected')
            ->whereBetween('created_at', [$contractStart, $contractEnd->endOfDay()])
            ->exists();
            
        return !$hasLaborContract;
    }
    
    /**
     * 检查是否需要解除协议合同
     */
    private function needsTerminationAgreement($employee, $checkDate)
    {
        // 检查是否已离职且合同已结束
        if (!$employee->termination_date || !$employee->contract_end_date) {
            return false;
        }
        
        $terminationDate = Carbon::parse($employee->termination_date);
        $contractEnd = Carbon::parse($employee->contract_end_date);
        
        // 当前时间必须大于合同结束时间
        if (!$checkDate->gt($contractEnd)) {
            return false;
        }
        
        // 检查是否已有解除协议合同（创建时间在合同结束日期后）
        $hasTerminationAgreement = EmployeeContract::where('employee_id', $employee->id)
            ->where('contract_type', 'termination')
            ->where('status', '!=', 'rejected')
            ->where('created_at', '>', $contractEnd->endOfDay())
            ->exists();
            
        return !$hasTerminationAgreement;
    }
    
    /**
     * 检查是否需要退休解除协议合同
     */
    private function needsRetirementAgreement($employee, $checkDate)
    {
        // 检查是否已退休且合同已结束
        if (!$employee->is_retired || !$employee->retirement_date || !$employee->contract_end_date) {
            return false;
        }
        
        $retirementDate = Carbon::parse($employee->retirement_date);
        $contractEnd = Carbon::parse($employee->contract_end_date);
        
        // 当前时间必须大于合同结束时间
        if (!$checkDate->gt($contractEnd)) {
            return false;
        }
        
        // 检查是否已有退休解除协议合同（创建时间在合同结束日期后）
        $hasRetirementAgreement = EmployeeContract::where('employee_id', $employee->id)
            ->where('contract_type', 'retirement')
            ->where('status', '!=', 'rejected')
            ->where('created_at', '>', $contractEnd->endOfDay())
            ->exists();
            
        return !$hasRetirementAgreement;
    }
    
    /**
     * 创建劳动合同提醒记录
     */
    private function createLaborContractReminder($employee, $checkDate)
    {
        // 检查是否已存在相同的提醒记录
        $existingReminder = ContractReminder::where('employee_id', $employee->id)
            ->where('reminder_type', 'labor_contract')
            ->where('reminder_date', $checkDate->format('Y-m-d'))
            ->where('status', '!=', 'resolved')
            ->first();
            
        if ($existingReminder) {
            return null; // 已存在，不重复创建
        }
        
        // 获取业务人员信息
        $handler = $this->getBusinessHandler($employee);
        
        $description = "员工 {$employee->name} 合同有效期内（{$employee->contract_start_date} 至 {$employee->contract_end_date}）未签订劳动合同，请及时处理。";
        
        return ContractReminder::create([
            'account_set_id' => $employee->account_set_id,
            'employee_id' => $employee->id,
            'reminder_type' => 'labor_contract',
            'employee_name' => $employee->name,
            'contract_start_date' => $employee->contract_start_date,
            'contract_end_date' => $employee->contract_end_date,
            'status' => 'pending',
            'description' => $description,
            'handler_id' => $handler['id'],
            'handler_name' => $handler['name'],
            'reminder_date' => $checkDate->format('Y-m-d'),
        ]);
    }
    
    /**
     * 创建解除协议提醒记录
     */
    private function createTerminationAgreementReminder($employee, $checkDate)
    {
        // 检查是否已存在相同的提醒记录
        $existingReminder = ContractReminder::where('employee_id', $employee->id)
            ->where('reminder_type', 'termination_agreement')
            ->where('reminder_date', $checkDate->format('Y-m-d'))
            ->where('status', '!=', 'resolved')
            ->first();
            
        if ($existingReminder) {
            return null; // 已存在，不重复创建
        }
        
        // 获取业务人员信息
        $handler = $this->getBusinessHandler($employee);
        
        $description = "员工 {$employee->name} 已于 {$employee->termination_date} 离职，合同于 {$employee->contract_end_date} 结束，但未收到解除协议合同，请及时处理。";
        
        return ContractReminder::create([
            'account_set_id' => $employee->account_set_id,
            'employee_id' => $employee->id,
            'reminder_type' => 'termination_agreement',
            'employee_name' => $employee->name,
            'contract_end_date' => $employee->contract_end_date,
            'termination_date' => $employee->termination_date,
            'status' => 'pending',
            'description' => $description,
            'handler_id' => $handler['id'],
            'handler_name' => $handler['name'],
            'reminder_date' => $checkDate->format('Y-m-d'),
        ]);
    }
    
    /**
     * 创建退休解除协议提醒记录
     */
    private function createRetirementAgreementReminder($employee, $checkDate)
    {
        // 检查是否已存在相同的提醒记录
        $existingReminder = ContractReminder::where('employee_id', $employee->id)
            ->where('reminder_type', 'retirement_agreement')
            ->where('reminder_date', $checkDate->format('Y-m-d'))
            ->where('status', '!=', 'resolved')
            ->first();
            
        if ($existingReminder) {
            return null; // 已存在，不重复创建
        }
        
        // 获取业务人员信息
        $handler = $this->getBusinessHandler($employee);
        
        $description = "员工 {$employee->name} 已于 {$employee->retirement_date} 退休，合同于 {$employee->contract_end_date} 结束，但未收到退休解除协议合同，请及时处理。";
        
        return ContractReminder::create([
            'account_set_id' => $employee->account_set_id,
            'employee_id' => $employee->id,
            'reminder_type' => 'retirement_agreement',
            'employee_name' => $employee->name,
            'contract_end_date' => $employee->contract_end_date,
            'retirement_date' => $employee->retirement_date,
            'status' => 'pending',
            'description' => $description,
            'handler_id' => $handler['id'],
            'handler_name' => $handler['name'],
            'reminder_date' => $checkDate->format('Y-m-d'),
        ]);
    }
    
    /**
     * 获取业务人员信息（从账套获取第一个审批人）
     */
    private function getBusinessHandler($employee)
    {
        $firstApprover = \DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $employee->account_set_id)
            ->whereNotNull('account_set_users.approval_level')
            ->orderBy('account_set_users.approval_level')
            ->select('users.id as user_id', 'users.name as user_name')
            ->first();

        if (!$firstApprover) {
            // 如果找不到审批人，返回默认值
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
}
