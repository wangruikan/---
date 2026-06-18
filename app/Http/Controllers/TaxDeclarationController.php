<?php

namespace App\Http\Controllers;

use App\Models\TaxCategory;
use App\Models\TaxDeclarationConfig;
use App\Models\TaxDeclarationTask;
use App\Models\TaxDeclarationAttachment;
use App\Models\ApprovalFlowConfig;
use App\Models\OperationLog;
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
            $this->appendConfigCreatorFallback($config);
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
            'declaration_date' => 'nullable|string|max:5',
        ]);
        $validator->after(function ($validator) use ($request) {
            $this->validateDeclarationMonth(
                $validator,
                $request->input('period_type'),
                $request->input('declaration_date')
            );
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $declarationDate = $this->normalizeDeclarationDate(
                $request->period_type,
                $request->declaration_date
            );

            $config = TaxDeclarationConfig::create([
                'account_set_id' => $request->account_set_id,
                'company_name' => $request->company_name,
                'tax_category_ids' => $request->tax_category_ids,
                'period_type' => $request->period_type,
                'declaration_date' => $declarationDate,
                'created_by' => $request->user()?->id ?? Auth::id(),
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
            'declaration_date' => 'nullable|string|max:5',
        ]);
        $validator->after(function ($validator) use ($request) {
            $this->validateDeclarationMonth(
                $validator,
                $request->input('period_type'),
                $request->input('declaration_date')
            );
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $declarationDate = $this->normalizeDeclarationDate(
                $request->period_type,
                $request->declaration_date
            );

            $config = TaxDeclarationConfig::findOrFail($id);
            
            $config->update([
                'company_name' => $request->company_name,
                'tax_category_ids' => $request->tax_category_ids,
                'period_type' => $request->period_type,
                'declaration_date' => $declarationDate,
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
        [$targetMonth, $year, $month] = $this->resolveTaskMonth($request);
        $targetStartDate = sprintf('%s-01', $targetMonth);
        $targetEndDate = now()->copy()->setDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

        $visibleUntilDate = $this->getVisibleTaskEndDate($year);
        if ($visibleUntilDate === null || $targetStartDate > $visibleUntilDate) {
            return response()->json([
                'success' => true,
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 20,
            ]);
        }

        $configIds = $this->syncTasksFromConfigs($accountSetId, $year, $visibleUntilDate, $targetMonth);
        
        $query = TaxDeclarationTask::where('account_set_id', $accountSetId)
            ->whereIn('config_id', $configIds)
            ->with(['handler', 'completedBy', 'attachments']);
        
        // 筛选条件
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        $query->where('year', $year)
            ->whereBetween('declaration_date', [$targetStartDate, $targetEndDate]);
        
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
     * 根据当前税费申报配置补齐任务，避免依赖定时任务预先生成。
     */
    private function syncTasksFromConfigs($accountSetId, int $year, ?string $visibleUntilDate, ?string $targetMonth = null): array
    {
        if (!$accountSetId) {
            return [];
        }

        $configs = TaxDeclarationConfig::where('account_set_id', $accountSetId)->get();
        $configIds = $configs->pluck('id')->all();

        foreach ($configs as $config) {
            $declarationDates = $this->buildDeclarationDates($year, $config);
            if (empty($declarationDates)) {
                continue;
            }

            foreach ($declarationDates as $declarationDate) {
                if ($targetMonth !== null && substr($declarationDate, 0, 7) !== $targetMonth) {
                    continue;
                }

                if ($visibleUntilDate !== null && $declarationDate > $visibleUntilDate) {
                    continue;
                }

                $task = $this->findExistingTaskForMonth($config->id, $year, $declarationDate);

                if ($task) {
                    $task->update([
                        'account_set_id' => $config->account_set_id,
                        'company_name' => $config->company_name,
                        'tax_category_ids' => $config->tax_category_ids,
                        'declaration_date' => $declarationDate,
                    ]);
                    continue;
                }

                $handler = ApprovalFlowConfig::getFirstEffectiveApprover(
                    (int) $config->account_set_id,
                    'tax_declaration'
                );

                if (!$handler) {
                    Log::warning('税费申报任务动态生成失败：未找到操作员', [
                        'config_id' => $config->id,
                        'account_set_id' => $config->account_set_id,
                    ]);
                    continue;
                }

                try {
                    DB::beginTransaction();

                    $task = TaxDeclarationTask::create([
                        'account_set_id' => $config->account_set_id,
                        'config_id' => $config->id,
                        'company_name' => $config->company_name,
                        'tax_category_ids' => $config->tax_category_ids,
                        'declaration_date' => $declarationDate,
                        'year' => $year,
                        'handler_id' => $handler->id,
                        'handler_name' => $handler->name,
                        'status' => 'pending',
                    ]);

                    PendingTaskService::createTaxDeclarationTask($task);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('税费申报任务动态生成失败', [
                        'config_id' => $config->id,
                        'year' => $year,
                        'declaration_date' => $declarationDate,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $configIds;
    }

    private function resolveTaskMonth(Request $request): array
    {
        $month = $request->input('month');

        if (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return [$month, (int) substr($month, 0, 4), (int) substr($month, 5, 2)];
        }

        $now = now();
        return [$now->format('Y-m'), $now->year, $now->month];
    }

    private function getVisibleTaskEndDate(int $year): ?string
    {
        $now = now();

        if ($year > $now->year) {
            return null;
        }

        if ($year < $now->year) {
            return sprintf('%d-12-31', $year);
        }

        return $now->copy()->endOfMonth()->format('Y-m-d');
    }

    private function validateDeclarationMonth($validator, ?string $periodType, ?string $value): void
    {
        if (!is_string($periodType)) {
            return;
        }

        if ($periodType === 'monthly') {
            return;
        }

        if (!is_string($value) || $value === '') {
            $validator->errors()->add('declaration_date', '请选择申报月份');
            return;
        }

        $month = $this->extractMonth($value);

        if ($periodType === 'quarterly' && ($month < 1 || $month > 3)) {
            $validator->errors()->add('declaration_date', '季度申报月份只能选择第1、2、3个月');
        }

        if ($periodType === 'yearly' && ($month < 1 || $month > 12)) {
            $validator->errors()->add('declaration_date', '年度申报月份只能选择1-12月');
        }
    }

    private function normalizeDeclarationDate(string $periodType, ?string $value): string
    {
        if ($periodType === 'monthly') {
            return '01-01';
        }

        $month = $this->extractMonth($value);
        return sprintf('%02d-01', $month > 0 ? $month : 1);
    }

    private function buildDeclarationDates(int $year, TaxDeclarationConfig $config): array
    {
        $month = $this->extractMonth($config->declaration_date);

        if ($config->period_type === 'monthly') {
            if ($month < 1 || $month > 12) {
                return [];
            }

            return array_map(function ($currentMonth) use ($year) {
                return sprintf('%d-%02d-01', $year, $currentMonth);
            }, range(1, 12));
        }

        if ($config->period_type === 'quarterly') {
            if ($month < 1 || $month > 3) {
                return [];
            }

            return array_map(function ($quarterStartMonth) use ($year, $month) {
                return sprintf('%d-%02d-01', $year, $quarterStartMonth + $month - 1);
            }, [1, 4, 7, 10]);
        }

        if ($month < 1 || $month > 12) {
            return [];
        }

        return [sprintf('%d-%02d-01', $year, $month)];
    }

    private function extractMonth(?string $value): int
    {
        if (!is_string($value) || !preg_match('/^\d{2}(?:-\d{2})?$/', $value)) {
            return 0;
        }

        return (int) substr($value, 0, 2);
    }

    private function findExistingTaskForMonth($configId, int $year, string $declarationDate): ?TaxDeclarationTask
    {
        $month = substr($declarationDate, 5, 2);

        return TaxDeclarationTask::where('config_id', $configId)
            ->where('year', $year)
            ->whereYear('declaration_date', $year)
            ->whereMonth('declaration_date', (int) $month)
            ->orderByDesc('id')
            ->first();
    }

    private function appendConfigCreatorFallback(TaxDeclarationConfig $config): void
    {
        if ($config->creator) {
            $config->creator_name = $config->creator->name;
            return;
        }

        $log = OperationLog::where('model_type', TaxDeclarationConfig::class)
            ->where('model_id', $config->id)
            ->orderBy('created_at')
            ->first();

        $config->creator_name = $log?->user_name;
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
