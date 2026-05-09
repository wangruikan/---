<?php

namespace App\Http\Controllers;

use App\Models\AssessmentRecord;
use App\Models\ApprovalInstance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProcessRecordController extends Controller
{
    /**
     * 获取所有流程记录（仅限管理员）
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $accountSetId = $request->input('account_set_id');
        
        // 检查用户权限：只有第2、3、4个审批节点的用户才能访问
        if (!$this->canViewProcessRecords($user, $accountSetId)) {
            return response()->json([
                'success' => false,
                'message' => '您没有权限查看流程记录，只有审批流程第2、3、4节点的用户才能访问'
            ], 403);
        }

        try {
            // 查询所有流程实例（approval_instances表）- 查看所有账套的流程记录
            $query = \App\Models\ApprovalInstance::with(['creator', 'records', 'accountSet']);
            
            // 如果提供了账套ID，则只查询该账套的数据
            if ($accountSetId) {
                $query->where('account_set_id', $accountSetId);
            }

            // 添加筛选条件
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('business_type') && $request->business_type !== '') {
                $query->where('business_type', $request->business_type);
            }

            if ($request->has('current_step') && $request->current_step !== '') {
                $query->where('current_step', $request->current_step);
            }

            if ($request->has('date_range') && is_array($request->date_range) && count($request->date_range) === 2) {
                $query->whereBetween('created_at', $request->date_range);
            }

            // 分页
            $perPage = $request->input('per_page', 15);
            $records = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // 格式化数据 - approval_instances表
            $formattedRecords = $records->getCollection()->map(function ($instance) {
                // 获取当前待审批记录
                $currentRecord = $instance->getCurrentPendingRecord();
                
                // 获取所有审批记录的摘要
                $approvalSummary = $instance->records->map(function($record) {
                    return [
                        'step' => $record->step_order,
                        'name' => $record->step_name,
                        'approver' => $record->approver_name,
                        'status' => $record->status,
                    ];
                });
                
                return [
                    'id' => $instance->id,
                    'account_set_id' => $instance->account_set_id,
                    'account_set_name' => $instance->accountSet ? $instance->accountSet->name : '未知账套',
                    'business_type' => $instance->business_type,
                    'business_type_text' => $this->getBusinessTypeText($instance->business_type),
                    'business_id' => $instance->business_id,
                    'current_step' => $instance->current_step,
                    'total_steps' => $instance->total_steps,
                    'status' => $instance->status,
                    'status_text' => $this->getInstanceStatusText($instance->status),
                    'current_approver' => $currentRecord ? $currentRecord->approver_name : '无',
                    'approval_summary' => $approvalSummary,
                    'created_by' => $instance->created_by,
                    'creator_name' => $instance->creator ? $instance->creator->name : '未知',
                    'approved_at' => $instance->completed_at ? $instance->completed_at->format('Y-m-d H:i:s') : null,
                    'completed_at' => $instance->completed_at ? $instance->completed_at->format('Y-m-d H:i:s') : null,
                    'created_at' => $instance->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $instance->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $formattedRecords,
                    'pagination' => [
                        'current_page' => $records->currentPage(),
                        'last_page' => $records->lastPage(),
                        'per_page' => $records->perPage(),
                        'total' => $records->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取流程记录失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查用户是否可以查看流程记录
     * 只有审批流程中第2、3、4个节点的用户才能查看
     */
    private function canViewProcessRecords($user, $accountSetId = null)
    {
        // 超级管理员可以查看
        if ($user->role === 'super_admin') {
            return true;
        }

        // 备用方案：通过用户角色判断
        $allowedRoles = ['admin', 'manager', 'senior_manager'];
        if (in_array($user->role, $allowedRoles)) {
            return true;
        }

        // 如果没有提供账套ID，检查用户在任意账套中是否有审批权限
        if (!$accountSetId) {
            $accountSetId = request()->input('account_set_id');
        }

        if ($accountSetId) {
            // 检查用户在指定账套下的审批级别
            $approver = \DB::table('account_set_users')
                ->where('account_set_id', $accountSetId)
                ->where('user_id', $user->id)
                ->whereIn('approval_level', [1, 2, 3, 4])
                ->first();
            
            if ($approver) {
                return true;
            }
        } else {
            // 没有账套ID时，检查用户在任意账套中是否有2/3/4级别权限
            $approver = \DB::table('account_set_users')
                ->where('user_id', $user->id)
                ->whereIn('approval_level', [1, 2, 3, 4])
                ->first();
            
            if ($approver) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查用户是否有权限访问流程记录管理
     */
    public function checkAccess(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('account_set_id');

        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        // 检查用户是否是该账套的审批人（审批级别 2、3、4）
        $approver = \DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('user_id', $user->id)
            ->whereIn('approval_level', [1, 2, 3, 4])
            ->first();

        return response()->json([
            'success' => true,
            'has_access' => $approver !== null,
            'approval_level' => $approver ? $approver->approval_level : null,
            'approval_level_name' => $approver ? $approver->approval_level_name : null
        ]);
    }

    /**
     * 获取状态文本
     */
    private function getStatusText($status)
    {
        switch ($status) {
            case 'pending':
                return '待处理';
            case 'approved':
                return '已通过';
            case 'rejected':
                return '已拒绝';
            case 'waiting':
                return '等待中';
            case 'completed':
                return '已完成';
            default:
                return '未知状态';
        }
    }

    /**
     * 获取业务类型文本
     */
    private function getBusinessTypeText($businessType)
    {
        switch ($businessType) {
            case 'employee_contract':
                return '员工合同';
            case 'invoice_application':
                return '发票申请';
            case 'salary_approval':
                return '工资表审批';
            case 'payment_application':
                return '付款申请';
            case 'reimbursement':
                return '报销申请';
            case 'insurance_summary':
                return '保险汇总';
            case 'attendance_sheet':
                return '考勤申请';
            default:
                return $businessType ?: '未知业务';
        }
    }

    /**
     * 获取流程实例状态文本
     */
    private function getInstanceStatusText($status)
    {
        switch ($status) {
            case 'pending':
                return '审批中';
            case 'approved':
                return '已通过';
            case 'rejected':
                return '已拒绝';
            case 'cancelled':
                return '已取消';
            case 'completed':
                return '已完成';
            default:
                return '未知状态';
        }
    }

    /**
     * 获取流程统计信息
     */
    public function getStats(Request $request)
    {
        $user = Auth::user();
        
        $accountSetId = $request->input('account_set_id');
        
        // 检查用户权限
        if (!$this->canViewProcessRecords($user, $accountSetId)) {
            return response()->json([
                'success' => false,
                'message' => '您没有权限查看流程记录统计'
            ], 403);
        }

        try {
            // 统计流程实例数据 - 支持所有账套或指定账套
            $baseQuery = \App\Models\ApprovalInstance::query();
            
            if ($accountSetId) {
                $baseQuery->where('account_set_id', $accountSetId);
            }
            
            $stats = [
                'total' => (clone $baseQuery)->count(),
                'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
                'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
                'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
                'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取统计信息失败：' . $e->getMessage()
            ], 500);
        }
    }
}
