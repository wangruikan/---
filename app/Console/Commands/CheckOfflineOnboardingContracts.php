<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\ContractReminder;
use App\Models\AccountSet;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckOfflineOnboardingContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contract:check-offline-onboarding {--account-set-id=} {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查线下入职员工合同上传情况，超过30天未上传则提醒业务人员';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始检查线下入职合同上传情况...');
        
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
            $result = $this->checkAccountSetOfflineOnboarding($accountSet, $checkDate);
            $results[] = $result;
        }
        
        // 输出总结
        $totalReminders = array_sum(array_column($results, 'total_reminders'));
        $this->info("检查完成!");
        $this->info("总计生成提醒记录: {$totalReminders}");
        
        return 0;
    }
    
    /**
     * 检查指定账套的线下入职合同上传情况
     */
    private function checkAccountSetOfflineOnboarding($accountSet, $checkDate)
    {
        $totalReminders = 0;
        
        // 查询线下入职且超过30天未上传合同的员工
        $employees = Employee::where('account_set_id', $accountSet->id)
            ->where('is_offline_onboarding', true)
            ->where('contract_uploaded', false)
            ->where('contract_upload_deadline', '<', $checkDate)
            ->where('contract_status', 'active') // 只查询在职员工
            ->get();
        
        $this->info("  找到超期未上传合同的员工: {$employees->count()} 人");
        
        foreach ($employees as $employee) {
            $reminder = $this->createOfflineOnboardingReminder($employee, $accountSet, $checkDate);
            if ($reminder) {
                $totalReminders++;
                $overdueDays = Carbon::parse($employee->contract_upload_deadline)->diffInDays($checkDate);
                $this->info("    员工: {$employee->name} - 已超期 {$overdueDays} 天，生成提醒");
            }
        }
        
        return [
            'account_set_id' => $accountSet->id,
            'account_set_name' => $accountSet->name,
            'total_reminders' => $totalReminders
        ];
    }
    
    /**
     * 创建线下入职合同上传提醒记录
     */
    private function createOfflineOnboardingReminder($employee, $accountSet, $checkDate)
    {
        // 检查今天是否已经创建过提醒（每天创建一条新提醒）
        $existingReminder = ContractReminder::where('employee_id', $employee->id)
            ->where('reminder_type', 'offline_onboarding_contract')
            ->where('reminder_date', $checkDate->format('Y-m-d'))
            ->first();
            
        if ($existingReminder) {
            return null; // 今天已创建，不重复创建
        }
        
        // 获取账套下所有业务人员
        $businessUsers = $this->getBusinessUsers($accountSet->id);
        
        if ($businessUsers->isEmpty()) {
            Log::warning('未找到业务人员', [
                'account_set_id' => $accountSet->id,
                'employee_id' => $employee->id
            ]);
            return null;
        }
        
        // 计算超期天数
        $overdueDays = Carbon::parse($employee->contract_upload_deadline)->diffInDays($checkDate);
        
        $description = "员工 {$employee->name} 于 {$employee->offline_onboarding_date} 线下入职，合同上传截止日期为 {$employee->contract_upload_deadline}，已超期 {$overdueDays} 天，请及时上传合同。";
        
        // 为每个业务人员创建提醒
        $reminders = [];
        foreach ($businessUsers as $user) {
            $reminder = ContractReminder::create([
                'account_set_id' => $accountSet->id,
                'employee_id' => $employee->id,
                'reminder_type' => 'offline_onboarding_contract',
                'employee_name' => $employee->name,
                'contract_upload_deadline' => $employee->contract_upload_deadline,
                'offline_onboarding_date' => $employee->offline_onboarding_date,
                'status' => 'pending',
                'description' => $description,
                'handler_id' => $user->id,
                'handler_name' => $user->name,
                'reminder_date' => $checkDate->format('Y-m-d'),
            ]);
            
            $reminders[] = $reminder;
        }
        
        return count($reminders) > 0 ? $reminders[0] : null;
    }
    
    /**
     * 获取账套下的所有业务人员
     */
    private function getBusinessUsers($accountSetId)
    {
        // 查找角色名称为 employee 的角色（业务人员）
        $businessRole = Role::where('name', 'employee')->first();
        
        if (!$businessRole) {
            // 如果没有找到业务人员角色，返回空集合
            Log::warning('未找到业务人员角色');
            return collect([]);
        }
        
        // 查找该账套下所有拥有业务角色的用户
        $users = User::where('role', $businessRole->name)
            ->whereHas('accountSets', function($query) use ($accountSetId) {
                $query->where('account_sets.id', $accountSetId);
            })
            ->where('is_active', true)
            ->get();
        
        return $users;
    }
}
