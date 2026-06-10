<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApprovalFlowConfig;
use App\Models\Employee;
use App\Models\InsuranceChange;
use App\Models\AssessmentRecord;
use Carbon\Carbon;

class CheckInsuranceDeadlines extends Command
{
    protected $signature = 'assessment:check-insurance-deadlines';
    protected $description = '检查参保入职超期情况并记录考核';

    public function handle()
    {
        $this->info('开始检查参保入职超期情况...');

        $today = Carbon::today();
        $latestHireDate = $today->copy()->subDays(30)->toDateString();
        $checkCount = 0;
        $recordCount = 0;

        // 入职满30天仍未完成参保入职的员工需要检查
        $employees = Employee::whereNotNull('hire_date')
            ->whereDate('hire_date', '<=', $latestHireDate)
            ->get();

        $this->info("找到 {$employees->count()} 个需要检查的员工");

        foreach ($employees as $employee) {
            $checkCount++;
            $deadlineDate = Carbon::parse($employee->hire_date)->addDays(30)->toDateString();

            // 检查该员工在增减模块中的状态
            $insuranceChange = InsuranceChange::where('employee_id', $employee->id)
                ->where('account_set_id', $employee->account_set_id)
                ->first();

            // 判断是否需要记录考核
            $shouldRecord = false;
            $reason = '';

            if (!$insuranceChange) {
                // 没有增减记录
                $shouldRecord = true;
                $reason = '未创建增减记录';
            } elseif ($insuranceChange->status !== 'completed') {
                // 状态不是已处理
                $shouldRecord = true;
                $reason = '状态：' . ($insuranceChange->status === 'pending' ? '待处理' : $insuranceChange->status);
            } elseif (!$insuranceChange->attachments || $insuranceChange->attachments->count() === 0) {
                // 没有上传附件
                $shouldRecord = true;
                $reason = '未上传附件';
            }

            // 如果需要记录，检查是否已存在今天的记录
            if ($shouldRecord) {
                // 获取经办人信息（从账套设置中获取）
                $handler = $this->getAccountSetHandler($employee->account_set_id);

                if (!$handler) {
                    $this->warn("员工 {$employee->name} 的账套没有配置经办人，跳过");
                    continue;
                }

                // 检查是否已存在记录
                $existingRecord = AssessmentRecord::where('account_set_id', $employee->account_set_id)
                    ->where('business_type', 'insurance_enrollment')
                    ->where('business_id', $employee->id)
                    ->where('deadline_date', $deadlineDate)
                    ->where('status', '!=', 'completed')
                    ->first();

                if (!$existingRecord) {
                    // 创建新的考核记录
                    $record = AssessmentRecord::create([
                        'account_set_id' => $employee->account_set_id,
                        'business_type' => 'insurance_enrollment',
                        'business_id' => $employee->id,
                        'business_name' => "{$employee->name} - 参保入职",
                        'handler_id' => $handler['id'],
                        'handler_name' => $handler['name'],
                        'deadline_date' => $deadlineDate,
                        'remark' => $reason
                    ]);

                    $record->updateStatus();
                    $recordCount++;

                    $this->info("记录考核：{$employee->name}，原因：{$reason}");
                } else {
                    // 更新现有记录的状态
                    $existingRecord->updateStatus();
                    $this->info("更新记录：{$employee->name}");
                }
            }
        }

        // 更新所有待处理记录的状态
        AssessmentRecord::updateAllPendingStatus();

        $this->info("检查完成！检查了 {$checkCount} 个员工，新增 {$recordCount} 条考核记录");

        return 0;
    }

    // 获取账套的第一个审批人（即经办人）
    private function getAccountSetHandler($accountSetId)
    {
        $firstApprover = ApprovalFlowConfig::getFirstEffectiveApprover(
            (int) $accountSetId,
            'insurance_enrollment'
        );

        if (!$firstApprover) {
            return null;
        }

        return [
            'id' => $firstApprover->user_id,
            'name' => $firstApprover->user_name
        ];
    }
}
