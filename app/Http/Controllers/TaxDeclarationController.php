<?php

namespace App\Http\Controllers;

use App\Models\TaxCategory;
use App\Models\TaxDeclarationConfig;
use App\Models\TaxDeclarationTask;
use App\Models\TaxDeclarationAttachment;
use App\Services\PendingTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TaxDeclarationController extends Controller
{
    /**
     * 获取税种类目列表
     */
    public function getCategories(Request $request)
    {
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('account_set_id');
        
        $categories = TaxCategory::where('account_set_id', $accountSetId)
            ->with('creator')
            ->orderBy('name')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * 创建税种类目
     */
    public function storeCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|integer',
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $category = TaxCategory::create([
                'account_set_id' => $request->account_set_id,
                'name' => $request->name,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => '创建成功'
            ]);
        } catch (\Exception $e) {
            Log::error('创建税种类目失败', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '创建失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新税种类目
     */
    public function updateCategory(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $category = TaxCategory::findOrFail($id);
            
            $category->update([
                'name' => $request->name,
            ]);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => '更新成功'
            ]);
        } catch (\Exception $e) {
            Log::error('更新税种类目失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '更新失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除税种类目
     */
    public function deleteCategory($id)
    {
        try {
            $category = TaxCategory::findOrFail($id);
            
            // 检查是否被配置使用（兼容 MySQL 5.6，使用 LIKE 查询 JSON 数组）
            $usedInConfigs = TaxDeclarationConfig::where(function($query) use ($id) {
                $query->where('tax_category_ids', 'LIKE', '%"' . $id . '"%')
                      ->orWhere('tax_category_ids', 'LIKE', '%[' . $id . ']%')
                      ->orWhere('tax_category_ids', 'LIKE', '%[' . $id . ',%')
                      ->orWhere('tax_category_ids', 'LIKE', '%,' . $id . ']%')
                      ->orWhere('tax_category_ids', 'LIKE', '%,' . $id . ',%');
            })->exists();
            
            if ($usedInConfigs) {
                return response()->json([
                    'success' => false,
                    'message' => '该税种已被申报配置使用，无法删除'
                ], 400);
            }
            
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除税种类目失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取申报配置列表
     */
    public function getConfigs(Request $request)
    {
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('account_set_id');
        
        $configs = TaxDeclarationConfig::where('account_set_id', $accountSetId)
            ->with('creator')
            ->orderBy('declaration_date')
            ->get();
        
        // 加载税种信息
        foreach ($configs as $config) {
            $config->tax_categories_list = $config->taxCategories;
        }
        
        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * 创建申报配置
     */
    public function storeConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|integer',
            'company_name' => 'required|string|max:200',
            'tax_category_ids' => 'required|array|min:1',
            'tax_category_ids.*' => 'integer|exists:tax_categories,id',
            'period_type' => 'required|in:monthly,quarterly,yearly',
            'declaration_date' => 'required|string|regex:/^\d{2}-\d{2}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $config = TaxDeclarationConfig::create([
                'account_set_id' => $request->account_set_id,
                'company_name' => $request->company_name,
                'tax_category_ids' => $request->tax_category_ids,
                'period_type' => $request->period_type,
                'declaration_date' => $request->declaration_date,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => '创建成功'
            ]);
        } catch (\Exception $e) {
            Log::error('创建申报配置失败', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '创建失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新申报配置
     */
    public function updateConfig(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:200',
            'tax_category_ids' => 'required|array|min:1',
            'tax_category_ids.*' => 'integer|exists:tax_categories,id',
            'period_type' => 'required|in:monthly,quarterly,yearly',
            'declaration_date' => 'required|string|regex:/^\d{2}-\d{2}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $config = TaxDeclarationConfig::findOrFail($id);
            
            $config->update([
                'company_name' => $request->company_name,
                'tax_category_ids' => $request->tax_category_ids,
                'period_type' => $request->period_type,
                'declaration_date' => $request->declaration_date,
            ]);

            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => '更新成功'
            ]);
        } catch (\Exception $e) {
            Log::error('更新申报配置失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '更新失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除申报配置
     */
    public function deleteConfig($id)
    {
        try {
            $config = TaxDeclarationConfig::findOrFail($id);
            $config->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除申报配置失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取申报任务列表
     */
    public function getTasks(Request $request)
    {
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('account_set_id');
        
        $query = TaxDeclarationTask::where('account_set_id', $accountSetId)
            ->with(['handler', 'completedBy', 'attachments']);
        
        // 筛选条件
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }
        
        $tasks = $query->orderBy('declaration_date', 'desc')
            ->paginate(20);
        
        // 加载税种信息
        foreach ($tasks as $task) {
            $task->tax_categories_list = $task->taxCategories;
        }
        
        return response()->json([
            'success' => true,
            'data' => $tasks->items(),
            'total' => $tasks->total(),
            'current_page' => $tasks->currentPage(),
            'per_page' => $tasks->perPage(),
        ]);
    }

    /**
     * 获取任务详情
     */
    public function getTaskDetail($id)
    {
        try {
            $task = TaxDeclarationTask::with(['handler', 'completedBy', 'attachments.uploader'])
                ->findOrFail($id);
            
            $task->tax_categories_list = $task->taxCategories;
            
            return response()->json([
                'success' => true,
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '任务不存在'
            ], 404);
        }
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|integer|exists:tax_declaration_tasks,id',
            'file' => 'required|file|max:51200', // 最大50MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $file = $request->file('file');
            $task = TaxDeclarationTask::findOrFail($request->task_id);
            
            // 先获取文件信息（在移动之前）
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getClientMimeType();
            $extension = $file->getClientOriginalExtension();
            
            // 生成文件名
            $filename = $task->account_set_id . '_' . $task->id . '_' . time() . '_' . uniqid() . '.' . $extension;
            
            // 确保目录存在
            $dir = public_path('uploads/tax_declarations');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // 保存文件
            $file->move($dir, $filename);
            
            // 保存附件记录
            $attachment = TaxDeclarationAttachment::create([
                'task_id' => $task->id,
                'file_name' => $originalName,
                'file_path' => 'uploads/tax_declarations/' . $filename,
                'file_size' => $fileSize,
                'file_type' => $mimeType,
                'uploaded_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $attachment,
                'message' => '上传成功'
            ]);
        } catch (\Exception $e) {
            Log::error('上传税费申报附件失败', [
                'error' => $e->getMessage(),
                'task_id' => $request->task_id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除附件
     */
    public function deleteAttachment($id)
    {
        try {
            $attachment = TaxDeclarationAttachment::findOrFail($id);
            
            // 删除文件
            $filePath = public_path($attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除税费申报附件失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 完成任务
     */
    public function completeTask(Request $request, $id)
    {
        try {
            $task = TaxDeclarationTask::findOrFail($id);
            
            if ($task->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => '任务已完成'
                ], 400);
            }
            
            DB::beginTransaction();
            
            // 标记任务为已完成
            $task->markAsCompleted(Auth::id());
            
            // 完成待办任务
            PendingTaskService::checkAndCompleteTaxDeclarationTask($task);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '任务已完成'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('完成税费申报任务失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '操作失败：' . $e->getMessage()
            ], 500);
        }
    }
}
