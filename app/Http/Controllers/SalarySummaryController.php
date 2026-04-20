<?php

namespace App\Http\Controllers;

use App\Models\SalarySummary;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalarySummaryController extends Controller
{
    use ChecksPermission;

    /**
     * 获取工资汇总列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('salary_summaries.view')) {
            return $response;
        }

        try {
            $accountSetId = $request->input('current_account_set_id');
            $user = Auth::user();

            $query = SalarySummary::with(['project', 'salaryApproval'])
                ->where('account_set_id', $accountSetId);

            // 筛选条件
            if ($request->has('month') && $request->month) {
                $query->where('month', $request->month);
            }

            if ($request->has('project_id') && $request->project_id) {
                $query->where('project_id', $request->project_id);
            }

            // 分页
            $perPage = $request->input('per_page', 20);
            $summaries = $query->orderBy('month', 'desc')
                ->orderBy('project_id', 'asc')
                ->paginate($perPage);

            // 🔍 调试日志：检查第一条记录的关键字段
            if ($summaries->count() > 0) {
                $firstRecord = $summaries->items()[0];
                \Log::info('========== 工资汇总API返回数据 ==========', [
                    'id' => $firstRecord->id,
                    'project_name' => $firstRecord->project_name,
                    'total_pension_personal' => $firstRecord->total_pension_personal,
                    'total_medical_personal' => $firstRecord->total_medical_personal,
                    'total_unemployment_personal' => $firstRecord->total_unemployment_personal,
                    'total_large_medical_personal' => $firstRecord->total_large_medical_personal,
                    '字段类型_pension' => gettype($firstRecord->total_pension_personal),
                    '字段类型_medical' => gettype($firstRecord->total_medical_personal),
                    '字段类型_unemployment' => gettype($firstRecord->total_unemployment_personal),
                    '字段类型_large_medical' => gettype($firstRecord->total_large_medical_personal),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $summaries
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取工资汇总列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取详情
     */
    public function show($id)
    {
        if ($response = $this->checkPermission('salary_summaries.view')) {
            return $response;
        }

        try {
            $summary = SalarySummary::with(['project', 'salaryApproval'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取详情失败: ' . $e->getMessage()
            ], 404);
        }
    }
}

