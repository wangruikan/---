<?php

namespace App\Traits;

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
    
    /**
     * 检查是否是后面3个审批节点的审批人（第2、3、4个审批人）
     * 用于工资依据和考勤依据的权限检查
     */
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
        
        // 检查用户是否是该账套的审批人，且审批级别为2、3、4
        $approver = \DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('user_id', $user->id)
            ->whereNotNull('approval_level')
            ->whereIn('approval_level', [2, 3, 4])
            ->first();
        
        if (!$approver) {
            return response()->json([
                'success' => false,
                'message' => '只有第2、3、4审批节点的审批人才能操作工资/考勤依据',
            ], 403);
        }
        
        return null;
    }
}
