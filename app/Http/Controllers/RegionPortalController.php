<?php

namespace App\Http\Controllers;

use App\Models\RegionPortal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RegionPortalController extends Controller
{
    /**
     * 获取地区网页入口列表
     */
    public function index(Request $request)
    {
        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $query = RegionPortal::with(['creator', 'updater'])
                ->where('account_set_id', $accountSetId);

            // 筛选条件
            if ($request->filled('region_name')) {
                $query->where('region_name', 'like', '%' . $request->input('region_name') . '%');
            }

            if ($request->filled('business_type')) {
                $query->where('business_type', 'like', '%' . $request->input('business_type') . '%');
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->input('is_active'));
            }

            if ($request->has('keyword')) {
                $keyword = $request->input('keyword');
                $query->where(function($q) use ($keyword) {
                    $q->where('region_name', 'like', "%{$keyword}%")
                      ->orWhere('business_type', 'like', "%{$keyword}%")
                      ->orWhere('portal_name', 'like', "%{$keyword}%");
                });
            }

            // 排序
            $query->orderBy('sort_order', 'asc')
                  ->orderBy('region_name', 'asc')
                  ->orderBy('created_at', 'desc');

            $portals = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $portals
            ]);

        } catch (\Exception $e) {
            Log::error('获取地区网页入口列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建地区网页入口
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'region_name' => 'required|string|max:100',
                'business_type' => 'required|string|max:100',
                'portal_name' => 'required|string|max:200',
                'portal_url' => 'required|url|max:500',
                'remarks' => 'nullable|string',
                'sort_order' => 'nullable|integer|min:0',
            ], [
                'region_name.required' => '地区名称不能为空',
                'business_type.required' => '业务类型不能为空',
                'portal_name.required' => '网站名称不能为空',
                'portal_url.required' => '网站地址不能为空',
                'portal_url.url' => '网站地址格式不正确',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $accountSetId = (int)$request->input('current_account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $portal = RegionPortal::create([
                'account_set_id' => $accountSetId,
                'region_name' => $request->input('region_name'),
                'business_type' => $request->input('business_type'),
                'portal_name' => $request->input('portal_name'),
                'portal_url' => $request->input('portal_url'),
                'remarks' => $request->input('remarks'),
                'sort_order' => $request->input('sort_order', 0),
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '网页入口创建成功',
                'data' => $portal->load(['creator'])
            ]);

        } catch (\Exception $e) {
            Log::error('创建地区网页入口失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新地区网页入口
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'region_name' => 'required|string|max:100',
                'business_type' => 'required|string|max:100',
                'portal_name' => 'required|string|max:200',
                'portal_url' => 'required|url|max:500',
                'remarks' => 'nullable|string',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ], [
                'region_name.required' => '地区名称不能为空',
                'business_type.required' => '业务类型不能为空',
                'portal_name.required' => '网站名称不能为空',
                'portal_url.required' => '网站地址不能为空',
                'portal_url.url' => '网站地址格式不正确',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $portal = RegionPortal::find($id);

            if (!$portal) {
                return response()->json([
                    'success' => false,
                    'message' => '网页入口不存在'
                ], 404);
            }

            $portal->update([
                'region_name' => $request->input('region_name'),
                'business_type' => $request->input('business_type'),
                'portal_name' => $request->input('portal_name'),
                'portal_url' => $request->input('portal_url'),
                'remarks' => $request->input('remarks'),
                'sort_order' => $request->input('sort_order', 0),
                'is_active' => $request->input('is_active', true),
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '网页入口更新成功',
                'data' => $portal->load(['creator', 'updater'])
            ]);

        } catch (\Exception $e) {
            Log::error('更新地区网页入口失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除地区网页入口
     */
    public function destroy($id)
    {
        try {
            $portal = RegionPortal::find($id);

            if (!$portal) {
                return response()->json([
                    'success' => false,
                    'message' => '网页入口不存在'
                ], 404);
            }

            $portal->delete();

            return response()->json([
                'success' => true,
                'message' => '网页入口删除成功'
            ]);

        } catch (\Exception $e) {
            Log::error('删除地区网页入口失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 切换启用/禁用状态
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $portal = RegionPortal::find($id);

            if (!$portal) {
                return response()->json([
                    'success' => false,
                    'message' => '网页入口不存在'
                ], 404);
            }

            $portal->update([
                'is_active' => !$portal->is_active,
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => $portal->is_active ? '已启用' : '已禁用',
                'data' => $portal
            ]);

        } catch (\Exception $e) {
            Log::error('切换网页入口状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '操作失败: ' . $e->getMessage()
            ], 500);
        }
    }
}

