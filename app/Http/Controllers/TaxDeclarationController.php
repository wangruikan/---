<?php

namespace App\Http\Controllers;

use App\Models\TaxCategory;
use App\Models\TaxDeclarationConfig;
use App\Models\TaxDeclarationTask;
use App\Models\TaxDeclarationAttachment;
use App\Models\ApprovalFlowConfig;
use App\Models\OperationLog;
use App\Models\User;
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
            ->with(['creator', 'parent'])
            ->orderBy('id')
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
            'parent_id' => 'nullable|integer|exists:tax_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        [$parentId, $parentError] = $this->resolveCategoryParent(
            $request->input('account_set_id'),
            $request->input('parent_id')
        );

        if ($parentError) {
            return response()->json([
                'success' => false,
                'message' => $parentError,
            ], 400);
        }

        try {
            $category = TaxCategory::create([
                'account_set_id' => $request->account_set_id,
                'name' => $request->name,
                'parent_id' => $parentId,
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
            'parent_id' => 'nullable|integer|exists:tax_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $category = TaxCategory::findOrFail($id);

            [$parentId, $parentError] = $this->resolveCategoryParent(
                $category->account_set_id,
                $request->input('parent_id'),
                $category->id
            );

            if ($parentError) {
                return response()->json([
                    'success' => false,
                    'message' => $parentError,
                ], 400);
            }
            
            $category->update([
                'name' => $request->name,
                'parent_id' => $parentId,
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

            if ($category->children()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => '该税种大类下还有细分税种，无法删除',
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
            'declaration_type' => 'nullable|in:monthly,quarterly,yearly',
            'declaration_date' => 'nullable|string|max:5',
        ]);
        $validator->after(function ($validator) use ($request) {
            $this->validateDeclarationMonth(
                $validator,
                $request->input('period_type'),
                $request->input('declaration_date')
            );
            $this->validateTaxCategorySelection(
                $validator,
                $request->input('account_set_id'),
                $request->input('tax_category_ids')
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
            $declarationType = $request->input('declaration_type') ?: $request->input('period_type');
            $taxCategoryIds = $this->normalizeConfiguredTaxCategoryIds(
                $request->input('account_set_id'),
                $request->input('tax_category_ids')
            );

            $config = TaxDeclarationConfig::create([
                'account_set_id' => $request->account_set_id,
                'company_name' => $request->company_name,
                'tax_category_ids' => $taxCategoryIds,
                'period_type' => $request->period_type,
                'declaration_type' => $declarationType,
                'declaration_date' => $declarationDate,
                'created_by' => $request->user()?->id ?? Auth::id(),
            ]);

            $this->syncCurrentMonthTask($config);

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
            'declaration_type' => 'nullable|in:monthly,quarterly,yearly',
            'declaration_date' => 'nullable|string|max:5',
        ]);
        $validator->after(function ($validator) use ($request) {
            $this->validateDeclarationMonth(
                $validator,
                $request->input('period_type'),
                $request->input('declaration_date')
            );
            $this->validateTaxCategorySelection(
                $validator,
                $request->input('account_set_id'),
                $request->input('tax_category_ids')
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
            $declarationType = $request->input('declaration_type') ?: $request->input('period_type');

            $config = TaxDeclarationConfig::findOrFail($id);
            $taxCategoryIds = $this->normalizeConfiguredTaxCategoryIds(
                $config->account_set_id,
                $request->input('tax_category_ids')
            );
            
            $config->update([
                'company_name' => $request->company_name,
                'tax_category_ids' => $taxCategoryIds,
                'period_type' => $request->period_type,
                'declaration_type' => $declarationType,
                'declaration_date' => $declarationDate,
            ]);

            $this->syncCurrentMonthTask($config->fresh());

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
        $taxCategoryGroups = $this->getTaxCategoryGroups($accountSetId);
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
                'tax_category_groups' => $taxCategoryGroups,
            ]);
        }

        $configIds = $this->syncTasksFromConfigs($accountSetId, $year, $visibleUntilDate, $targetMonth);
        
        $query = TaxDeclarationTask::where('account_set_id', $accountSetId)
            ->whereIn('config_id', $configIds)
            ->with(['config', 'handler', 'completedBy', 'attachments']);
        
        // 筛选条件
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        $query->where('year', $year)
            ->whereBetween('declaration_date', [$targetStartDate, $targetEndDate]);
        
        $tasks = $query->orderBy('declaration_date', 'desc')
            ->paginate(20);
        
        // 加载固定的两层税种表头，以及每个任务对应的逐项状态
        foreach ($tasks as $task) {
            $this->appendTaskTaxCategoryState($task, $taxCategoryGroups);
        }
        
        return response()->json([
            'success' => true,
            'data' => $tasks->items(),
            'total' => $tasks->total(),
            'current_page' => $tasks->currentPage(),
            'per_page' => $tasks->perPage(),
            'tax_category_groups' => $taxCategoryGroups,
        ]);
    }

    /**
     * 为任务税种附加逐项完成状态，供列表和详情页统一使用。
     */
    private function appendTaskTaxCategoryState(TaxDeclarationTask $task, ?array $taxCategoryGroups = null): void
    {
        $completedIds = $task->getCompletedTaxCategoryIdsList();
        $configuredIds = $task->getConfiguredTaxCategoryIds();

        $task->tax_categories_list = $task->taxCategories->map(function ($category) use ($completedIds) {
            $category->completed = in_array((int) $category->id, $completedIds, true);
            return $category;
        })->values();
        $task->completed_tax_category_ids = $completedIds;
        $task->pending_tax_category_ids = $task->getPendingTaxCategoryIds();

        $groups = $taxCategoryGroups ?? $this->getTaxCategoryGroups($task->account_set_id);
        $states = [];
        foreach ($groups as $group) {
            foreach ($group['children'] as $category) {
                $categoryId = (int) $category['id'];
                $states[(string) $categoryId] = in_array($categoryId, $configuredIds, true)
                    ? (in_array($categoryId, $completedIds, true) ? 'completed' : 'pending')
                    : 'not_required';
            }
        }

        $task->tax_category_states = $states;
        $task->tax_category_groups = $groups;
    }

    private function normalizeTaxCategoryIds($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $value)));
    }

    /**
     * 验证所选税种属于当前账套。大类允许直接选择，保存时会展开成细分税种。
     */
    private function validateTaxCategorySelection($validator, $accountSetId, $value): void
    {
        $accountSetId = $accountSetId ?: request()->input('current_account_set_id');
        $ids = $this->normalizeTaxCategoryIds($value);
        if (!$accountSetId || empty($ids)) {
            return;
        }

        $availableCount = TaxCategory::where('account_set_id', $accountSetId)
            ->whereIn('id', $ids)
            ->count();

        if ($availableCount !== count($ids)) {
            $validator->errors()->add('tax_category_ids', '选择的税种不属于当前账套');
        }

        if (empty($this->normalizeConfiguredTaxCategoryIds($accountSetId, $ids))) {
            $validator->errors()->add('tax_category_ids', '请选择有效的税种或细分税种');
        }
    }

    /**
     * 将大类选择转换为细分税种 ID，只有两级结构，避免出现多级嵌套。
     */
    private function normalizeConfiguredTaxCategoryIds($accountSetId, $value): array
    {
        $ids = $this->normalizeTaxCategoryIds($value);
        if (!$accountSetId || empty($ids)) {
            return [];
        }

        $categories = TaxCategory::where('account_set_id', $accountSetId)
            ->get(['id', 'parent_id'])
            ->keyBy('id');
        $childrenByParent = $categories->filter(fn ($category) => $category->parent_id !== null)
            ->groupBy('parent_id');

        $expandedIds = [];
        foreach ($ids as $id) {
            $category = $categories->get($id);
            if (!$category) {
                continue;
            }

            $children = $childrenByParent->get($id, collect());
            if ($category->parent_id === null && $children->isNotEmpty()) {
                foreach ($children as $child) {
                    $expandedIds[] = (int) $child->id;
                }
            } else {
                $expandedIds[] = (int) $id;
            }
        }

        return array_values(array_unique($expandedIds));
    }

    /**
     * 返回任务表固定使用的两层表头。没有细分的大类自身作为一列。
     */
    private function getTaxCategoryGroups($accountSetId): array
    {
        if (!$accountSetId) {
            return [];
        }

        $categories = TaxCategory::where('account_set_id', $accountSetId)
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id']);
        $parents = $categories->filter(fn ($category) => $category->parent_id === null);

        return $parents->map(function ($parent) use ($categories) {
            $children = $categories->filter(
                fn ($category) => (int) $category->parent_id === (int) $parent->id
            );

            if ($children->isEmpty()) {
                $children = collect([$parent]);
            }

            return [
                'id' => (int) $parent->id,
                'name' => $parent->name,
                'children' => $children->map(function ($category) use ($parent) {
                    return [
                        'id' => (int) $category->id,
                        'name' => $category->name,
                        'parent_id' => (int) $parent->id,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * 校验并限制税种细分的所属大类。
     */
    private function resolveCategoryParent($accountSetId, $parentId, $ignoreId = null): array
    {
        if ($parentId === null || $parentId === '') {
            return [null, null];
        }

        $parent = TaxCategory::where('account_set_id', $accountSetId)
            ->whereKey((int) $parentId)
            ->first();

        if (!$parent) {
            return [null, '所属税种大类不存在或不属于当前账套'];
        }

        if ($ignoreId !== null && (int) $parent->id === (int) $ignoreId) {
            return [null, '细分税种不能将自己设为所属大类'];
        }

        if ($ignoreId !== null && TaxCategory::where('parent_id', $ignoreId)->exists()) {
            return [null, '已有细分税种的大类不能再设置所属大类'];
        }

        if ($parent->parent_id !== null) {
            return [null, '只支持大类和细分税种两级结构'];
        }

        return [(int) $parent->id, null];
    }

    /**
     * 配置保存后立即同步当前月任务，不等待下个周期或定时任务。
     */
    private function syncCurrentMonthTask(TaxDeclarationConfig $config): void
    {
        $now = now();

        $this->syncTasksFromConfigs(
            $config->account_set_id,
            $now->year,
            $now->copy()->endOfMonth()->format('Y-m-d'),
            $now->format('Y-m'),
            $config->id
        );
    }

    /**
     * 根据当前税费申报配置补齐任务，避免依赖定时任务预先生成。
     */
    private function syncTasksFromConfigs(
        $accountSetId,
        int $year,
        ?string $visibleUntilDate,
        ?string $targetMonth = null,
        ?int $forceCurrentTaskConfigId = null
    ): array
    {
        if (!$accountSetId) {
            return [];
        }

        $configs = TaxDeclarationConfig::where('account_set_id', $accountSetId)->get();
        $visibleConfigIds = [];

        foreach ($configs as $config) {
            $currentCategoryIds = $this->normalizeConfiguredTaxCategoryIds(
                $config->account_set_id,
                $config->tax_category_ids
            );
            $declarationDates = $this->buildDeclarationDates($year, $config);
            $forceCurrentTask = $targetMonth !== null && (
                (int) $config->id === $forceCurrentTaskConfigId
                || $this->wasConfigChangedInMonth($config, $targetMonth)
            );

            if ($forceCurrentTask) {
                $currentMonthDate = $targetMonth . '-01';
                if (!in_array($currentMonthDate, $declarationDates, true)) {
                    $declarationDates[] = $currentMonthDate;
                }
            }

            if (empty($declarationDates)) {
                continue;
            }

            $hasVisibleTargetMonth = false;

            foreach ($declarationDates as $declarationDate) {
                if ($visibleUntilDate !== null && $declarationDate > $visibleUntilDate) {
                    continue;
                }

                $isForcedCurrentTask = $forceCurrentTask && substr($declarationDate, 0, 7) === $targetMonth;

                if (!$isForcedCurrentTask && !$this->isDeclarationDateAvailable($config, $declarationDate)) {
                    continue;
                }

                if ($targetMonth !== null && substr($declarationDate, 0, 7) !== $targetMonth) {
                    continue;
                }

                $hasVisibleTargetMonth = true;

                $task = $this->findExistingTaskForMonth($config->id, $year, $declarationDate);

                if ($task) {
                    // 配置调整后保留仍然存在的已完成税种；新增税种自动回到待处理。
                    $previousCategoryIds = $task->getConfiguredTaxCategoryIds();
                    $storedCompletedIds = $task->completed_tax_category_ids;
                    $completedCategoryIds = $storedCompletedIds === null && $task->status === 'completed'
                        ? $previousCategoryIds
                        : $task->getCompletedTaxCategoryIdsList();
                    $declarationType = $config->declaration_type ?: $config->period_type;
                    $completedCategoryIds = array_values(array_intersect($currentCategoryIds, $completedCategoryIds));
                    $isCompleted = !empty($currentCategoryIds)
                        && empty(array_diff($currentCategoryIds, $completedCategoryIds));

                    $task->update([
                        'account_set_id' => $config->account_set_id,
                        'company_name' => $config->company_name,
                        'declaration_type' => $declarationType,
                        'tax_category_ids' => $currentCategoryIds,
                        'declaration_date' => $declarationDate,
                        'completed_tax_category_ids' => $completedCategoryIds,
                        'status' => $isCompleted ? 'completed' : 'pending',
                        'completed_at' => $isCompleted ? $task->completed_at : null,
                        'completed_by' => $isCompleted ? $task->completed_by : null,
                    ]);

                    if ($isCompleted) {
                        PendingTaskService::checkAndCompleteTaxDeclarationTask($task->fresh());
                    } else {
                        PendingTaskService::createTaxDeclarationTask($task->fresh());
                    }
                    continue;
                }

                $handler = $this->resolveTaskHandler($config);

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
                        'declaration_type' => $config->declaration_type ?: $config->period_type,
                        'company_name' => $config->company_name,
                        'tax_category_ids' => $currentCategoryIds,
                        'completed_tax_category_ids' => [],
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

            if ($hasVisibleTargetMonth) {
                $visibleConfigIds[] = $config->id;
            }
        }

        if ($targetMonth === null) {
            return $configs->pluck('id')->all();
        }

        $existingConfigIds = TaxDeclarationTask::where('account_set_id', $accountSetId)
            ->whereIn('config_id', $configs->pluck('id')->all())
            ->where('year', $year)
            ->whereYear('declaration_date', $year)
            ->whereMonth('declaration_date', (int) substr($targetMonth, 5, 2))
            ->pluck('config_id')
            ->all();

        return array_values(array_unique(array_merge($visibleConfigIds, $existingConfigIds)));
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

        if ($periodType === 'quarterly') {
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
            return array_map(function ($quarterStartMonth) use ($year) {
                return sprintf('%d-%02d-01', $year, $quarterStartMonth);
            }, [1, 4, 7, 10]);
        }

        if ($month < 1 || $month > 12) {
            return [];
        }

        return [sprintf('%d-%02d-01', $year, $month)];
    }

    private function isDeclarationDateAvailable(TaxDeclarationConfig $config, string $declarationDate): bool
    {
        $firstAvailableDate = $this->getFirstAvailableDeclarationDate($config);

        if ($firstAvailableDate === null) {
            return true;
        }

        return $declarationDate >= $firstAvailableDate;
    }

    private function getFirstAvailableDeclarationDate(TaxDeclarationConfig $config): ?string
    {
        if (!$config->created_at) {
            return null;
        }

        if ($config->period_type === 'monthly') {
            return $config->created_at->copy()->startOfMonth()->addMonth()->format('Y-m-01');
        }

        if ($config->period_type === 'quarterly') {
            return $config->created_at->copy()->startOfQuarter()->addQuarter()->format('Y-m-01');
        }

        return null;
    }

    /**
     * 当前月创建或修改的配置必须立即拥有当期任务。
     */
    private function wasConfigChangedInMonth(TaxDeclarationConfig $config, string $targetMonth): bool
    {
        return $config->created_at?->format('Y-m') === $targetMonth
            || $config->updated_at?->format('Y-m') === $targetMonth;
    }

    /**
     * 未配置税费审批人时，由配置创建人承接任务，避免任务被跳过。
     */
    private function resolveTaskHandler(TaxDeclarationConfig $config): ?object
    {
        $handler = ApprovalFlowConfig::getFirstEffectiveApprover(
            (int) $config->account_set_id,
            'tax_declaration'
        );

        if ($handler) {
            return $handler;
        }

        if ($config->created_by) {
            $creator = User::query()
                ->whereKey($config->created_by)
                ->where('is_active', true)
                ->first();

            if ($creator) {
                return $creator;
            }
        }

        $currentUser = Auth::user();

        return $currentUser instanceof User && $currentUser->is_active ? $currentUser : null;
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
            $task = TaxDeclarationTask::with(['config', 'handler', 'completedBy', 'attachments.uploader'])
                ->findOrFail($id);
            
            $this->appendTaskTaxCategoryState($task, $this->getTaxCategoryGroups($task->account_set_id));
            
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
     * 完成选中的税种申报
     */
    public function completeTask(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tax_category_ids' => 'required|array|min:1',
                'tax_category_ids.*' => 'integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择需要申报的税种'
                ], 422);
            }

            $task = TaxDeclarationTask::findOrFail($id);

            $selectedIds = $this->normalizeTaxCategoryIds($request->input('tax_category_ids'));
            $selectedIds = $this->normalizeConfiguredTaxCategoryIds(
                $task->account_set_id,
                $selectedIds
            );
            $configuredIds = $task->getConfiguredTaxCategoryIds();
            $completedIds = $task->getCompletedTaxCategoryIdsList();
            $invalidIds = array_diff($selectedIds, $configuredIds);
            $alreadyCompletedIds = array_intersect($selectedIds, $completedIds);

            if (!empty($invalidIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '选择的税种不属于当前申报任务'
                ], 422);
            }

            if (!empty($alreadyCompletedIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '已完成的税种不能重复申报'
                ], 422);
            }

            if ($task->status === 'completed' || empty($task->getPendingTaxCategoryIds())) {
                return response()->json([
                    'success' => false,
                    'message' => '该任务的税种已全部完成'
                ], 400);
            }

            DB::beginTransaction();

            $task->markTaxCategoriesCompleted($selectedIds, Auth::id());

            // 完成待办任务
            PendingTaskService::checkAndCompleteTaxDeclarationTask($task);

            DB::commit();

            $remainingIds = $task->fresh()->getPendingTaxCategoryIds();

            return response()->json([
                'success' => true,
                'message' => empty($remainingIds) ? '全部税种申报完成' : '所选税种申报完成',
                'data' => [
                    'status' => empty($remainingIds) ? 'completed' : 'pending',
                    'completed_tax_category_ids' => $task->fresh()->getCompletedTaxCategoryIdsList(),
                    'pending_tax_category_ids' => $remainingIds,
                ]
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
