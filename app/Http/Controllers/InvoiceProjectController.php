<?php

namespace App\Http\Controllers;

use App\Models\InvoiceProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * 发票项目配置控制器
 */
class InvoiceProjectController extends Controller
{
    /**
     * 获取项目列表
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $query = InvoiceProject::where('account_set_id', $accountSetId)
            ->with('creator:id,name');

        // 搜索
        if ($request->has('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('project_name', 'like', "%{$keyword}%")
                  ->orWhere('remark', 'like', "%{$keyword}%");
            });
        }

        // 排序
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // 分页
        $perPage = $request->input('per_page', 15);
        $projects = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    /**
     * 创建项目
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|max:255',
            'remark' => 'nullable|string',
        ], [
            'project_name.required' => '项目名称不能为空',
            'project_name.max' => '项目名称不能超过255个字符',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $project = InvoiceProject::create([
            'account_set_id' => $accountSetId,
            'project_name' => $request->input('project_name'),
            'remark' => $request->input('remark'),
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => '创建成功',
            'data' => $project
        ]);
    }

    /**
     * 更新项目
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|max:255',
            'remark' => 'nullable|string',
        ], [
            'project_name.required' => '项目名称不能为空',
            'project_name.max' => '项目名称不能超过255个字符',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = InvoiceProject::find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        $project->update([
            'project_name' => $request->input('project_name'),
            'remark' => $request->input('remark'),
        ]);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $project
        ]);
    }

    /**
     * 删除项目
     */
    public function destroy($id)
    {
        $project = InvoiceProject::find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        // 检查是否被使用
        $usageCount = $project->invoiceItems()->count();
        if ($usageCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "该项目已被 {$usageCount} 条发票申请使用，无法删除"
            ], 400);
        }

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    /**
     * 获取所有项目（用于下拉选择）
     */
    public function all(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $projects = InvoiceProject::where('account_set_id', $accountSetId)
            ->orderBy('project_name')
            ->get(['id', 'project_name']);

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }
}

