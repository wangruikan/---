<?php

namespace App\Http\Controllers;

use App\Models\FinancialSoftwareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FinancialSoftwareLinkController extends Controller
{
    /**
     * 获取财务软件链接列表
     */
    public function index(Request $request)
    {
        $accountSetId = $request->input('account_set_id');

        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $query = FinancialSoftwareLink::where('account_set_id', $accountSetId)
            ->with('creator')
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc');

        // 筛选：是否启用
        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        // 搜索：软件名称
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        $links = $query->get();

        return response()->json([
            'success' => true,
            'data' => $links
        ]);
    }

    /**
     * 创建财务软件链接
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|integer',
            'name' => 'required|string|max:100',
            'url' => 'required|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ], [
            'account_set_id.required' => '请选择账套',
            'name.required' => '请输入软件名称',
            'name.max' => '软件名称不能超过100个字符',
            'url.required' => '请输入软件地址',
            'url.max' => '软件地址不能超过500个字符',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $link = FinancialSoftwareLink::create([
            'account_set_id' => $request->account_set_id,
            'name' => $request->name,
            'url' => $request->url,
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->input('is_active', true),
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '创建成功',
            'data' => $link
        ]);
    }

    /**
     * 更新财务软件链接
     */
    public function update(Request $request, $id)
    {
        $link = FinancialSoftwareLink::find($id);

        if (!$link) {
            return response()->json([
                'success' => false,
                'message' => '财务软件链接不存在'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'url' => 'required|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required' => '请输入软件名称',
            'name.max' => '软件名称不能超过100个字符',
            'url.required' => '请输入软件地址',
            'url.max' => '软件地址不能超过500个字符',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $link->update([
            'name' => $request->name,
            'url' => $request->url,
            'sort_order' => $request->input('sort_order', $link->sort_order),
            'is_active' => $request->input('is_active', $link->is_active),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $link
        ]);
    }

    /**
     * 删除财务软件链接
     */
    public function destroy($id)
    {
        $link = FinancialSoftwareLink::find($id);

        if (!$link) {
            return response()->json([
                'success' => false,
                'message' => '财务软件链接不存在'
            ], 404);
        }

        $link->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }
}
