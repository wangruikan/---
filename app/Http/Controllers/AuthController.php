<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 用户登录
     */
    public function login(Request $request)
    {
        // 验证请求数据
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // 尝试通过邮箱或用户名查找用户
        $user = User::where('email', $request->username)
            ->orWhere('name', $request->username)
            ->first();

        // 验证用户是否存在及密码是否正确
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '用户名或密码错误'
            ], 401);
        }

        // 检查用户是否被激活
        if (isset($user->is_active) && !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => '账号已被禁用，请联系管理员'
            ], 403);
        }

        // 生成 API Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->name,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null,
                    'avatar' => $user->avatar ?? null,
                    'role' => $user->role,
                    'is_active' => $user->is_active ?? true
                ]
            ],
            'message' => '登录成功'
        ]);
    }

    /**
     * 用户登出
     */
    public function logout(Request $request)
    {
        // 删除当前用户的所有 tokens
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => '登出成功'
        ]);
    }

    /**
     * 获取当前登录用户信息
     */
    public function user(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }
        
        $currentAccountSetId = $request->get('current_account_set_id', $user->account_set_id ?? null);

        // 检查用户在当前账套中的审批级别
        $approvalLevel = null;
        $approvalLevelName = null;
        $isHandler = false;
        
        if ($currentAccountSetId) {
            $accountSetUser = DB::table('account_set_users')
                ->where('account_set_id', $currentAccountSetId)
                ->where('user_id', $user->id)
                ->first();
            
            if ($accountSetUser) {
                $approvalLevel = $accountSetUser->approval_level;
                $approvalLevelName = $accountSetUser->approval_level_name;
                // 检查是否为经办人员（approval_level = 1）
                $isHandler = ($approvalLevel == 1);
            }
        }

        // 获取用户角色的可见菜单
        $visibleMenus = null;
        if ($user->role_id) {
            // 优先使用 role_id
            $role = \App\Models\Role::find($user->role_id);
            if ($role) {
                $visibleMenus = $role->visible_menus;
            }
        } elseif ($user->role) {
            // 如果没有 role_id，通过 role 字段名称查找
            $role = \App\Models\Role::where('name', $user->role)->first();
            if ($role) {
                $visibleMenus = $role->visible_menus;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'avatar' => $user->avatar ?? null,
                'role' => $user->role,
                'role_id' => $user->role_id ?? null,
                'visible_menus' => $visibleMenus,
                'is_active' => $user->is_active ?? true,
                'is_handler' => $isHandler,
                'account_set_id' => $user->account_set_id ?? null,
                'approval_level' => $approvalLevel,
                'approval_level_name' => $approvalLevelName,
                'can_view_operation_barrage' => $user->can_view_operation_barrage ?? false,
            ]
        ]);
    }

    /**
     * 修改密码
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $user = $request->user();

        // 验证旧密码
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '原密码错误'
            ], 400);
        }

        // 更新密码
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '密码修改成功'
        ]);
    }

    /**
     * 更新用户资料
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nickname' => 'sometimes|string|max:100',
            'email' => 'sometimes|nullable|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
        ]);

        $updateData = [];
        if ($request->has('nickname')) {
            $updateData['nickname'] = $request->nickname;
        }
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        if ($request->has('phone')) {
            $updateData['phone'] = $request->phone;
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $user->name,
                'name' => $user->name,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'avatar' => $user->avatar ?? null,
                'role' => $user->role,
            ],
            'message' => '资料更新成功'
        ]);
    }
}


