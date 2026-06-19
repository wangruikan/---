<?php

namespace App\Http\Controllers;

use App\Models\InvoiceContentConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * 开票内容配置项目控制器
 */
class InvoiceContentConfigController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $query = InvoiceContentConfig::where('account_set_id', $accountSetId)
            ->with('creator:id,name');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('project_name', 'like', "%{$keyword}%")
                    ->orWhere('remark', 'like', "%{$keyword}%")
                    ->orWhere('deduction_info', 'like', "%{$keyword}%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $projects = $query->orderBy($sortBy, $sortOrder)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    public function all(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $projects = InvoiceContentConfig::where('account_set_id', $accountSetId)
            ->orderBy('project_name')
            ->get([
                'id',
                'project_name',
                'remark',
                'deduction_info',
            ]);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $project = InvoiceContentConfig::create([
            'account_set_id' => $accountSetId,
            'project_name' => $request->input('project_name'),
            'remark' => $request->input('remark'),
            'deduction_info' => $request->input('deduction_info'),
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => '创建成功',
            'data' => $project,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);
        $project = InvoiceContentConfig::where('account_set_id', $accountSetId)->find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在',
            ], 404);
        }

        $project->update([
            'project_name' => $request->input('project_name'),
            'remark' => $request->input('remark'),
            'deduction_info' => $request->input('deduction_info'),
        ]);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $project,
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $accountSetId = request()->input('current_account_set_id', $user->account_set_id);
        $project = InvoiceContentConfig::where('account_set_id', $accountSetId)->find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在',
            ], 404);
        }

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功',
        ]);
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'project_name' => 'required|string|max:255',
            'remark' => 'nullable|string',
            'deduction_info' => 'nullable|string',
        ], [
            'project_name.required' => '项目名称不能为空',
            'project_name.max' => '项目名称不能超过255个字符',
        ]);
    }
}
