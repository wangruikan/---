<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocumentConfig;
use App\Models\ProjectDocumentSet;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ProjectDocumentConfigController extends Controller
{
    private function buildConfigsResponse(int $projectId): array
    {
        if (!Schema::hasTable('project_document_sets')) {
            return [
                'sets' => [],
                'default_set_id' => null,
                'flat_configs' => ProjectDocumentConfig::where('project_id', $projectId)
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('id', 'asc')
                    ->get(),
            ];
        }

        $sets = ProjectDocumentSet::where('project_id', $projectId)
            ->with(['configs' => function ($query) {
                $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
            }])
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return [
            'sets' => $sets,
            'default_set_id' => optional($sets->firstWhere('is_default', true))->id,
            'flat_configs' => $sets->flatMap(function ($set) {
                return $set->configs;
            })->values(),
        ];
    }

    /**
     * 获取项目的资料配置列表
     */
    public function index(Request $request, $projectId)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->buildConfigsResponse((int) $projectId)
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
        if (!Schema::hasTable('project_document_sets')) {
            return response()->json([
                'success' => false,
                'message' => '请先执行资料方案相关SQL后再使用该功能'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'document_set_id' => 'required|integer|exists:project_document_sets,id',
            'document_name' => 'required|string|max:100',
            'document_type' => 'nullable|in:image,pdf,document,all',
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
            Project::findOrFail($projectId);
            $documentSet = ProjectDocumentSet::where('project_id', $projectId)
                ->where('id', $request->input('document_set_id'))
                ->firstOrFail();

            // 如果没有提供排序，自动设置为最后
            $sortOrder = $request->input('sort_order');
            if ($sortOrder === null) {
                $maxSort = ProjectDocumentConfig::where('document_set_id', $documentSet->id)->max('sort_order');
                $sortOrder = ($maxSort ?? 0) + 1;
            }

            $config = ProjectDocumentConfig::create([
                'project_id' => $projectId,
                'document_set_id' => $documentSet->id,
                'document_name' => $request->document_name,
                'document_type' => $request->input('document_type', 'all'),
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
        if (!Schema::hasTable('project_document_sets')) {
            return response()->json([
                'success' => false,
                'message' => '请先执行资料方案相关SQL后再使用该功能'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'document_set_id' => 'sometimes|required|integer|exists:project_document_sets,id',
            'document_name' => 'sometimes|required|string|max:100',
            'document_type' => 'sometimes|nullable|in:image,pdf,document,all',
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

            if ($request->has('document_set_id')) {
                ProjectDocumentSet::where('project_id', $projectId)
                    ->where('id', $request->input('document_set_id'))
                    ->firstOrFail();
            }

            $config->update($request->only([
                'document_set_id',
                'document_name',
                'document_type',
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
        if (!Schema::hasTable('project_document_sets')) {
            return response()->json([
                'success' => false,
                'message' => '请先执行资料方案相关SQL后再使用该功能'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'document_set_id' => 'required|integer|exists:project_document_sets,id',
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
            ProjectDocumentSet::where('project_id', $projectId)
                ->where('id', $request->input('document_set_id'))
                ->firstOrFail();

            foreach ($request->configs as $item) {
                ProjectDocumentConfig::where('project_id', $projectId)
                    ->where('document_set_id', $request->input('document_set_id'))
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

    public function storeSet(Request $request, $projectId)
    {
        if (!Schema::hasTable('project_document_sets')) {
            return response()->json([
                'success' => false,
                'message' => '请先执行资料方案相关SQL后再使用该功能'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'set_name' => 'required|string|max:100',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Project::findOrFail($projectId);

            $maxSort = ProjectDocumentSet::where('project_id', $projectId)->max('sort_order');
            $hasExistingSet = ProjectDocumentSet::where('project_id', $projectId)->exists();
            $isDefault = !$hasExistingSet || $request->boolean('is_default');

            if ($isDefault) {
                ProjectDocumentSet::where('project_id', $projectId)->update(['is_default' => false]);
            }

            $set = ProjectDocumentSet::create([
                'project_id' => $projectId,
                'set_name' => $request->input('set_name'),
                'sort_order' => ($maxSort ?? 0) + 1,
                'is_default' => $isDefault,
            ]);

            return response()->json([
                'success' => true,
                'message' => '资料方案创建成功',
                'data' => $set
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateSet(Request $request, $projectId, $id)
    {
        if (!Schema::hasTable('project_document_sets')) {
            return response()->json([
                'success' => false,
                'message' => '请先执行资料方案相关SQL后再使用该功能'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'set_name' => 'sometimes|required|string|max:100',
            'is_default' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $set = ProjectDocumentSet::where('project_id', $projectId)
                ->where('id', $id)
                ->firstOrFail();

            if ($request->boolean('is_default')) {
                ProjectDocumentSet::where('project_id', $projectId)->update(['is_default' => false]);
            }

            $set->update($request->only(['set_name', 'is_default']));

            return response()->json([
                'success' => true,
                'message' => '资料方案更新成功',
                'data' => $set
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroySet($projectId, $id)
    {
        if (!Schema::hasTable('project_document_sets')) {
            return response()->json([
                'success' => false,
                'message' => '请先执行资料方案相关SQL后再使用该功能'
            ], 422);
        }

        try {
            $set = ProjectDocumentSet::where('project_id', $projectId)
                ->where('id', $id)
                ->firstOrFail();

            $isUsedByEmployee = DB::table('employee_projects')
                ->where('project_id', $projectId)
                ->where('document_set_id', $set->id)
                ->exists();

            if ($isUsedByEmployee) {
                return response()->json([
                    'success' => false,
                    'message' => '该资料方案已有人员在使用，不能删除'
                ], 422);
            }

            DB::transaction(function () use ($projectId, $set) {
                ProjectDocumentConfig::where('project_id', $projectId)
                    ->where('document_set_id', $set->id)
                    ->delete();

                $wasDefault = $set->is_default;
                $set->delete();

                if ($wasDefault) {
                    $nextSet = ProjectDocumentSet::where('project_id', $projectId)
                        ->orderBy('sort_order', 'asc')
                        ->orderBy('id', 'asc')
                        ->first();

                    if ($nextSet) {
                        $nextSet->update(['is_default' => true]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => '资料方案删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }
}

