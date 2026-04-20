<?php

namespace App\Http\Controllers;

use App\Models\PayrollRemark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayrollRemarkController extends Controller
{
    /**
     * 检查权限：只有审批级别2、3、4可以访问
     */
    private function checkPermission()
    {
        $user = Auth::user();
        $accountSetId = request()->input('account_set_id', $user->account_set_id);

        if (!$accountSetId) {
            return false;
        }

        $approver = DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('user_id', $user->id)
            ->whereIn('approval_level', [2, 3, 4])
            ->first();

        return $approver !== null;
    }

    /**
     * 获取所有项目列表（带备注）
     */
    public function index(Request $request)
    {
        if (!$this->checkPermission()) {
            return response()->json([
                'success' => false,
                'message' => '无权访问此功能'
            ], 403);
        }

        $user = Auth::user();
        $accountSetId = $request->input('account_set_id', $user->account_set_id);
        $year = $request->input('year');
        $month = $request->input('month');

        // 获取该账套下所有项目名称（从项目表中获取）
        $projectsQuery = DB::table('projects')
            ->where('account_set_id', $accountSetId)
            ->select('name')
            ->distinct();

        $projects = $projectsQuery->pluck('name')->toArray();

        // 获取备注数据
        $remarksQuery = PayrollRemark::where('account_set_id', $accountSetId);

        if ($year) {
            $remarksQuery->where('year', $year);
        }
        if ($month) {
            $remarksQuery->where('month', $month);
        }

        $remarks = $remarksQuery->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('project_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'projects' => $projects,
                'remarks' => $remarks
            ]
        ]);
    }

    /**
     * 获取单个备注
     */
    public function show($id)
    {
        if (!$this->checkPermission()) {
            return response()->json([
                'success' => false,
                'message' => '无权访问此功能'
            ], 403);
        }

        $remark = PayrollRemark::find($id);

        if (!$remark) {
            return response()->json([
                'success' => false,
                'message' => '备注不存在'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $remark
        ]);
    }

    /**
     * 创建或更新备注
     */
    public function store(Request $request)
    {
        if (!$this->checkPermission()) {
            return response()->json([
                'success' => false,
                'message' => '无权访问此功能'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|max:100',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'remark' => 'nullable|string',
        ], [
            'project_name.required' => '项目名称不能为空',
            'year.required' => '年份不能为空',
            'month.required' => '月份不能为空',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $request->input('account_set_id', $user->account_set_id);

        // 查找是否已存在
        $remark = PayrollRemark::where('account_set_id', $accountSetId)
            ->where('project_name', $request->input('project_name'))
            ->where('year', $request->input('year'))
            ->where('month', $request->input('month'))
            ->first();

        if ($remark) {
            // 更新
            $remark->update([
                'remark' => $request->input('remark'),
                'updated_by' => $user->id,
            ]);
            $message = '更新成功';
        } else {
            // 创建
            $remark = PayrollRemark::create([
                'account_set_id' => $accountSetId,
                'project_name' => $request->input('project_name'),
                'year' => $request->input('year'),
                'month' => $request->input('month'),
                'remark' => $request->input('remark'),
                'created_by' => $user->id,
            ]);
            $message = '创建成功';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $remark
        ]);
    }

    /**
     * 删除备注
     */
    public function destroy($id)
    {
        if (!$this->checkPermission()) {
            return response()->json([
                'success' => false,
                'message' => '无权访问此功能'
            ], 403);
        }

        $remark = PayrollRemark::find($id);

        if (!$remark) {
            return response()->json([
                'success' => false,
                'message' => '备注不存在'
            ], 404);
        }

        $remark->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    /**
     * 根据项目和期间获取备注
     */
    public function getByProjectAndPeriod(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('account_set_id', $user->account_set_id);
        $projectNames = $request->input('project_names', []); // 项目名称数组
        $year = $request->input('year');
        $month = $request->input('month');

        if (empty($projectNames) || !$year || !$month) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $remarks = PayrollRemark::where('account_set_id', $accountSetId)
            ->whereIn('project_name', $projectNames)
            ->where('year', $year)
            ->where('month', $month)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $remarks
        ]);
    }
}

