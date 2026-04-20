<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * 获取所有权限（按模块分组）
     */
    public function index()
    {
        $groupedPermissions = Permission::getGroupedPermissions();

        return response()->json([
            'success' => true,
            'data' => $groupedPermissions
        ]);
    }

    /**
     * 获取当前用户的权限列表
     */
    public function myPermissions(Request $request)
    {
        $user = $request->user();
        // 统一由后端计算“角色权限 + 用户直配权限”的并集
        $permissionKeys = $user->getPermissionKeys();

        // 额外返回一些调试信息，方便排查“为什么权限列表为空/不完整”
        // 这些字段不影响前端原有逻辑（前端只用 data.permissions / data.is_admin）
        $roleModel = $user ? $user->roleModel() : null;
        $rolePermissionCount = $roleModel ? $roleModel->permissions()->count() : 0;
        $userDirectPermissionCount = $user ? $user->permissions()->count() : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $permissionKeys,
                'is_admin' => $user->role === 'admin',
                'debug' => [
                    'user_id' => $user->id ?? null,
                    'user_role' => $user->role ?? null,
                    'role_model_found' => (bool) $roleModel,
                    'role_model_id' => $roleModel->id ?? null,
                    'role_model_name' => $roleModel->name ?? null,
                    'role_permission_count' => $rolePermissionCount,
                    'user_direct_permission_count' => $userDirectPermissionCount,
                    'merged_permission_count' => is_array($permissionKeys) ? count($permissionKeys) : 0,
                ],
            ]
        ]);
    }

    /**
     * 获取指定用户的权限
     */
    public function getUserPermissions(Request $request, $userId)
    {
        // 只有admin可以查看其他用户的权限
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '没有权限执行此操作'
            ], 403);
        }

        $user = User::findOrFail($userId);
        
        // 获取用户已有的权限ID列表
        $userPermissionIds = $user->permissions()->pluck('permissions.id')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role
                ],
                'permission_ids' => $userPermissionIds,
                'is_admin' => $user->role === 'admin'
            ]
        ]);
    }

    /**
     * 更新用户权限
     */
    public function updateUserPermissions(Request $request, $userId)
    {
        // 只有admin可以修改权限
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '没有权限执行此操作'
            ], 403);
        }

        $user = User::findOrFail($userId);

        // admin用户的权限不能修改
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => '管理员拥有所有权限，无需设置'
            ], 400);
        }

        $request->validate([
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        // 同步用户权限（空数组表示移除所有权限）
        $user->permissions()->sync($request->permission_ids ?? []);

        return response()->json([
            'success' => true,
            'message' => '权限更新成功'
        ]);
    }

    /**
     * 获取所有用户及其权限概览（用于权限管理页面）
     */
    public function getUsersWithPermissions(Request $request)
    {
        // 只有admin可以访问
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '没有权限执行此操作'
            ], 403);
        }

        $users = User::where('role', '!=', 'admin')
            ->with(['permissions'])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'permission_count' => $user->permissions->count(),
                    'permission_ids' => $user->permissions->pluck('id')->toArray()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * 批量设置用户权限（快捷操作）
     */
    public function batchSetPermissions(Request $request)
    {
        // 只有admin可以操作
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '没有权限执行此操作'
            ], 403);
        }

        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
            'action' => 'required|in:add,remove,set'
        ]);

        $users = User::whereIn('id', $request->user_ids)
            ->where('role', '!=', 'admin')
            ->get();

        foreach ($users as $user) {
            switch ($request->action) {
                case 'add':
                    // 添加权限（不移除现有权限）
                    $user->permissions()->syncWithoutDetaching($request->permission_ids);
                    break;
                case 'remove':
                    // 移除指定权限
                    $user->permissions()->detach($request->permission_ids);
                    break;
                case 'set':
                    // 设置为指定权限（替换）
                    $user->permissions()->sync($request->permission_ids);
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'message' => '批量权限操作成功'
        ]);
    }
}
