<?php

namespace App\Http\Controllers;

use App\Models\LargeMedicalInsuranceConfig;
use App\Models\LargeMedicalInsuranceConfigHistory;
use App\Models\EmployeeLargeMedicalInsurance;
use App\Services\InsuranceChangeDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\ChecksPermission;

class LargeMedicalInsuranceController extends Controller
{
    use ChecksPermission;
    /**
     * 获取大额医疗保险配置列表
     */
    public function index(Request $request)
    {
        // 大额医疗查看权限
        if ($response = $this->checkPermission('large_medical.view')) {
            return $response;
        }

        try {
            $user = $request->user();
            // 兼容两种参数名
            $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
            
            // 如果请求中没有account_set_id，尝试从用户获取
            if (!$accountSetId && $user) {
                $accountSetId = $user->account_set_id;
            }
            
            // 强制转换为整数
            $accountSetId = (int)$accountSetId;
            
            // 如果还是没有，返回错误
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 直接查询，不使用scope
            $configs = LargeMedicalInsuranceConfig::where('account_set_id', $accountSetId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $configs
            ]);
        } catch (\Exception $e) {
            Log::error('获取大额医疗保险配置失败: ' . $e->getMessage());
            Log::error('错误堆栈: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => '获取配置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建大额医疗保险配置
     */
    public function store(Request $request)
    {
        // 大额医疗新增权限
        if ($response = $this->checkPermission('large_medical.create')) {
            return $response;
        }

        try {
            $user = $request->user();
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId && $user) {
                $accountSetId = $user->account_set_id;
            }
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'region_name' => 'required|string|max:100',
                'calculation_type' => 'required|in:base,fixed',
                'base_source' => 'nullable|in:employee,config', // employee=员工基数, config=统一基数
                'payment_cycle' => 'required|in:month,year',
                'base_amount' => 'nullable|numeric|min:0',
                'employee_base_amount' => 'nullable|numeric|min:0',
                'company_ratio' => 'nullable|numeric|min:0|max:1',
                'employee_ratio' => 'nullable|numeric|min:0|max:1',
                'company_amount' => 'nullable|numeric|min:0',
                'employee_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 检查地区是否已存在
            $exists = LargeMedicalInsuranceConfig::byAccountSet($accountSetId)
                ->where('region_name', $request->region_name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => '该地区的大额医疗保险配置已存在'
                ], 422);
            }

            $config = LargeMedicalInsuranceConfig::create([
                'region_name' => $request->region_name,
                'account_set_id' => $accountSetId,
                'calculation_type' => $request->calculation_type,
                'base_source' => $request->input('base_source', 'employee'),
                'base_amount' => $request->base_amount,
                'employee_base_amount' => $request->employee_base_amount,
                'company_ratio' => $request->company_ratio,
                'employee_ratio' => $request->employee_ratio,
                'company_amount' => $request->company_amount,
                'employee_amount' => $request->employee_amount,
                'payment_cycle' => $request->payment_cycle,
                'status' => $request->input('status', 1),
                'remarks' => $request->remarks,
                'created_by' => $user ? $user->id : 1
            ]);

            // 检测保险信息变更并自动导入到增减模块
            $detectionService = app(InsuranceChangeDetectionService::class);
            $importResult = $detectionService->detectAndImport('large_medical_insurance', [], $config->toArray(), $config->id);

            Log::info('大额医疗保险配置创建成功', [
                'config_id' => $config->id,
                'account_set_id' => $config->account_set_id,
                'region_name' => $config->region_name
            ]);

            return response()->json([
                'success' => true,
                'message' => '创建成功',
                'data' => $config->load('creator'),
                'import_result' => $importResult
            ]);
        } catch (\Exception $e) {
            Log::error('创建大额医疗保险配置失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '创建失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新大额医疗保险配置
     */
    public function update(Request $request, $id)
    {
        // 大额医疗编辑权限
        if ($response = $this->checkPermission('large_medical.update')) {
            return $response;
        }

        try {
            $user = $request->user();
            $config = LargeMedicalInsuranceConfig::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'region_name' => 'sometimes|required|string|max:100',
                'calculation_type' => 'sometimes|required|in:base,fixed',
                'base_source' => 'nullable|in:employee,config',
                'payment_cycle' => 'sometimes|required|in:month,year',
                'base_amount' => 'nullable|numeric|min:0',
                'employee_base_amount' => 'nullable|numeric|min:0',
                'company_ratio' => 'nullable|numeric|min:0|max:1',
                'employee_ratio' => 'nullable|numeric|min:0|max:1',
                'company_amount' => 'nullable|numeric|min:0',
                'employee_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 保存旧数据用于变更检测
            $oldData = $config->toArray();

            $config->update($request->all());

            // 检测保险信息变更并自动导入到增减模块
            $detectionService = app(InsuranceChangeDetectionService::class);
            $importResult = $detectionService->detectAndImport('large_medical_insurance', $oldData, $config->toArray(), $config->id);

            return response()->json([
                'success' => true,
                'message' => '更新成功',
                'data' => $config->load('creator'),
                'import_result' => $importResult
            ]);
        } catch (\Exception $e) {
            Log::error('更新大额医疗保险配置失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '更新失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除大额医疗保险配置
     */
    public function destroy($id)
    {
        // 大额医疗删除权限
        if ($response = $this->checkPermission('large_medical.delete')) {
            return $response;
        }

        try {
            $config = LargeMedicalInsuranceConfig::findOrFail($id);
            
            // 检查是否有关联的员工
            $employeeCount = EmployeeLargeMedicalInsurance::where('config_id', $id)->count();
            if ($employeeCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "该配置已被{$employeeCount}个员工使用，无法删除"
                ], 422);
            }

            // 保存删除前的数据用于变更检测
            $oldData = $config->toArray();
            $regionId = $config->id;
            
            $config->delete();

            // 检测保险信息变更并自动导入到增减模块
            $detectionService = app(InsuranceChangeDetectionService::class);
            $importResult = $detectionService->detectAndImport('large_medical_insurance', $oldData, [], $regionId);

            return response()->json([
                'success' => true,
                'message' => '删除成功',
                'import_result' => $importResult
            ]);
        } catch (\Exception $e) {
            Log::error('删除大额医疗保险配置失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取地区列表
     */
    public function getRegions(Request $request)
    {
        try {
            $user = $request->user();
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId && $user) {
                $accountSetId = $user->account_set_id;
            }
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $regions = LargeMedicalInsuranceConfig::byAccountSet($accountSetId)
                ->active()
                ->distinct()
                ->pluck('region_name');

            return response()->json([
                'success' => true,
                'data' => $regions
            ]);
        } catch (\Exception $e) {
            Log::error('获取大额医疗保险地区列表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取地区列表失败'
            ], 500);
        }
    }

    /**
     * 根据地区获取配置
     */
    public function getByRegion(Request $request, $regionName)
    {
        try {
            $user = $request->user();
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId && $user) {
                $accountSetId = $user->account_set_id;
            }
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $config = LargeMedicalInsuranceConfig::byAccountSet($accountSetId)
                ->byRegion($regionName)
                ->active()
                ->first();

            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Exception $e) {
            Log::error('获取大额医疗保险配置失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取配置失败'
            ], 500);
        }
    }

    /**
     * 批量设置员工大额医疗保险
     */
    public function batchSetEmployees(Request $request)
    {
        try {
            $user = $request->user();
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId && $user) {
                $accountSetId = $user->account_set_id;
            }
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'required|integer|exists:employees,id',
                'config_id' => 'required|integer|exists:large_medical_insurance_configs,id',
                'is_enabled' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::transaction(function () use ($request, $accountSetId) {
                foreach ($request->employee_ids as $employeeId) {
                    EmployeeLargeMedicalInsurance::updateOrCreate(
                        ['employee_id' => $employeeId],
                        [
                            'config_id' => $request->config_id,
                            'account_set_id' => $accountSetId,
                            'is_enabled' => $request->is_enabled
                        ]
                    );
                }
            });

            return response()->json([
                'success' => true,
                'message' => '设置成功'
            ]);
        } catch (\Exception $e) {
            Log::error('批量设置员工大额医疗保险失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '设置失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 设置待生效的配置变更
     */
    public function setPendingChanges(Request $request, $id)
    {
        try {
            $user = $request->user();
            $config = LargeMedicalInsuranceConfig::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'effective_date' => 'required|date|after_or_equal:today',
                'changes' => 'required|array',
                'changes.base_amount' => 'nullable|numeric|min:0',
                'changes.employee_base_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $config->setPendingChanges(
                $request->input('changes'),
                $request->input('effective_date'),
                $user ? $user->id : null,
                $user ? $user->name : null
            );

            Log::info('设置大额医疗保险配置待生效变更', [
                'config_id' => $config->id,
                'region_name' => $config->region_name,
                'effective_date' => $request->input('effective_date'),
                'changes' => $request->input('changes')
            ]);

            return response()->json([
                'success' => true,
                'message' => '待生效变更设置成功，将在 ' . $request->input('effective_date') . ' 自动生效',
                'data' => $config->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('设置待生效变更失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '设置失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取消待生效的配置变更
     */
    public function cancelPendingChanges(Request $request, $id)
    {
        try {
            $config = LargeMedicalInsuranceConfig::findOrFail($id);

            if (!$config->hasPendingChanges()) {
                return response()->json([
                    'success' => false,
                    'message' => '该配置没有待生效的变更'
                ], 400);
            }

            $config->update([
                'pending_changes' => null,
                'effective_date' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => '待生效变更已取消',
                'data' => $config->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('取消待生效变更失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '取消失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取配置的历史记录
     */
    public function getHistories(Request $request, $id)
    {
        try {
            $config = LargeMedicalInsuranceConfig::findOrFail($id);

            $histories = LargeMedicalInsuranceConfigHistory::where('config_id', $id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $histories
            ]);
        } catch (\Exception $e) {
            Log::error('获取配置历史记录失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取历史记录失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取所有配置的历史记录（按账套）
     */
    public function getAllHistories(Request $request)
    {
        try {
            $user = $request->user();
            $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
            
            if (!$accountSetId && $user) {
                $accountSetId = $user->account_set_id;
            }
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $query = LargeMedicalInsuranceConfigHistory::where('account_set_id', $accountSetId);

            // 按地区筛选
            if ($request->filled('region_name')) {
                $query->where('region_name', $request->input('region_name'));
            }

            // 按变更类型筛选
            if ($request->filled('change_type')) {
                $query->where('change_type', $request->input('change_type'));
            }

            // 按日期范围筛选
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->input('start_date'));
            }
            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->input('end_date'));
            }

            $histories = $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $histories
            ]);
        } catch (\Exception $e) {
            Log::error('获取所有配置历史记录失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取历史记录失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 立即应用待生效的变更（手动触发）
     */
    public function applyPendingChanges(Request $request, $id)
    {
        try {
            $config = LargeMedicalInsuranceConfig::findOrFail($id);

            if (!$config->hasPendingChanges()) {
                return response()->json([
                    'success' => false,
                    'message' => '该配置没有待生效的变更'
                ], 400);
            }

            // 强制设置生效日期为今天，然后应用
            $config->effective_date = now()->toDateString();
            $config->save();

            if ($config->applyPendingChanges()) {
                return response()->json([
                    'success' => true,
                    'message' => '配置变更已立即生效',
                    'data' => $config->fresh()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '应用变更失败'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('立即应用变更失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '应用失败：' . $e->getMessage()
            ], 500);
        }
    }
}

