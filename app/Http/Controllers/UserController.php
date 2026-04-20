<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * 获取用户列表（用于账套分配等）
     */
    public function index(Request $request)
    {
        $query = User::query();
        
        // 搜索
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // 按角色筛选
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        
        // 按状态筛选
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        // 获取所有用户或分页
        if ($request->get('all') === 'true') {
            $users = $query->orderBy('created_at', 'desc')->get(['id', 'name', 'nickname', 'email', 'role', 'is_active']);
        } else {
            $perPage = $request->get('per_page', 20);
            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);
        }
        
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * 创建用户
     */
    public function store(Request $request)
    {
        // 只有管理员可以创建用户
        if (!in_array($request->user()->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        // 从角色表获取所有有效角色
        $validRoles = \App\Models\Role::pluck('name')->toArray();
        $validRolesStr = implode(',', $validRoles);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'nickname' => 'nullable|string|max:100',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:' . $validRolesStr,
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'nickname' => $request->nickname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
            'avatar' => $request->avatar,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => '用户创建成功',
            'data' => $user
        ]);
    }

    /**
     * 更新用户
     */
    public function update(Request $request, $id)
    {
        // 只有管理员可以更新用户
        if (!in_array($request->user()->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $user = User::findOrFail($id);

        // 从角色表获取所有有效角色
        $validRoles = \App\Models\Role::pluck('name')->toArray();
        $validRolesStr = implode(',', $validRoles);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:191',
            'nickname' => 'nullable|string|max:100',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|in:' . $validRolesStr,
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'can_view_operation_barrage' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['name', 'nickname', 'email', 'role', 'phone', 'avatar', 'is_active', 'can_view_operation_barrage']);
        
        // 如果提供了密码，则更新密码
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => '用户更新成功',
            'data' => $user
        ]);
    }

    /**
     * 删除用户
     */
    public function destroy(Request $request, $id)
    {
        // 只有管理员可以删除用户
        if (!in_array($request->user()->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $user = User::findOrFail($id);

        // 不能删除自己
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => '不能删除自己的账号'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => '用户删除成功'
        ]);
    }

    /**
     * 重置密码
     */
    public function resetPassword(Request $request, $id)
    {
        // 只有管理员可以重置密码
        if (!in_array($request->user()->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => '密码重置成功'
        ]);
    }

    /**
     * 更新用户状态（启用/禁用）
     */
    public function updateStatus(Request $request, $id)
    {
        // 只有管理员可以修改用户状态
        if (!in_array($request->user()->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $user = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'is_active' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 转换为布尔值
        $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            $isActive = (bool) $request->is_active;
        }

        $user->update([
            'is_active' => $isActive
        ]);

        return response()->json([
            'success' => true,
            'message' => '用户状态更新成功',
            'data' => $user
        ]);
    }

    /**
     * 更新用户当前选择的账套
     */
    public function updateCurrentAccountSet(Request $request)
    {
        $request->validate([
            'account_set_id' => 'required|exists:account_sets,id'
        ]);

        $user = $request->user();
        $user->current_account_set_id = $request->account_set_id;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '账套切换成功',
            'data' => [
                'current_account_set_id' => $user->current_account_set_id
            ]
        ]);
    }

    /**
     * 获取用户当前选择的账套
     */
    public function getCurrentAccountSet(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'current_account_set_id' => $user->current_account_set_id
            ]
        ]);
    }

    /**
     * 更新当前用户个人资料
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nickname' => 'nullable|string|max:100',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = [];
        if ($request->has('nickname')) {
            $updateData['nickname'] = $request->nickname;
        }
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => '个人资料更新成功',
            'data' => $user
        ]);
    }

    /**
     * 修改当前用户密码
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 验证原密码
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '原密码错误'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => '密码修改成功'
        ]);
    }
}
