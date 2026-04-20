<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ContractReminder;
use App\Models\EmployeeContract;
use App\Models\AssessmentRecord;
use App\Models\AccountSet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EscalateContractReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contract:escalate-reminders {--account-set-id=} {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每月15号检查月末合同提醒记录，未解决的升级为考核记录';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始检查合同提醒记录升级...');
        
        $accountSetId = $this->option('account-set-id');
        $checkDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now();
        
        $this->info("检查日期: {$checkDate->format('Y-m-d')}");
        
        // 计算上个月末的日期
        $lastMonthEnd = $checkDate->copy()->subMonth()->endOfMonth();
        $this->info("检查上月末日期: {$lastMonthEnd->format('Y-m-d')}");
        
        $results = [];
        
        if ($accountSetId) {
            $accountSets = AccountSet::where('id', $accountSetId)->get();
        } else {
            $accountSets = AccountSet::all();
        }
        
        foreach ($accountSets as $accountSet) {
            $this->info("正在检查账套: {$accountSet->name} (ID: {$accountSet->id})");
            $result = $this->processAccountSetReminders($accountSet, $lastMonthEnd, $checkDate);
            $results[] = $result;
        }
        
        // 输出总结
        $totalEscalated = array_sum(array_column($results, 'escalated_count'));
        $totalResolved = array_sum(array_column($results, 'resolved_count'));
        
        $this->info("检查完成!");
        $this->info("总计解决提醒: {$totalResolved}");
        $this->info("总计升级考核: {$totalEscalated}");
        
        return 0;
    }
    
    /**
     * 处理指定账套的提醒记录
     */
    private function processAccountSetReminders($accountSet, $lastMonthEnd, $checkDate)
    {
        $escalatedCount = 0;
        $resolvedCount = 0;
        
        // 获取上月末的待处理提醒记录
        $reminders = ContractReminder::where('account_set_id', $accountSet->id)
            ->where('reminder_date', $lastMonthEnd->format('Y-m-d'))
            ->where('status', 'pending')
            ->where('is_escalated', false)
            ->get();
        
        $this->info("  找到待处理提醒: {$reminders->count()} 条");
        
        foreach ($reminders as $reminder) {
            if ($this->checkIfResolved($reminder)) {
                // 标记为已解决
                $reminder->update([
                    'status' => 'resolved',
                    'remark' => '15号复查时发现已上传相关合同'
                ]);
                $resolvedCount++;
                $this->info("    提醒已解决: {$reminder->employee_name} - {$reminder->reminder_type_text}");
            } else {
                // 升级为考核记录
                $assessmentRecord = $this->createAssessmentRecord($reminder, $checkDate);
                if ($assessmentRecord) {
                    $reminder->update([
                        'status' => 'escalated',
                        'is_escalated' => true,
                        'escalation_date' => $checkDate->format('Y-m-d'),
                        'assessment_record_id' => $assessmentRecord->id,
                        'remark' => '15号复查时仍未解决，已升级为考核记录'
                    ]);
                    $escalatedCount++;
                    $this->info("    提醒已升级: {$reminder->employee_name} - {$reminder->reminder_type_text}");
                }
            }
        }
        
        return [
            'account_set_id' => $accountSet->id,
            'account_set_name' => $accountSet->name,
            'escalated_count' => $escalatedCount,
            'resolved_count' => $resolvedCount
        ];
    }
    
    /**
     * 检查提醒是否已解决（是否已上传相关合同）
     */
    private function checkIfResolved($reminder)
    {
        $employee = $reminder->employee;
        if (!$employee) {
            return false;
        }
        
        switch ($reminder->reminder_type) {
            case 'labor_contract':
                return $this->hasLaborContract($employee, $reminder);
                
            case 'termination_agreement':
                return $this->hasTerminationAgreement($employee, $reminder);
                
            case 'retirement_agreement':
                return $this->hasRetirementAgreement($employee, $reminder);
                
            default:
                return false;
        }
    }
    
    /**
     * 检查是否已有劳动合同
     */
    private function hasLaborContract($employee, $reminder)
    {
        if (!$reminder->contract_start_date || !$reminder->contract_end_date) {
            return false;
        }
        
        return EmployeeContract::where('employee_id', $employee->id)
            ->where('contract_type', 'labor')
            ->where('status', '!=', 'rejected')
            ->whereBetween('created_at', [
                $reminder->contract_start_date,
                Carbon::parse($reminder->contract_end_date)->endOfDay()
            ])
            ->exists();
    }
    
    /**
     * 检查是否已有解除协议合同
     */
    private function hasTerminationAgreement($employee, $reminder)
    {
        if (!$reminder->contract_end_date) {
            return false;
        }
        
        return EmployeeContract::where('employee_id', $employee->id)
            ->where('contract_type', 'termination')
            ->where('status', '!=', 'rejected')
            ->where('created_at', '>', Carbon::parse($reminder->contract_end_date)->endOfDay())
            ->exists();
    }
    
    /**
     * 检查是否已有退休解除协议合同
     */
    private function hasRetirementAgreement($employee, $reminder)
    {
        if (!$reminder->contract_end_date) {
            return false;
        }
        
        return EmployeeContract::where('employee_id', $employee->id)
            ->where('contract_type', 'retirement')
            ->where('status', '!=', 'rejected')
            ->where('created_at', '>', Carbon::parse($reminder->contract_end_date)->endOfDay())
            ->exists();
    }
    
    /**
     * 创建考核记录
     */
    private function createAssessmentRecord($reminder, $checkDate)
    {
        // 检查是否已存在相同的考核记录
        $existingRecord = AssessmentRecord::where('business_type', 'contract_management')
            ->where('business_id', $reminder->id)
            ->where('account_set_id', $reminder->account_set_id)
            ->first();
            
        if ($existingRecord) {
            return $existingRecord; // 已存在，返回现有记录
        }
        
        // 计算截止日期（7个工作日后）
        $deadline = $this->calculateWorkingDays($checkDate, 7);
        
        // 生成业务描述
        $businessName = $this->generateBusinessName($reminder);
        
        // 生成备注信息
        $remark = $this->generateRemark($reminder);
        
        return AssessmentRecord::create([
            'account_set_id' => $reminder->account_set_id,
            'business_type' => 'contract_management',
            'business_id' => $reminder->id,
            'business_name' => $businessName,
            'handler_id' => $reminder->handler_id,
            'handler_name' => $reminder->handler_name,
            'deadline_date' => $deadline,
            'status' => 'pending',
            'remark' => $remark
        ]);
    }
    
    /**
     * 生成业务描述
     */
    private function generateBusinessName($reminder)
    {
        $typeNames = [
            'labor_contract' => '劳动合同签订',
            'termination_agreement' => '解除协议合同',
            'retirement_agreement' => '退休解除协议合同',
        ];
        
        $typeName = $typeNames[$reminder->reminder_type] ?? '合同管理';
        
        return "员工 {$reminder->employee_name} {$typeName}";
    }
    
    /**
     * 生成备注信息
     */
    private function generateRemark($reminder)
    {
        $remark = "月末检查发现问题，15号复查仍未解决，现列入考核。\n\n";
        $remark .= "问题描述：{$reminder->description}\n";
        $remark .= "提醒日期：{$reminder->reminder_date}\n";
        $remark .= "请及时处理相关合同事务。";
        
        return $remark;
    }
    
    /**
     * 计算工作日
     */
    private function calculateWorkingDays($startDate, $workingDays)
    {
        $date = $startDate->copy();
        $addedDays = 0;
        
        while ($addedDays < $workingDays) {
            $date->addDay();
            
            // 跳过周末（周六=6，周日=0）
            if ($date->dayOfWeek !== 0 && $date->dayOfWeek !== 6) {
                $addedDays++;
            }
        }
        
        return $date;
    }
}
