<?php

namespace App\Http\Controllers;

use App\Models\Blacklist;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlacklistController extends Controller
{
    /**
     * 获取黑名单列表
     */
    public function index(Request $request)
    {
        $query = Blacklist::with('creator:id,name');

        // 搜索
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $blacklist = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $blacklist
        ]);
    }

    /**
     * 添加到黑名单
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_number' => 'required|string|size:18|unique:blacklist,id_number',
            'name' => 'required|string|max:255',
            'reason' => 'required|string'
        ]);

        DB::beginTransaction();
        try {
            // 添加到黑名单
            $blacklist = Blacklist::create([
                'id_number' => $request->id_number,
                'name' => $request->name,
                'reason' => $request->reason,
                'created_by' => $request->user()->id
            ]);

            // 终止所有账套中该身份证号的在职员工
            $terminatedCount = Employee::where('id_number', $request->id_number)
                ->where('contract_status', 'active')
                ->update([
                    'contract_status' => 'terminated',
                    'termination_date' => now(),
                    'termination_reason' => '已加入黑名单：' . $request->reason
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '已添加到黑名单' . ($terminatedCount > 0 ? "，并终止了 {$terminatedCount} 个账套中的在职状态" : ''),
                'data' => $blacklist,
                'terminated_count' => $terminatedCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '添加黑名单失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 从黑名单移除
     */
    public function destroy($id)
    {
        try {
            $blacklist = Blacklist::findOrFail($id);
            $blacklist->delete();

            return response()->json([
                'success' => true,
                'message' => '已从黑名单移除'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '移除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查身份证号是否在黑名单中
     */
    public function check(Request $request)
    {
        $request->validate([
            'id_number' => 'required|string|size:18'
        ]);

        $blacklistInfo = Blacklist::getBlacklistInfo($request->id_number);

        if ($blacklistInfo) {
            return response()->json([
                'success' => true,
                'is_blacklisted' => true,
                'data' => $blacklistInfo
            ]);
        }

        return response()->json([
            'success' => true,
            'is_blacklisted' => false
        ]);
    }
}
