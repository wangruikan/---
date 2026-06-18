<?php

namespace App\Http\Controllers;

use App\Models\SpecialDeductionItem;
use App\Models\EmployeeDeductionDetail;
use App\Models\Employee;
use App\Models\Project;
use App\Services\PendingTaskService;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpecialDeductionController extends Controller
{
    use ChecksPermission;

    /**
     * 获取当前用户的账套ID
     */
    private function getAccountSetId(Request $request)
    {
        $currentAccountSetId = $request->input('current_account_set_id');
        
        // 如果没有传递账套ID，尝试从用户的第一个账套获取
        if (!$currentAccountSetId) {
            $user = $request->user();
            if ($user && $user->accountSets()->exists()) {
                $firstAccountSet = $user->accountSets()->first();
                if ($firstAccountSet) {
                    return $firstAccountSet->id;
                } else {
                    throw new \Exception('用户没有关联的账套，请联系管理员分配账套');
                }
            } else {
                throw new \Exception('请先选择账套或联系管理员分配账套');
            }
        }
        
        return $currentAccountSetId;
    }

    private function normalizeMonth(?string $month = null): string
    {
        if ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $month;
        }

        return now('Asia/Shanghai')->format('Y-m');
    }

    private function activeEmployeesQuery($accountSetId)
    {
        return Employee::where('employees.account_set_id', $accountSetId)
            ->where('employees.contract_status', 'active');
    }

    private function getProjectNamesByEmployeeIds(array $employeeIds): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        return DB::table('employee_projects')
            ->join('projects', 'employee_projects.project_id', '=', 'projects.id')
            ->whereIn('employee_projects.employee_id', $employeeIds)
            ->select('employee_projects.employee_id', 'projects.name as project_name')
            ->orderBy('projects.name', 'asc')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($rows) {
                return $rows->pluck('project_name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode('、');
            })
            ->toArray();
    }

    private function normalizeImportValue($value): string
    {
        return trim((string)($value ?? ''));
    }

    private function getImportCell(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $this->normalizeImportValue($row[$key]);
            }
        }

        foreach ($row as $rowKey => $value) {
            $normalizedKey = $this->normalizeImportValue($rowKey);
            if (in_array($normalizedKey, $keys, true)) {
                return $this->normalizeImportValue($value);
            }
        }

        return '';
    }

    private function normalizeImportIdNumber($value): string
    {
        return strtoupper(str_replace(["'", ' ', "\t", "\r", "\n", '　'], '', $this->normalizeImportValue($value)));
    }

    private function getScientificIdPrefix(string $idNumber): ?string
    {
        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $idNumber, $matches)) {
            return null;
        }

        if (($matches[1] ?? '') === '-') {
            return null;
        }

        $digits = ltrim(($matches[2] ?? '') . ($matches[3] ?? ''), '0');
        return strlen($digits) >= 6 ? $digits : null;
    }

    private function getIdNumberFallbackPrefixes(string $idNumber): array
    {
        $prefixes = [];
        $scientificPrefix = $this->getScientificIdPrefix($idNumber);
        if ($scientificPrefix) {
            $prefixes[] = $scientificPrefix;
        }

        if (preg_match('/^\d{18}$/', $idNumber)) {
            $trimmed = rtrim($idNumber, '0');
            if (strlen($trimmed) >= 6 && strlen($trimmed) < 18) {
                $prefixes[] = $trimmed;
            }

            // 18位身份证被 Excel 当数字保存后会丢失尾部精度，例如
            // 110101197608227871 可能变成 110101197608228000。
            // 精确匹配失败时，用区县+出生年月日前缀兜底，再结合姓名限定。
            $prefixes[] = substr($idNumber, 0, 14);
            $prefixes[] = substr($idNumber, 0, 12);
        }

        return array_values(array_unique($prefixes));
    }

    private function findEmployeeByImportedIdNumber($accountSetId, string $idNumber, string $employeeName = '', ?string &$errorMessage = null): ?Employee
    {
        $employee = Employee::where('account_set_id', $accountSetId)
            ->where('id_number', $idNumber)
            ->first();

        if ($employee) {
            return $employee;
        }

        foreach ($this->getIdNumberFallbackPrefixes($idNumber) as $prefix) {
            $query = Employee::where('account_set_id', $accountSetId)
                ->where('id_number', 'like', $prefix . '%');

            if ($employeeName !== '') {
                $query->where('name', $employeeName);
            }

            $candidates = $query->limit(2)->get();
            if ($candidates->count() === 1) {
                return $candidates->first();
            }

            if ($candidates->count() > 1) {
                $errorMessage = "身份证号 {$idNumber} 匹配到多名员工，请将身份证号列设置为文本后重新导入";
                return null;
            }
        }

        return null;
    }

    // 获取专项扣除项目列表
    public function getDeductionItems(Request $request)
    {
        if ($response = $this->checkPermission('special_deductions.view')) {
            return $response;
        }

        try {
            // 获取当前用户的账套ID
            $accountSetId = $this->getAccountSetId($request);
            
            // 按账套筛选
            $query = SpecialDeductionItem::where('account_set_id', $accountSetId);

            // 按状态筛选
            if ($request->has('is_active') && $request->is_active !== '' && $request->is_active !== null) {
                $query->where('is_active', $request->is_active);
            }

            // 搜索
            if ($request->has('search') && $request->search) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }


            $items = $query->orderBy('sort_order', 'asc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));


            return response()->json([
                'success' => true,
                'data' => $items->items(),
                'total' => $items->total(),
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('获取专项扣除项目失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 创建专项扣除项目
    public function createDeductionItem(Request $request)
    {
        if ($response = $this->checkPermission('special_deductions.create')) {
            return $response;
        }

        try {
            // 获取当前用户的账套ID
            $accountSetId = $this->getAccountSetId($request);
            
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'amount' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'sort_order' => 'integer',
            ]);

            // 创建专项扣除项目
            $item = SpecialDeductionItem::create([
                'account_set_id' => $accountSetId,
                'name' => $validated['name'],
                'amount' => $validated['amount'] ?? 0,
                'project_id' => null,  // 所有扣除项目都是通用的
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => '创建成功',
                'data' => $item
            ]);
        } catch (\Exception $e) {
            Log::error('创建专项扣除项目失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 更新专项扣除项目
    public function updateDeductionItem(Request $request, $id)
    {
        if ($response = $this->checkPermission('special_deductions.edit')) {
            return $response;
        }

        try {
            // 获取当前用户的账套ID
            $accountSetId = $this->getAccountSetId($request);
            
            // 按账套查找记录
            $item = SpecialDeductionItem::where('account_set_id', $accountSetId)
                ->findOrFail($id);

            $validated = $request->validate([
                'name' => 'string|max:100',
                'amount' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'sort_order' => 'integer',
            ]);

            // 确保 project_id 始终为 null（通用项目）
            $validated['project_id'] = null;
            $validated['amount'] = $validated['amount'] ?? 0;
            $item->update($validated);

            return response()->json([
                'success' => true,
                'message' => '更新成功',
                'data' => $item
            ]);
        } catch (\Exception $e) {
            Log::error('更新专项扣除项目失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 删除专项扣除项目
    public function deleteDeductionItem(Request $request, $id)
    {
        if ($response = $this->checkPermission('special_deductions.delete')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);

            $item = SpecialDeductionItem::where('account_set_id', $accountSetId)
                ->findOrFail($id);

            // 检查是否有员工使用该项目
            $usageCount = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                ->where('deduction_items', 'like', '%' . $id . ':%')
                ->count();

            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "该专项扣除项目已被 {$usageCount} 名员工使用，无法删除"
                ], 400);
            }

            $item->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除专项扣除项目失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 获取员工专项扣除列表（显示所有在职员工）
    public function getEmployeeDeductions(Request $request)
    {
        if ($response = $this->checkPermission('special_deductions.view')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);
            $month = $this->normalizeMonth($request->input('month'));

            // 以员工为分页单位，项目只用于筛选和展示，避免多项目员工重复占行。
            $query = $this->activeEmployeesQuery($accountSetId)
                ->select(
                    'employees.id as employee_id',
                    'employees.name as employee_name',
                    'employees.id_number'
                );

            // 按项目筛选
            if ($request->has('project_id') && $request->project_id !== '') {
                $query->whereExists(function ($q) use ($request) {
                    $q->select(DB::raw(1))
                        ->from('employee_projects')
                        ->whereColumn('employee_projects.employee_id', 'employees.id')
                        ->where('employee_projects.project_id', $request->project_id);
                });
            }

            // 搜索员工姓名或身份证号
            if ($request->has('search') && $request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('employees.name', 'like', '%' . $request->search . '%')
                      ->orWhere('employees.id_number', 'like', '%' . $request->search . '%');
                });
            }

            $employees = $query
                ->orderBy('employees.name', 'asc')
                ->paginate($request->get('per_page', 20));

            $employeeIds = collect($employees->items())->pluck('employee_id')->filter()->values()->all();
            $projectNamesByEmployeeId = $this->getProjectNamesByEmployeeIds($employeeIds);

            // 为每个员工加载专项扣除信息
            $result = [];
            foreach ($employees->items() as $employee) {
                $employeeId = $employee->employee_id;
                
                // 获取该员工的所有专项扣除设置
                $deductionDetails = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                    ->where('employee_id', $employeeId)
                    ->whereNull('project_id')
                    ->where('month', $month)
                    ->where('is_active', true)
                    ->get();

                $deductionItems = [];
                $totalAmount = 0;
                $deductionDetailIds = [];
                foreach ($deductionDetails as $detail) {
                    // 使用deduction_items_array访问器获取扣除项目
                    $items = $detail->deduction_items_array;
                    foreach ($items as $item) {
                        $deductionItems[] = [
                            'id' => $item['id'],
                            'name' => $item['name'],
                            'amount' => $item['amount']
                        ];
                    }
                    $totalAmount += $detail->total_amount;
                    $deductionDetailIds[] = $detail->id;
                }

                $result[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->employee_name,
                    'id_number' => $employee->id_number,
                    'project_id' => null,
                    'project_name' => $projectNamesByEmployeeId[$employeeId] ?? '未分配项目',
                    'deduction_items_array' => $deductionItems,
                    'deduction_items' => $deductionItems,
                    'deduction_detail_ids' => $deductionDetailIds,
                    'total_amount' => $totalAmount,
                    'month' => $month,
                    'effective_date' => null, // 已删除effective_date字段
                    'is_active' => $deductionDetails->count() > 0 ? true : false,
                    'has_deduction' => $deductionDetails->count() > 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'total' => $employees->total(),
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('获取员工专项扣除失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 获取项目下的员工（用于批量设置）
    public function getProjectEmployees(Request $request)
    {
        if ($response = $this->checkPermission('special_deductions.view')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);
            $projectId = $request->get('project_id');
            $month = $this->normalizeMonth($request->input('month'));

            // 获取在职员工，项目仅作为筛选条件。
            $query = $this->activeEmployeesQuery($accountSetId)
                ->select(
                    'employees.id as employee_id',
                    'employees.name as employee_name',
                    'employees.id_number'
                );

            // 如果指定了项目，则筛选该项目下的员工
            if ($projectId) {
                $query->whereExists(function ($q) use ($projectId) {
                    $q->select(DB::raw(1))
                        ->from('employee_projects')
                        ->whereColumn('employee_projects.employee_id', 'employees.id')
                        ->where('employee_projects.project_id', $projectId);
                });
            }

            $employees = $query
                ->orderBy('employees.name', 'asc')
                ->get();

            $existingEmployeeIds = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                ->whereNull('project_id')
                ->where('month', $month)
                ->where('is_active', true)
                ->pluck('employee_id')
                ->toArray();

            $result = [];
            foreach ($employees as $employee) {
                $result[] = [
                    'id' => $employee->employee_id,
                    'name' => $employee->employee_name,
                    'id_number' => $employee->id_number,
                    'has_deduction' => in_array($employee->employee_id, $existingEmployeeIds),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('获取项目员工失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 设置员工专项扣除
    public function setEmployeeDeduction(Request $request)
    {
        if ($response = $this->checkPermission('special_deductions.edit')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);

            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'project_id' => 'nullable|exists:projects,id',
                'month' => 'nullable|date_format:Y-m',
                'deduction_items' => 'required|array',
                'deduction_items.*.id' => 'required|exists:special_deduction_items,id',
                'deduction_items.*.amount' => 'required|numeric|min:0',
                'effective_date' => 'nullable|date',
                'is_active' => 'boolean',
            ]);

            DB::beginTransaction();

            // 查找或创建员工专项扣除记录
            $detail = EmployeeDeductionDetail::updateOrCreate(
                [
                    'account_set_id' => $accountSetId,
                    'employee_id' => $validated['employee_id'],
                    'project_id' => null,
                    'month' => $this->normalizeMonth($validated['month'] ?? null),
                ],
                [
                    'is_active' => $validated['is_active'] ?? true,
                    'updated_by' => $request->user()->id,
                ]
            );

            // 设置专项扣除项目
            $detail->setDeductionItemsFromArray($validated['deduction_items']);
            $detail->save();

            $detail->load(['employee', 'project']);

            DB::commit();
            PendingTaskService::createSpecialDeductionTask($accountSetId, $this->normalizeMonth($validated['month'] ?? null));

            return response()->json([
                'success' => true,
                'message' => '设置成功',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('设置员工专项扣除失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '设置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 批量设置员工专项扣除
    public function batchSetEmployeeDeduction(Request $request)
    {
        if ($response = $this->checkPermission('special_deductions.edit')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);

            $validated = $request->validate([
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'exists:employees,id',
                'project_id' => 'nullable|exists:projects,id',
                'month' => 'nullable|date_format:Y-m',
                'deduction_items' => 'required|array',
                'deduction_items.*.id' => 'required|exists:special_deduction_items,id',
                'deduction_items.*.amount' => 'required|numeric|min:0',
                'is_active' => 'boolean',
            ]);

            DB::beginTransaction();

            $successCount = 0;
            $errorCount = 0;

            foreach ($validated['employee_ids'] as $employeeId) {
                try {
                    // 先创建或更新记录，设置基本的扣除项目信息
                    $detail = EmployeeDeductionDetail::updateOrCreate(
                        [
                            'account_set_id' => $accountSetId,
                            'employee_id' => $employeeId,
                            'project_id' => null,
                            'month' => $this->normalizeMonth($validated['month'] ?? null),
                        ],
                        [
                            'deduction_items' => '', // 先设置空值，避免数据库错误
                            'total_amount' => 0, // 先设置0值
                            'is_active' => $validated['is_active'] ?? true,
                            'updated_by' => $request->user() ? $request->user()->id : null,
                        ]
                    );

                    // 然后设置具体的扣除项目信息
                    $detail->setDeductionItemsFromArray($validated['deduction_items']);
                    $detail->save();

                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("批量设置员工{$employeeId}专项扣除失败", [
                        'employee_id' => $employeeId,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();
            PendingTaskService::createSpecialDeductionTask($accountSetId, $this->normalizeMonth($validated['month'] ?? null));

            return response()->json([
                'success' => true,
                'message' => "批量设置完成，成功 {$successCount} 名员工" . ($errorCount > 0 ? "，失败 {$errorCount} 名员工" : ""),
                'success_count' => $successCount,
                'error_count' => $errorCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('批量设置员工专项扣除失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '批量设置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 导入员工专项扣除
    public function importEmployeeDeductions(Request $request)
    {
        if ($response = $this->checkPermission('special_deductions.edit')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);

            $validated = $request->validate([
                'rows' => 'required|array|min:1',
                'rows.*' => 'array',
                'project_id' => 'nullable|exists:projects,id',
                'month' => 'nullable|date_format:Y-m',
            ]);

            $project = null;
            if (!empty($validated['project_id'])) {
                $project = Project::where('account_set_id', $accountSetId)
                    ->find($validated['project_id']);
            }

            if (!empty($validated['project_id']) && !$project) {
                return response()->json([
                    'success' => false,
                    'message' => '所选项目不存在或不属于当前账套'
                ], 422);
            }

            $deductionItems = SpecialDeductionItem::where('account_set_id', $accountSetId)
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($deductionItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '请先添加启用的扣除项目'
                ], 422);
            }

            $successCount = 0;
            $errors = [];

            foreach ($validated['rows'] as $index => $row) {
                $rowNumber = $index + 2;

                try {
                    $idNumber = $this->normalizeImportIdNumber($this->getImportCell($row, ['身份证号', '身份证号码', '证件号码']));
                    if ($idNumber === '') {
                        $errors[] = "第 {$rowNumber} 行：身份证号不能为空";
                        continue;
                    }

                    $employeeName = $this->getImportCell($row, ['员工姓名', '姓名']);
                    $matchError = null;
                    $employee = $this->findEmployeeByImportedIdNumber($accountSetId, $idNumber, $employeeName, $matchError);

                    if (!$employee) {
                        $errors[] = "第 {$rowNumber} 行：" . ($matchError ?: "未找到身份证号为 {$idNumber} 的员工");
                        continue;
                    }

                    if ($project) {
                        $hasProject = DB::table('employee_projects')
                            ->where('employee_id', $employee->id)
                            ->where('project_id', $project->id)
                            ->exists();

                        if (!$hasProject) {
                            $errors[] = "第 {$rowNumber} 行：员工 {$employee->name} 不属于项目 {$project->name}";
                            continue;
                        }
                    }

                    $importDeductionItems = [];
                    foreach ($deductionItems as $deductionItem) {
                        $amountText = $this->getImportCell($row, [$deductionItem->name]);
                        if ($amountText === '') {
                            continue;
                        }

                        if (!is_numeric($amountText) || (float) $amountText < 0) {
                            $errors[] = "第 {$rowNumber} 行：{$deductionItem->name} 请填写大于等于 0 的金额";
                            continue 2;
                        }

                        $importDeductionItems[] = [
                            'id' => $deductionItem->id,
                            'amount' => round((float) $amountText, 2),
                        ];
                    }

                    if (empty($importDeductionItems)) {
                        $importDeductionItems = $deductionItems->map(function ($deductionItem) {
                            return [
                                'id' => $deductionItem->id,
                                'amount' => 0,
                            ];
                        })->all();
                    }

                    DB::transaction(function () use ($accountSetId, $employee, $project, $importDeductionItems, $request, $validated) {
                        $detail = EmployeeDeductionDetail::updateOrCreate(
                            [
                                'account_set_id' => $accountSetId,
                                'employee_id' => $employee->id,
                                'project_id' => null,
                                'month' => $this->normalizeMonth($validated['month'] ?? null),
                            ],
                            [
                                'deduction_items' => '',
                                'total_amount' => 0,
                                'is_active' => true,
                                'updated_by' => $request->user() ? $request->user()->id : null,
                            ]
                        );

                        $detail->setDeductionItemsFromArray($importDeductionItems);
                        $detail->save();
                    });

                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = "第 {$rowNumber} 行：导入失败，{$e->getMessage()}";
                    Log::error('导入员工专项扣除行失败', [
                        'row_number' => $rowNumber,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $errorCount = count($errors);
            PendingTaskService::createSpecialDeductionTask($accountSetId, $this->normalizeMonth($validated['month'] ?? null));

            return response()->json([
                'success' => true,
                'message' => "导入完成，成功 {$successCount} 条" . ($errorCount > 0 ? "，失败 {$errorCount} 条" : ""),
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('导入员工专项扣除失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '导入失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 获取员工的专项扣除详情
    public function getEmployeeDeductionDetail(Request $request, $employeeId)
    {
        if ($response = $this->checkPermission('special_deductions.view')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);
            $projectId = $request->get('project_id');
            $month = $this->normalizeMonth($request->input('month'));

            $detail = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                ->where('employee_id', $employeeId)
                ->whereNull('project_id')
                ->where('month', $month)
                ->with(['employee', 'project'])
                ->first();

            if (!$detail) {
                return response()->json([
                    'success' => true,
                    'data' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            Log::error('获取员工专项扣除详情失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 删除员工的专项扣除
    public function deleteEmployeeDeduction(Request $request, $id)
    {
        if ($response = $this->checkPermission('special_deductions.delete')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);

            $detail = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                ->findOrFail($id);

            $detail->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除员工专项扣除失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
