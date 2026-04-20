<?php

namespace App\Http\Controllers;

use App\Models\PendingTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PendingTaskController extends Controller
{
    /**
     * 获取当前用户的待办任务列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $accountSetId = $request->input('current_account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $status = $request->input('status', 'pending'); // pending 或 completed
        
        $query = PendingTask::where('account_set_id', $accountSetId)
            ->where('handler_id', $user->id);
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $tasks = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }

    /**
     * 获取待办任务统计
     */
    public function statistics(Request $request)
    {
        $user = $request->user();
        $accountSetId = $request->input('current_account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $pendingCount = PendingTask::where('account_set_id', $accountSetId)
            ->where('handler_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $completedCount = PendingTask::where('account_set_id', $accountSetId)
            ->where('handler_id', $user->id)
            ->where('status', 'completed')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => $pendingCount,
                'completed' => $completedCount,
                'total' => $pendingCount + $completedCount,
            ]
        ]);
    }

    /**
     * 手动标记任务为已完成
     */
    public function markAsCompleted(Request $request, $id)
    {
        $user = $request->user();
        $accountSetId = $request->input('current_account_set_id');
        
        $task = PendingTask::where('account_set_id', $accountSetId)
            ->where('handler_id', $user->id)
            ->findOrFail($id);

        if ($task->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => '任务已完成'
            ], 400);
        }

        $task->markAsCompleted();

        return response()->json([
            'success' => true,
            'message' => '任务已标记为完成',
            'data' => $task
        ]);
    }
}
