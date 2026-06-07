<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ApprovalInstance;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $stampOptions = $this->resolveApprovalStampOptions($request, $accountSetId);

        DB::beginTransaction();
        try {
            // 线下入职不需要在提交时更新日期字段
            // 这些字段会在审批通过后由 ApprovalService 自动设置

            $instance = app(ApprovalService::class)->createApprovalInstanceWithApprovedInitiator(
                $accountSetId,
                'offline_onboarding',
                $employee->id,
                $user->id,
                $user->name,
                [],
                'offline',
                '线下入职申请，经办自动通过',
                $stampOptions
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '线下入职审批已提交',
                'data' => [
                    'employee' => $employee->fresh(),
                    'approval_instance' => $instance->load('records')
                ]
            ]);

        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            DB::rollBack();
            throw $e;
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
