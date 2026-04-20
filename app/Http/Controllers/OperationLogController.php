<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperationLogController extends Controller
{
    /**
     * 获取最新的操作日志(用于弹幕显示)
     */
    public function getLatest(Request $request)
    {
        $user = Auth::user();
        
        // 检查权限
        if (!$this->canViewOperationLogs($user)) {
            return response()->json([
                'success' => false,
                'message' => '无权限查看操作日志'
            ], 403);
        }

        $query = OperationLog::with(['user', 'accountSet'])
            ->orderBy('created_at', 'desc');

        // 如果指定了账套,只显示该账套的日志
        if ($request->has('account_set_id')) {
            $query->where('account_set_id', $request->account_set_id);
        }

        // 获取指定时间之后的日志(用于轮询)
        if ($request->has('after') && $request->after) {
            // 记录日志，方便调试
            \Log::info('弹幕API - after参数', [
                'after' => $request->after,
                'current_time' => now()->toISOString()
            ]);
            
            $query->where('created_at', '>', $request->after);
        }

        // 限制数量
        $limit = $request->input('limit', 10);
        $logs = $query->limit($limit)->get();
        
        // 记录返回的日志数量
        \Log::info('弹幕API - 返回日志', [
            'count' => $logs->count(),
            'has_after' => $request->has('after')
        ]);

        return response()->json([
            'success' => true,
            'data' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user_name,
                    'description' => $log->description,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $log->created_at->diffForHumans(),
                ];
            })
        ]);
    }

    /**
     * 获取操作日志列表(用于管理页面)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = OperationLog::with(['user', 'accountSet'])
            ->orderBy('created_at', 'desc');

        // 按账套筛选
        if ($request->has('account_set_id') && $request->account_set_id && $request->account_set_id !== '') {
            $query->where('account_set_id', $request->account_set_id);
        }

        // 按用户筛选
        if ($request->has('user_id') && $request->user_id && $request->user_id !== '') {
            $query->where('user_id', $request->user_id);
        }

        // 按操作类型筛选
        if ($request->has('action') && $request->action && $request->action !== '') {
            $query->where('action', $request->action);
        }

        // 按时间范围筛选
        if ($request->has('start_date') && $request->start_date && $request->start_date !== '') {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date && $request->end_date !== '') {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // 搜索
        if ($request->has('keyword') && $request->keyword && $request->keyword !== '') {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('description', 'like', "%{$keyword}%")
                  ->orWhere('user_name', 'like', "%{$keyword}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * 检查用户是否有权限查看操作日志
     */
    protected function canViewOperationLogs($user)
    {
        // 超级管理员和管理员可以查看
        if (in_array($user->role, ['admin', 'super_admin'])) {
            return true;
        }

        // 检查用户是否有弹幕查看权限
        if (isset($user->can_view_operation_barrage) && $user->can_view_operation_barrage) {
            return true;
        }

        return false;
    }
}
