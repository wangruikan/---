<?php

namespace App\Http\Controllers;

use App\Models\SpecialDeductionItem;
use App\Models\EmployeeDeductionDetail;
use App\Models\Employee;
use App\Models\Project;
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
                'amount' => 'required|numeric|min:0',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'sort_order' => 'integer',
            ]);

            // 创建专项扣除项目
            $item = SpecialDeductionItem::create([
                'account_set_id' => $accountSetId,
                'name' => $validated['name'],
                'amount' => $validated['amount'],
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
                'amount' => 'numeric|min:0',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'sort_order' => 'integer',
            ]);

            // 确保 project_id 始终为 null（通用项目）
            $validated['project_id'] = null;
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

            // 获取所有在职员工及其项目信息
            $query = Employee::where('employees.account_set_id', $accountSetId)
                ->where('employees.contract_status', 'active') // 只显示在职员工
                ->leftJoin('employee_projects', 'employees.id', '=', 'employee_projects.employee_id')
                ->leftJoin('projects', 'employee_projects.project_id', '=', 'projects.id')
                ->select(
                    'employees.id as employee_id',
                    'employees.name as employee_name',
                    'employees.id_number',
                    'projects.id as project_id',
                    'projects.name as project_name'
                );

            // 按项目筛选
            if ($request->has('project_id') && $request->project_id !== '') {
                $query->where('projects.id', $request->project_id);
            }

            // 搜索员工姓名或身份证号
            if ($request->has('search') && $request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('employees.name', 'like', '%' . $request->search . '%')
                      ->orWhere('employees.id_number', 'like', '%' . $request->search . '%');
                });
            }

            $employees = $query->distinct()
                ->orderBy('employees.name', 'asc')
                ->paginate($request->get('per_page', 20));

            // 按员工分组，处理一个员工多个项目的情况
            $employeeGroups = [];
            foreach ($employees->items() as $employee) {
                $employeeId = $employee->employee_id;
                if (!isset($employeeGroups[$employeeId])) {
                    $employeeGroups[$employeeId] = [
                        'employee_id' => $employee->employee_id,
                        'employee_name' => $employee->employee_name,
                        'id_number' => $employee->id_number,
                        'projects' => []
                    ];
                }
                
                // 添加项目信息（如果有的话）
                if ($employee->project_id) {
                    $employeeGroups[$employeeId]['projects'][] = [
                        'project_id' => $employee->project_id,
                        'project_name' => $employee->project_name
                    ];
                }
            }

            // 为每个员工加载专项扣除信息
            $result = [];
            foreach ($employeeGroups as $employeeGroup) {
                $employeeId = $employeeGroup['employee_id'];
                
                // 获取该员工的所有专项扣除设置
                $deductionDetails = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                    ->where('employee_id', $employeeId)
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

                // 如果有项目关联，为每个项目创建一条记录
                if (!empty($employeeGroup['projects'])) {
                    foreach ($employeeGroup['projects'] as $project) {
                        $result[] = [
                            'employee_id' => $employeeId,
                            'employee_name' => $employeeGroup['employee_name'],
                            'id_number' => $employeeGroup['id_number'],
                            'project_id' => $project['project_id'],
                            'project_name' => $project['project_name'],
                            'deduction_items_array' => $deductionItems,
                            'deduction_items' => $deductionItems,
                            'deduction_detail_ids' => $deductionDetailIds,
                            'total_amount' => $totalAmount,
                            'effective_date' => null, // 已删除effective_date字段
                            'is_active' => $deductionDetails->count() > 0 ? true : false,
                            'has_deduction' => $deductionDetails->count() > 0
                        ];
                    }
                } else {
                    // 没有项目关联的员工
                    $result[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employeeGroup['employee_name'],
                        'id_number' => $employeeGroup['id_number'],
                        'project_id' => null,
                        'project_name' => '未分配项目',
                        'deduction_items_array' => $deductionItems,
                        'deduction_items' => $deductionItems,
                        'deduction_detail_ids' => $deductionDetailIds,
                        'total_amount' => $totalAmount,
                        'effective_date' => null, // 已删除effective_date字段
                        'is_active' => $deductionDetails->count() > 0 ? true : false,
                        'has_deduction' => $deductionDetails->count() > 0
                    ];
                }
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

            // 获取所有在职员工及其项目信息
            $query = Employee::where('employees.account_set_id', $accountSetId)
                ->where('employees.contract_status', 'active') // 只显示在职员工
                ->leftJoin('employee_projects', 'employees.id', '=', 'employee_projects.employee_id')
                ->leftJoin('projects', 'employee_projects.project_id', '=', 'projects.id')
                ->select(
                    'employees.id as employee_id',
                    'employees.name as employee_name',
                    'employees.id_number',
                    'projects.id as project_id',
                    'projects.name as project_name'
                );

            // 如果指定了项目，则筛选该项目下的员工
            if ($projectId) {
                $query->where('projects.id', $projectId);
            }

            $employees = $query->distinct()
                ->orderBy('employees.name', 'asc')
                ->get();

            // 按员工分组
            $employeeGroups = [];
            foreach ($employees as $employee) {
                $employeeId = $employee->employee_id;
                if (!isset($employeeGroups[$employeeId])) {
                    $employeeGroups[$employeeId] = [
                        'employee_id' => $employee->employee_id,
                        'employee_name' => $employee->employee_name,
                        'id_number' => $employee->id_number,
                        'projects' => []
                    ];
                }
                
                // 添加项目信息（如果有的话）
                if ($employee->project_id) {
                    $employeeGroups[$employeeId]['projects'][] = [
                        'project_id' => $employee->project_id,
                        'project_name' => $employee->project_name
                    ];
                }
            }

            // 获取已有专项扣除的员工ID（按项目筛选）
            $existingEmployeeIds = [];
            if ($projectId) {
                $existingEmployeeIds = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                    ->where('project_id', $projectId)
                    ->pluck('employee_id')
                    ->toArray();
            } else {
                // 如果没有指定项目，获取所有专项扣除的员工ID
                $existingEmployeeIds = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                    ->pluck('employee_id')
                    ->toArray();
            }

            $result = [];
            foreach ($employeeGroups as $employeeGroup) {
                // 如果有项目筛选，只显示该项目下的员工
                if ($projectId && empty($employeeGroup['projects'])) {
                    continue;
                }
                
                // 检查员工是否有项目关联（如果指定了项目）
                $hasProjectAccess = true;
                if ($projectId) {
                    $hasProjectAccess = false;
                    foreach ($employeeGroup['projects'] as $project) {
                        if ($project['project_id'] == $projectId) {
                            $hasProjectAccess = true;
                            break;
                        }
                    }
                }
                
                if ($hasProjectAccess) {
                    $result[] = [
                        'id' => $employeeGroup['employee_id'],
                        'name' => $employeeGroup['employee_name'],
                        'id_number' => $employeeGroup['id_number'],
                        'has_deduction' => in_array($employeeGroup['employee_id'], $existingEmployeeIds),
                    ];
                }
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
                'project_id' => 'required|exists:projects,id',
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
                    'project_id' => $validated['project_id'],
                ],
                [
                    'effective_date' => $validated['effective_date'] ?? now(),
                    'is_active' => $validated['is_active'] ?? true,
                    'updated_by' => $request->user()->id,
                ]
            );

            // 设置专项扣除项目
            $detail->setDeductionItemsFromArray($validated['deduction_items']);
            $detail->save();

            $detail->load(['employee', 'project']);

            DB::commit();

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
                'project_id' => 'required|exists:projects,id',
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
                            'project_id' => $validated['project_id'],
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

    // 获取员工的专项扣除详情
    public function getEmployeeDeductionDetail(Request $request, $employeeId)
    {
        if ($response = $this->checkPermission('special_deductions.view')) {
            return $response;
        }

        try {
            $accountSetId = $this->getAccountSetId($request);
            $projectId = $request->get('project_id');

            $detail = EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                ->where('employee_id', $employeeId)
                ->where('project_id', $projectId)
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
