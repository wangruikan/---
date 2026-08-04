<?php

namespace App\Http\Controllers;

use App\Models\InvoiceProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // 默认按维护顺序展示；保留显式排序参数兼容旧调用。
        if ($request->filled('sort_by')) {
            $query->orderBy($request->input('sort_by'), $request->input('sort_order', 'asc'));
        } else {
            $query->orderBy('sort_order', 'asc')
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

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
            'spec_model' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'quantity' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'tax_amount' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string',
        ], [
            'project_name.required' => '项目名称不能为空',
            'project_name.max' => '项目名称不能超过255个字符',
            'tax_rate.max' => '税率不能超过100%',
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
            'sort_order' => (int) InvoiceProject::where('account_set_id', $accountSetId)->max('sort_order') + 1,
            'spec_model' => $request->input('spec_model'),
            'unit' => $request->input('unit'),
            'quantity' => $request->input('quantity'),
            'unit_price' => $request->input('unit_price'),
            'amount' => $request->input('amount'),
            'tax_rate' => $request->input('tax_rate', 0),
            'tax_amount' => $this->calculateTaxAmount(
                $request->input('amount'),
                $request->input('tax_rate')
            ),
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
            'spec_model' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'quantity' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'tax_amount' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string',
        ], [
            'project_name.required' => '项目名称不能为空',
            'project_name.max' => '项目名称不能超过255个字符',
            'tax_rate.max' => '税率不能超过100%',
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
            'spec_model' => $request->input('spec_model'),
            'unit' => $request->input('unit'),
            'quantity' => $request->input('quantity'),
            'unit_price' => $request->input('unit_price'),
            'amount' => $request->input('amount'),
            'tax_rate' => $request->input('tax_rate', 0),
            'tax_amount' => $this->calculateTaxAmount(
                $request->input('amount'),
                $request->input('tax_rate')
            ),
            'remark' => $request->input('remark'),
        ]);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $project
        ]);
    }

    /**
     * 批量更新发票项目排序
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
        $projects = InvoiceProject::where('account_set_id', $accountSetId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($projects->count() !== $ids->count()) {
            return response()->json([
                'success' => false,
                'message' => '只能调整当前账套中的发票项目顺序',
            ], 422);
        }

        DB::transaction(function () use ($items, $accountSetId) {
            foreach ($items as $index => $item) {
                InvoiceProject::where('account_set_id', $accountSetId)
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => '排序更新成功',
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
            ->orderBy('sort_order', 'asc')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'project_name',
                'sort_order',
                'spec_model',
                'unit',
                'quantity',
                'unit_price',
                'amount',
                'tax_rate',
                'tax_amount',
                'remark'
            ]);

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    private function calculateTaxAmount($amount, $taxRate)
    {
        $amount = round(max(0, (float) $amount), 2);
        $taxRate = max(0, (float) $taxRate);

        return round($amount * $taxRate, 2);
    }
}

