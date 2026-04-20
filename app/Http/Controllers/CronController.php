<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Models\Employee;
use App\Models\InsuranceChange;
use App\Models\AssessmentRecord;
use App\Models\SystemSetting;
use Carbon\Carbon;

class CronController extends Controller
{
    // 安全密钥（可以在.env中配置）
    private function validateCronToken(Request $request)
    {
        $token = $request->input('token') ?? $request->header('X-Cron-Token');
        $expectedToken = env('CRON_TOKEN', 'default-cron-token-please-change');
        
        return $token === $expectedToken;
    }

    // 检查参保入职超期情况
    public function checkInsuranceDeadlines(Request $request)
    {
        // 验证token（防止恶意访问）
        if (!$this->validateCronToken($request)) {
            return response()->json([
                'success' => false,
                'message' => '无效的访问令牌'
            ], 403);
        }

        try {
            $today = Carbon::today();
            $checkCount = 0;
            $recordCount = 0;
            $updateCount = 0;

            // 查找所有参保完成时间已到期的员工
            $employees = Employee::whereNotNull('insurance_completion_time')
                ->where('insurance_completion_time', '<=', $today)
                ->get();

            foreach ($employees as $employee) {
                $checkCount++;

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

                // 如果需要记录，检查是否已存在今天的记录
                if ($shouldRecord) {
                    // 获取经办人信息（从账套设置中获取）
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
                        $recordCount++;
                    } else {
                        // 更新现有记录的状态
                        $existingRecord->updateStatus();
                        $updateCount++;
                    }
                }
            }

            // 更新所有待处理记录的状态
            AssessmentRecord::updateAllPendingStatus();

            return response()->json([
                'success' => true,
                'message' => '检查完成',
                'data' => [
                    'check_time' => Carbon::now()->toDateTimeString(),
                    'checked_employees' => $checkCount,
                    'new_records' => $recordCount,
                    'updated_records' => $updateCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '检查失败：' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], 500);
        }
    }

    // 获取账套的经办人
    private function getAccountSetHandler($accountSetId)
    {
        $setting = SystemSetting::where('account_set_id', $accountSetId)
            ->where('key', 'handler_user_id')
            ->first();

        if (!$setting || !$setting->value) {
            return null;
        }

        $handlerUser = \App\Models\User::find($setting->value);

        if (!$handlerUser) {
            return null;
        }

        return [
            'id' => $handlerUser->id,
            'name' => $handlerUser->name
        ];
    }
}
