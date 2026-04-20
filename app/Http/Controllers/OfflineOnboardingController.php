<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * 线下入职控制器
 * 处理员工线下入职（先办理社保，后补合同）的流程
 */
class OfflineOnboardingController extends Controller
{
    /**
     * 发起线下入职审批
     * 
     * @param Request $request
     * @param int $id 员工ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitOfflineOnboarding(Request $request, $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '员工不存在'
            ], 404);
        }

        // 检查员工是否已经在职
        if ($employee->contract_status === 'active') {
            return response()->json([
                'success' => false,
                'message' => '该员工已经在职，无需重复入职'
            ], 400);
        }

        // 检查是否已有待审批的线下入职申请
        $existingPendingApproval = ApprovalInstance::where('business_type', 'offline_onboarding')
            ->where('business_id', $id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPendingApproval) {
            return response()->json([
                'success' => false,
                'message' => '已有待审批的线下入职申请，请勿重复提交'
            ], 400);
        }

        $user = Auth::user();
        $accountSetId = $employee->account_set_id;

        DB::beginTransaction();
        try {
            // 线下入职不需要在提交时更新日期字段
            // 这些字段会在审批通过后由 ApprovalService 自动设置

            // 获取审批人配置（跳过经办，从第二个审批节点开始）
            $approvers = DB::table('account_set_users')
                ->where('account_set_id', $accountSetId)
                ->where('approval_level', '>', 1) // 跳过经办（级别1）
                ->orderBy('approval_level')
                ->get();

            if ($approvers->isEmpty()) {
                throw new \Exception('未找到审批人员配置');
            }

            // 创建审批实例
            $instance = ApprovalInstance::create([
                'account_set_id' => $accountSetId,
                'business_type' => 'offline_onboarding',
                'business_id' => $employee->id,
                'current_step' => 2, // 从第二个审批节点开始
                'total_steps' => $approvers->count() + 1, // 包括经办
                'status' => 'pending',
                'created_by' => $user->id,
                'stamp_method' => 'offline', // 默认线下盖章
            ]);

            // 创建经办节点记录（自动通过）
            ApprovalRecord::create([
                'instance_id' => $instance->id,
                'step_order' => 1,
                'step_name' => '经办',
                'approver_id' => $user->id,
                'approver_name' => $user->name,
                'status' => 'approved',
                'comment' => '线下入职申请，经办自动通过',
                'approved_at' => now(),
            ]);

            // 为第一个审批人创建待办记录
            $firstApprover = $approvers->first();
            $approverUser = User::find($firstApprover->user_id);
            
            ApprovalRecord::create([
                'instance_id' => $instance->id,
                'step_order' => 2,
                'step_name' => $firstApprover->approval_level_name,
                'approver_id' => $firstApprover->user_id,
                'approver_name' => $approverUser->name,
                'status' => 'pending',
                'comment' => null,
                'approved_at' => null,
            ]);

            // 如果有更多审批级别，继续创建记录
            $stepOrder = 3;
            foreach ($approvers->skip(1) as $approver) {
                $approverUser = User::find($approver->user_id);
                ApprovalRecord::create([
                    'instance_id' => $instance->id,
                    'step_order' => $stepOrder,
                    'step_name' => $approver->approval_level_name,
                    'approver_id' => $approver->user_id,
                    'approver_name' => $approverUser->name,
                    'status' => 'waiting',
                    'comment' => null,
                    'approved_at' => null,
                ]);
                $stepOrder++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '线下入职审批已提交',
                'data' => [
                    'employee' => $employee->fresh(),
                    'approval_instance' => $instance->load('records')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('提交线下入职审批失败', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '提交失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取待上传合同的员工列表（超过30天未上传）
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingContractUpload(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $user->current_account_set_id;
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '未选择账套'
            ], 400);
        }

        // 查询线下入职且超过30天未上传合同的员工
        $employees = Employee::where('account_set_id', $accountSetId)
            ->where('is_offline_onboarding', true)
            ->where('contract_uploaded', false)
            ->where('contract_upload_deadline', '<', Carbon::now())
            ->where('contract_status', 'active') // 只查询在职员工
            ->with(['projects'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employees->map(function($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'id_number' => $employee->id_number,
                    'phone' => $employee->phone,
                    'offline_onboarding_date' => $employee->offline_onboarding_date,
                    'contract_upload_deadline' => $employee->contract_upload_deadline,
                    'overdue_days' => Carbon::parse($employee->contract_upload_deadline)->diffInDays(Carbon::now()),
                    'projects' => $employee->projects->pluck('name'),
                ];
            })
        ]);
    }

    /**
     * 标记合同已上传
     * 
     * @param Request $request
     * @param int $id 员工ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function markContractUploaded(Request $request, $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '员工不存在'
            ], 404);
        }

        if (!$employee->is_offline_onboarding) {
            return response()->json([
                'success' => false,
                'message' => '该员工不是线下入职，无需标记'
            ], 400);
        }

        $employee->update([
            'contract_uploaded' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => '已标记合同已上传',
            'data' => $employee
        ]);
    }
}
