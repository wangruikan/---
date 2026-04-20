<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Employee;
use App\Models\InsuranceChange;
use App\Models\AssessmentRecord;
use Carbon\Carbon;

class DailyAssessmentCheck
{
    /**
     * 每天第一次访问时检查考核
     */
    public function handle(Request $request, Closure $next)
    {
        // 只在已登录且有账套的情况下执行
        $user = auth()->user();
        if (!$user) {
            return $next($request);
        }

        // 获取当前日期
        $today = Carbon::today()->toDateString();
        $cacheKey = 'daily_assessment_checked_' . $today;

        // 检查今天是否已经执行过检查
        if (!Cache::has($cacheKey)) {
            try {
                // 执行检查
                $this->performDailyCheck();

                // 设置缓存，标记今天已检查（缓存到今天结束）
                $expiresAt = Carbon::tomorrow();
                Cache::put($cacheKey, true, $expiresAt);

                \Log::info("每日考核检查已执行", [
                    'date' => $today,
                    'user_id' => $user->id
                ]);
            } catch (\Exception $e) {
                \Log::error("每日考核检查失败", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $next($request);
    }

    /**
     * 执行每日检查
     */
    private function performDailyCheck()
    {
        $today = Carbon::today();

        // 检查 employees 表是否有 insurance_completion_time 字段
        try {
            $hasColumn = \Schema::hasColumn('employees', 'insurance_completion_time');
            if (!$hasColumn) {
                \Log::info('employees表中没有insurance_completion_time字段，跳过每日考核检查');
                return;
            }
        } catch (\Exception $e) {
            \Log::error('检查insurance_completion_time字段失败', ['error' => $e->getMessage()]);
            return;
        }

        // 查找所有参保完成时间已到期的员工
        $employees = Employee::whereNotNull('insurance_completion_time')
            ->where('insurance_completion_time', '<=', $today)
            ->get();

        foreach ($employees as $employee) {
            // 检查该员工在增减模块中的状态
            $insuranceChange = InsuranceChange::where('employee_id', $employee->id)
                ->where('account_set_id', $employee->account_set_id)
                ->first();

            // 判断是否需要记录考核
            $shouldRecord = false;
            $reason = '';

            if (!$insuranceChange) {
                $shouldRecord = true;
                $reason = '未创建增减记录';
            } elseif ($insuranceChange->status !== 'completed') {
                $shouldRecord = true;
                $reason = '状态：' . ($insuranceChange->status === 'pending' ? '待处理' : $insuranceChange->status);
            }

            // 如果需要记录考核
            if ($shouldRecord) {
                // 获取经办人信息
                $handler = $this->getAccountSetHandler($employee->account_set_id);

                if (!$handler) {
                    continue;
                }

                // 检查是否已存在记录
                $existingRecord = AssessmentRecord::where('account_set_id', $employee->account_set_id)
                    ->where('business_type', 'insurance_enrollment')
                    ->where('business_id', $employee->id)
                    ->where('deadline_date', $employee->insurance_completion_time)
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
                        'deadline_date' => $employee->insurance_completion_time,
                        'remark' => $reason
                    ]);

                    $record->updateStatus();
                } else {
                    // 更新现有记录的状态
                    $existingRecord->updateStatus();
                }
            }
        }

        // 更新所有待处理记录的状态（重新计算超期天数）
        AssessmentRecord::updateAllPendingStatus();
    }

    /**
     * 获取账套的第一个审批人（即经办人）
     */
    private function getAccountSetHandler($accountSetId)
    {
        // 经办人 = 第一级审批人（approval_level最小的）
        $firstApprover = \DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->whereNotNull('account_set_users.approval_level')
            ->orderBy('account_set_users.approval_level')
            ->select(
                'users.id as user_id',
                'users.name as user_name'
            )
            ->first();

        if (!$firstApprover) {
            return null;
        }

        return [
            'id' => $firstApprover->user_id,
            'name' => $firstApprover->user_name
        ];
    }
}
