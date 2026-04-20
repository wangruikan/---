<?php

namespace App\Http\Controllers;

use App\Models\AccountSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountSetController extends Controller
{
    /**
     * 获取当前用户可访问的账套列表
     */
    public function getMyAccountSets(Request $request)
    {
        $user = $request->user();
        
        // 管理员可以访问所有账套
        if ($user && $user->role === 'admin') {
            $accountSets = AccountSet::where('status', 'active')
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // 非管理员只能访问自己所属的账套
            $accountSets = AccountSet::whereHas('users', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'active')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        }
        
        return response()->json([
            'success' => true,
            'data' => $accountSets
        ]);
    }

    /**
     * 获取套账列表
     */
    public function index(Request $request)
    {
        // 检查权限：只有管理员可以访问
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权访问，只有管理员可以管理套账'
            ], 403);
        }

        $query = AccountSet::with('creator');
        
        // 状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // 搜索
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }
        
        $perPage = $request->get('per_page', 20);
        $accountSets = $query->orderBy('is_default', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $accountSets->items(),
            'total' => $accountSets->total(),
            'current_page' => $accountSets->currentPage(),
            'per_page' => $accountSets->perPage(),
            'last_page' => $accountSets->lastPage()
        ]);
    }

    /**
     * 创建套账
     */
    public function store(Request $request)
    {
        // 检查权限：只有管理员可以创建
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权操作，只有管理员可以创建套账'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'code' => 'required|string|max:50|unique:account_sets,code',
            'description' => 'nullable|string',
            'company_name' => 'nullable|string|max:191',
            'tax_number' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 检查代码是否已存在
        if (AccountSet::where('code', $request->code)->exists()) {
            return response()->json([
                'success' => false,
                'message' => '套账代码已存在，请使用其他代码'
            ], 422);
        }

        $accountSet = AccountSet::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'company_name' => $request->company_name,
            'tax_number' => $request->tax_number,
            'contact_person' => $request->contact_person,
            'contact_phone' => $request->contact_phone,
            'address' => $request->address,
            'status' => 'active',
            'is_default' => $request->is_default ?? false,
            'created_by' => $request->user()->id,
        ]);

        // 如果设置为默认，取消其他默认套账
        if ($request->is_default) {
            AccountSet::where('id', '!=', $accountSet->id)
                ->update(['is_default' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => '套账创建成功',
            'data' => $accountSet->load('creator')
        ]);
    }

    /**
     * 获取套账详情
     */
    public function show(Request $request, $id)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权访问'
            ], 403);
        }

        $accountSet = AccountSet::with(['creator', 'users'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $accountSet
        ]);
    }

    /**
     * 更新套账
     */
    public function update(Request $request, $id)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $accountSet = AccountSet::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:191',
            'code' => 'sometimes|required|string|max:50|unique:account_sets,code,' . $id,
            'description' => 'nullable|string',
            'company_name' => 'nullable|string|max:191',
            'tax_number' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'status' => 'in:active,inactive,archived',
            'is_default' => 'boolean',
            'base_adjustment_months' => 'nullable|array',
            'base_adjustment_months.*' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $accountSet->update($request->only([
            'name', 'code', 'description', 'company_name', 'tax_number',
            'contact_person', 'contact_phone', 'address', 'status', 'base_adjustment_months'
        ]));

        // 如果设置为默认，取消其他默认套账
        if ($request->has('is_default') && $request->is_default) {
            AccountSet::where('id', '!=', $accountSet->id)
                ->update(['is_default' => false]);
            $accountSet->update(['is_default' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => '套账更新成功',
            'data' => $accountSet->load('creator')
        ]);
    }

    /**
     * 删除套账
     */
    public function destroy(Request $request, $id)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $accountSet = AccountSet::findOrFail($id);
        
        // 检查是否为默认套账
        if ($accountSet->is_default) {
            return response()->json([
                'success' => false,
                'message' => '默认套账不能删除'
            ], 422);
        }

        $accountSet->delete();

        return response()->json([
            'success' => true,
            'message' => '套账删除成功'
        ]);
    }

    /**
     * 设置为默认套账
     */
    public function setDefault(Request $request, $id)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $accountSet = AccountSet::findOrFail($id);
        
        if ($accountSet->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => '只有启用状态的套账才能设置为默认'
            ], 422);
        }

        $accountSet->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => '已设置为默认套账'
        ]);
    }

    /**
     * 归档套账
     */
    public function archive(Request $request, $id)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $accountSet = AccountSet::findOrFail($id);
        
        try {
            $accountSet->archive();
            
            return response()->json([
                'success' => true,
                'message' => '套账已归档'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * 获取统计信息
     */
    public function getStatistics(Request $request)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权访问'
            ], 403);
        }

        $statistics = [
            'total' => AccountSet::count(),
            'active' => AccountSet::where('status', 'active')->count(),
            'inactive' => AccountSet::where('status', 'inactive')->count(),
            'archived' => AccountSet::where('status', 'archived')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * 分配管理员到账套
     */
    public function assignUsers(Request $request, $id)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $validator = \Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'role' => 'in:owner,admin,viewer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $accountSet = AccountSet::findOrFail($id);
        $role = $request->role ?? 'admin';

        // 批量添加用户
        foreach ($request->user_ids as $userId) {
            // 检查是否已存在
            $existing = \DB::table('account_set_users')
                ->where('account_set_id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$existing) {
                \DB::table('account_set_users')->insert([
                    'account_set_id' => $id,
                    'user_id' => $userId,
                    'role' => $role,
                    'is_default' => 0, // 默认不是默认账套
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // 更新用户表的 account_set_id 字段
            \DB::table('users')
                ->where('id', $userId)
                ->update(['account_set_id' => $id]);
        }

        return response()->json([
            'success' => true,
            'message' => '管理员分配成功'
        ]);
    }

    /**
     * 移除账套管理员
     */
    public function removeUser(Request $request, $id, $userId)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        \DB::table('account_set_users')
            ->where('account_set_id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => '已移除管理员'
        ]);
    }

    /**
     * 设置用户的审批级别
     */
    public function setApprovalLevel(Request $request, $id, $userId)
    {
        // 检查权限
        if ($request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '无权操作'
            ], 403);
        }

        $validator = \Validator::make($request->all(), [
            'approval_level' => 'nullable|integer|between:1,4',
            'approval_level_name' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 检查用户是否在该账套中
        $accountSetUser = \DB::table('account_set_users')
            ->where('account_set_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$accountSetUser) {
            return response()->json([
                'success' => false,
                'message' => '用户不在该账套中'
            ], 404);
        }

        // 更新审批级别
        \DB::table('account_set_users')
            ->where('account_set_id', $id)
            ->where('user_id', $userId)
            ->update([
                'approval_level' => $request->approval_level,
                'approval_level_name' => $request->approval_level_name,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => '审批级别设置成功'
        ]);
    }

    /**
     * 获取账套的管理员列表
     */
    public function getUsers(Request $request, $id)
    {
        // 检查权限：管理员可以查看所有用户，普通用户只能查看自己所属账套的用户列表
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '未登录'
            ], 401);
        }

        // 如果不是管理员，检查用户是否属于该账套
        if ($user->role !== 'admin' && $user->role !== 'super_admin') {
            $hasAccess = \DB::table('account_set_users')
                ->where('account_set_id', $id)
                ->where('user_id', $user->id)
                ->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权访问该账套'
                ], 403);
            }
        }

        $users = \DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $id)
            ->select(
                'users.id',
                'users.name',
                'users.nickname',
                'users.email',
                'users.role as system_role',
                'account_set_users.role as account_set_role',
                'account_set_users.is_default',
                'account_set_users.approval_level',
                'account_set_users.approval_level_name'
            )
            ->orderBy('account_set_users.approval_level')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }


    /**
     * 设置用户的默认账套
     */
    public function setUserDefault(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'account_set_id' => 'required|exists:account_sets,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $request->user()->id;
        $accountSetId = $request->account_set_id;

        // 检查用户是否有权访问此账套
        $hasAccess = \DB::table('account_set_users')
            ->where('user_id', $userId)
            ->where('account_set_id', $accountSetId)
            ->exists();

        if (!$hasAccess && $request->user() && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => '您没有访问此账套的权限'
            ], 403);
        }

        // 取消该用户的所有默认账套
        \DB::table('account_set_users')
            ->where('user_id', $userId)
            ->update(['is_default' => 0]);

        // 设置新的默认账套
        \DB::table('account_set_users')
            ->where('user_id', $userId)
            ->where('account_set_id', $accountSetId)
            ->update(['is_default' => 1]);

        return response()->json([
            'success' => true,
            'message' => '默认账套设置成功'
        ]);
    }
}
