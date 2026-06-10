<?php

namespace App\Traits;

use App\Models\ApprovalFlowConfig;

trait ChecksPermission
{
    /**
     * 检查权限，无权限时返回403响应
     */
    protected function checkPermission($permission)
    {
        $user = request()->user();
        
        // super_admin 和 admin 拥有所有权限
        if ($user && in_array($user->role, ['super_admin', 'admin'])) {
            return null;
        }
        
        // 检查用户是否有指定权限（基于角色的权限检查）
        if (!$user || !$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => '您没有执行此操作的权限',
                'required_permission' => $permission,
            ], 403);
        }
        
        return null;
    }
    
    protected function checkApproverPermission($accountSetId = null)
    {
        $user = request()->user();
        
        // super_admin 和 admin 拥有所有权限
        if ($user && in_array($user->role, ['super_admin', 'admin'])) {
            return null;
        }
        
        // 获取账套ID
        if (!$accountSetId) {
            $accountSetId = request()->header('X-Account-Set-Id') ?: request()->input('current_account_set_id');
        }
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套',
            ], 400);
        }
        
        // 检查用户是否是该账套的后续审批节点人员
        $approver = \DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('user_id', $user->id)
            ->whereNotNull('approval_level')
            ->whereBetween('approval_level', [
                ApprovalFlowConfig::APPROVER_MIN_LEVEL,
                ApprovalFlowConfig::MAX_APPROVAL_LEVEL
            ])
            ->first();
        
        if (!$approver) {
            return response()->json([
                'success' => false,
                'message' => '只有后续审批节点人员才能操作工资/考勤依据',
            ], 403);
        }
        
        return null;
    }
}
