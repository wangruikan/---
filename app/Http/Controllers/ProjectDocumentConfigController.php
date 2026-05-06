<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocumentConfig;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProjectDocumentConfigController extends Controller
{
    /**
     * 获取项目的资料配置列表
     */
    public function index(Request $request, $projectId)
    {
        try {
            $configs = ProjectDocumentConfig::where('project_id', $projectId)
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $configs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取资料配置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建资料配置
     */
    public function store(Request $request, $projectId)
    {
        $validator = Validator::make($request->all(), [
            'document_name' => 'required|string|max:100',
            'is_required' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 检查项目是否存在
            $project = Project::findOrFail($projectId);

            // 如果没有提供排序，自动设置为最后
            $sortOrder = $request->input('sort_order');
            if ($sortOrder === null) {
                $maxSort = ProjectDocumentConfig::where('project_id', $projectId)->max('sort_order');
                $sortOrder = ($maxSort ?? 0) + 1;
            }

            $config = ProjectDocumentConfig::create([
                'project_id' => $projectId,
                'document_name' => $request->document_name,
                'is_required' => $request->input('is_required', true),
                'sort_order' => $sortOrder,
            ]);

            return response()->json([
                'success' => true,
                'message' => '资料配置创建成功',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新资料配置
     */
    public function update(Request $request, $projectId, $id)
    {
        $validator = Validator::make($request->all(), [
            'document_name' => 'sometimes|required|string|max:100',
            'is_required' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $config = ProjectDocumentConfig::where('project_id', $projectId)
                ->where('id', $id)
                ->firstOrFail();

            $config->update($request->only([
                'document_name',
                'is_required',
                'sort_order'
            ]));

            return response()->json([
                'success' => true,
                'message' => '资料配置更新成功',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除资料配置
     */
    public function destroy($projectId, $id)
    {
        try {
            $config = ProjectDocumentConfig::where('project_id', $projectId)
                ->where('id', $id)
                ->firstOrFail();

            $config->delete();

            return response()->json([
                'success' => true,
                'message' => '资料配置删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量更新排序
     */
    public function updateSort(Request $request, $projectId)
    {
        $validator = Validator::make($request->all(), [
            'configs' => 'required|array',
            'configs.*.id' => 'required|integer',
            'configs.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->configs as $item) {
                ProjectDocumentConfig::where('project_id', $projectId)
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }

            return response()->json([
                'success' => true,
                'message' => '排序更新成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }
}

