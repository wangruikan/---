<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\AssessmentRecord;
use App\Models\ApprovalInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckResignationContracts extends Command
{
    /**
     * 命令名称
     */
    protected $signature = 'check:resignation-contracts';

    /**
     * 命令描述
     */
    protected $description = '检查填写了离职日期但未完成离职合同审批的员工，并记录到考核';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('开始检查离职合同...');
        
        // 获取当前月份的最后一天
        $currentMonthEnd = Carbon::now()->endOfMonth()->toDateString();
        
        // 查找所有填写了离职日期的员工
        $employees = Employee::whereNotNull('resignation_date')
            ->where('resignation_date', '<=', $currentMonthEnd)
            ->get();
        
        $this->info("找到 {$employees->count()} 个填写了离职日期的员工");
        
        $recordCount = 0;
        
        foreach ($employees as $employee) {
            // 检查该员工是否有已完成的离职合同
            $hasCompletedContract = $this->hasCompletedResignationContract($employee);
            
            if (!$hasCompletedContract) {
                // 没有完成的离职合同，查找离职合同的第一个审批人
                $firstApprover = $this->getFirstApproverOfResignationContract($employee);
                
                if ($firstApprover) {
                    // 创建考核记录
                    $this->createAssessmentRecord($employee, $firstApprover);
                    $recordCount++;
                    $this->info("已为员工 {$employee->name} 创建考核记录，责任人：{$firstApprover['name']}");
                } else {
                    $this->warn("员工 {$employee->name} 没有找到离职合同的审批人");
                }
            } else {
                $this->info("员工 {$employee->name} 的离职合同已完成审批");
            }
        }
        
        $this->info("检查完成，共创建 {$recordCount} 条考核记录");
        
        Log::info('离职合同检查完成', [
            'total_employees' => $employees->count(),
            'assessment_records_created' => $recordCount,
            'check_date' => Carbon::now()->toDateTimeString()
        ]);
        
        return 0;
    }
    
    /**
     * 检查员工是否有已完成的离职合同
     */
    private function hasCompletedResignationContract(Employee $employee): bool
    {
        // 查找该员工的离职合同
        $contract = EmployeeContract::where('employee_id', $employee->id)
            ->where('contract_type', 'termination')
            ->whereNotNull('approval_instance_id')
            ->first();
        
        if (!$contract) {
            return false;
        }
        
        // 检查审批实例的状态
        $approvalInstance = ApprovalInstance::find($contract->approval_instance_id);
        
        if (!$approvalInstance) {
            return false;
        }
        
        // 审批状态为 'approved' 表示已完成
        return $approvalInstance->status === 'approved';
    }
    
    /**
     * 获取离职合同的第一个审批人
     */
    private function getFirstApproverOfResignationContract(Employee $employee): ?array
    {
        // 查找该员工的离职合同
        $contract = EmployeeContract::where('employee_id', $employee->id)
            ->where('contract_type', 'termination')
            ->whereNotNull('approval_instance_id')
            ->first();
        
        if (!$contract) {
            return null;
        }
        
        // 获取审批实例
        $approvalInstance = ApprovalInstance::find($contract->approval_instance_id);
        
        if (!$approvalInstance) {
            return null;
        }
        
        // 获取第一个审批节点（step_order = 1）
        $firstRecord = $approvalInstance->records()
            ->where('step_order', 1)
            ->first();
        
        if (!$firstRecord) {
            return null;
        }
        
        return [
            'id' => $firstRecord->approver_id,
            'name' => $firstRecord->approver_name
        ];
    }
    
    /**
     * 创建考核记录
     */
    private function createAssessmentRecord(Employee $employee, array $approver): void
    {
        // 计算截止日期（离职日期当月的最后一天）
        $resignationDate = Carbon::parse($employee->resignation_date);
        $deadlineDate = $resignationDate->endOfMonth()->toDateString();
        
        // 检查是否已存在相同的考核记录（避免重复创建）
        $exists = AssessmentRecord::where('business_type', 'resignation_contract')
            ->where('business_id', $employee->id)
            ->where('handler_id', $approver['id'])
            ->where('deadline_date', $deadlineDate)
            ->exists();
        
        if ($exists) {
            return;
        }
        
        // 创建考核记录
        AssessmentRecord::create([
            'account_set_id' => $employee->account_set_id,
            'business_type' => 'resignation_contract',
            'business_id' => $employee->id,
            'business_name' => "员工 {$employee->name} 的离职合同",
            'handler_id' => $approver['id'],
            'handler_name' => $approver['name'],
            'deadline_date' => $deadlineDate,
            'actual_complete_date' => null,
            'overdue_days' => 0,
            'status' => 'overdue',
            'remark' => "员工离职日期：{$employee->resignation_date}，截止日期前未完成离职合同审批"
        ]);
    }
}
