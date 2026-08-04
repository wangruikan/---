<?php

namespace App\Http\Controllers;

use App\Models\InvoiceContentConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            $query->where('project_name', 'like', "%{$keyword}%");
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if ($request->filled('sort_by')) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('sort_order', 'asc')
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        $projects = $query
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
            ->orderBy('sort_order', 'asc')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'project_name',
                'tax_rate',
                'sort_order',
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
            'sort_order' => (int) InvoiceContentConfig::where('account_set_id', $accountSetId)->max('sort_order') + 1,
            'tax_rate' => $request->input('tax_rate', 0),
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
            'tax_rate' => $request->input('tax_rate', 0),
        ]);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $project,
        ]);
    }

    /**
     * 批量更新开票内容配置排序
     */
    public function updateSort(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer|min:1',
        ], [
            'items.required' => '排序数据不能为空',
            'items.*.id.required' => '项目ID不能为空',
            'items.*.sort_order.required' => '排序值不能为空',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);
        $items = collect($request->input('items'));
        $ids = $items->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        $projects = InvoiceContentConfig::where('account_set_id', $accountSetId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($projects->count() !== $ids->count()) {
            return response()->json([
                'success' => false,
                'message' => '只能调整当前账套中的开票内容配置顺序',
            ], 422);
        }

        DB::transaction(function () use ($items, $accountSetId) {
            foreach ($items as $index => $item) {
                InvoiceContentConfig::where('account_set_id', $accountSetId)
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => '排序更新成功',
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
            'tax_rate' => 'required|numeric|min:0|max:1',
        ], [
            'project_name.required' => '项目名称不能为空',
            'project_name.max' => '项目名称不能超过255个字符',
            'tax_rate.required' => '税率不能为空',
            'tax_rate.numeric' => '税率格式不正确',
            'tax_rate.min' => '税率不能小于0',
            'tax_rate.max' => '税率不能大于100%',
        ]);
    }
}
