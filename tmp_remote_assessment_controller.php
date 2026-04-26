<?php

namespace App\Http\Controllers;

use App\Models\AssessmentRecord;
use App\Models\AssessmentAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Traits\ChecksPermission;

class AssessmentRecordController extends Controller
{
    use ChecksPermission;
    // 获取考核记录列表
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('assessment.view')) {
            return $response;
        }
        
        $accountSetId = $request->input('account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $query = AssessmentRecord::with(['latestAppeal'])->where('account_set_id', $accountSetId);

        // 只显示当前用户相关的考核记录
        $currentUserId = auth()->id();
        $query->where('handler_id', $currentUserId);

        // 筛选条件
        if ($request->has('business_type') && $request->business_type) {
            $query->where('business_type', $request->business_type);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('handler_id') && $request->handler_id) {
            $query->where('handler_id', $request->handler_id);
        }

        // 时间范围筛选
        if ($request->has('start_date') && $request->start_date) {
            $query->where('deadline_date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->where('deadline_date', '<=', $request->end_date);
        }

        // 排序
        $query->orderBy('deadline_date', 'desc')
              ->orderBy('created_at', 'desc');

        // 分页
        $perPage = $request->input('per_page', 20);
        $records = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'total' => $records->total(),
            'per_page' => $records->perPage(),
            'current_page' => $records->currentPage()
        ]);
    }

    // 获取考核统计
    public function statistics(Request $request)
    {
        $accountSetId = $request->input('account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $query = AssessmentRecord::where('account_set_id', $accountSetId);

        // 只统计当前用户相关的考核记录
        $currentUserId = auth()->id();
        $query->where('handler_id', $currentUserId);

        // 按经办人统计
        $handlerStats = (clone $query)
            ->select(
                'handler_id',
                'handler_name',
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                DB::raw('SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) as overdue_count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count'),
                DB::raw('AVG(overdue_days) as avg_overdue_days'),
                DB::raw('MAX(overdue_days) as max_overdue_days')
            )
            ->groupBy('handler_id', 'handler_name')
            ->get();

        // 按业务类型统计
        $businessStats = (clone $query)
            ->select(
                'business_type',
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                DB::raw('SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) as overdue_count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count')
            )
            ->groupBy('business_type')
            ->get();

        // 总体统计
        $overallStats = $query
            ->select(
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                DB::raw('SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) as overdue_count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count'),
                DB::raw('AVG(overdue_days) as avg_overdue_days')
            )
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'overall' => $overallStats,
                'by_handler' => $handlerStats,
                'by_business' => $businessStats
            ]
        ]);
    }

    // 标记为已完成
    public function complete(Request $request, $id)
    {
        $record = AssessmentRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        // 检查权限：只能操作自己的考核记录
        if ($record->handler_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '无权限操作此记录'
            ], 403);
        }

        $record->actual_complete_date = now();
        $record->updateStatus();

        return response()->json([
            'success' => true,
            'message' => '已标记为完成',
            'data' => $record
        ]);
    }

    // 更新备注
    public function updateRemark(Request $request, $id)
    {
        $record = AssessmentRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        // 检查权限：只能操作自己的考核记录
        if ($record->handler_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '无权限操作此记录'
            ], 403);
        }

        $record->remark = $request->input('remark');
        $record->save();

        return response()->json([
            'success' => true,
            'message' => '备注已更新',
            'data' => $record
        ]);
    }

    public function uploadAppealImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $path = $file->storeAs('assessment_appeals/images', $filename, 'public');

        return response()->json([
            'success' => true,
            'message' => '图片上传成功',
            'data' => [
                'file_name' => $originalName,
                'file_path' => $path,
                'url' => '/storage/' . ltrim($path, '/')
            ]
        ]);
    }

    public function submitAppeal(Request $request, $id)
    {
        $record = AssessmentRecord::with('latestAppeal')->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        if ($record->handler_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '无权限操作此记录'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:1000',
            'images' => 'required|array|min:1|max:9',
            'images.*' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $latestAppeal = $record->latestAppeal;
        if ($latestAppeal && $latestAppeal->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => '该考核申诉已被驳回，不可再次申诉'
            ], 422);
        }

        if ($latestAppeal) {
            return response()->json([
                'success' => false,
                'message' => '该考核已发起过申诉'
            ], 422);
        }

        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $monthlyAppealCount = AssessmentAppeal::where('appellant_id', auth()->id())
            ->whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $monthEnd)
            ->count();

        if ($monthlyAppealCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => '当月申诉次数已达上限（3次）'
            ], 422);
        }

        $appeal = AssessmentAppeal::create([
            'assessment_record_id' => $record->id,
            'account_set_id' => $record->account_set_id,
            'appellant_id' => auth()->id(),
            'appellant_name' => auth()->user()->name,
            'description' => $request->input('description'),
            'images' => $request->input('images'),
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => '申诉提交成功',
            'data' => $appeal
        ]);
    }

    public function getAppeals(Request $request, $id)
    {
        $record = AssessmentRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        if ($record->handler_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '无权限查看此记录'
            ], 403);
        }

        $appeals = AssessmentAppeal::where('assessment_record_id', $record->id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $appeals
        ]);
    }

    // 删除记录
    public function destroy($id)
    {
        $record = AssessmentRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        // 检查权限：只能删除自己的考核记录
        if ($record->handler_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '无权限删除此记录'
            ], 403);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => '记录已删除'
        ]);
    }

    // 刷新所有待处理记录的状态
    public function refreshStatus(Request $request)
    {
        $accountSetId = $request->input('account_set_id');
        
        $count = AssessmentRecord::updateAllPendingStatus($accountSetId);

        return response()->json([
            'success' => true,
            'message' => "已更新 {$count} 条记录的状态"
        ]);
    }

    // 手动触发检查（新增）
    public function triggerCheck(Request $request)
    {
        try {
            // 执行检查命令
            Artisan::call('assessment:check-insurance-deadlines');
            
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => '检查完成',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '检查失败：' . $e->getMessage()
            ], 500);
        }
    }

    // 手动触发新入职员工资料检查
    public function checkNewEmployeeDocuments(Request $request)
    {
        try {
            $accountSetId = $request->input('account_set_id');
            $month = $request->input('month'); // 格式: YYYY-MM
            
            // 构建命令参数
            $command = 'assessment:check-new-employee-documents';
            $params = [];
            
            if ($accountSetId) {
                $params['--account-set-id'] = $accountSetId;
            }
            
            if ($month) {
                $params['--month'] = $month;
            }
            
            // 执行命令
            Artisan::call($command, $params);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => '新入职员工资料检查完成',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '检查失败：' . $e->getMessage()
            ], 500);
        }
    }
}