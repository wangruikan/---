<?php

namespace App\Http\Controllers;

use App\Models\BaseAdjustment;
use App\Models\Employee;
use App\Models\AccountSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\ChecksPermission;

class BaseAdjustmentController extends Controller
{
    use ChecksPermission;
    /**
     * 检查当前是否在允许调整的月份内
     */
    private function canAdjustBase(Request $request): array
    {
        $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
        if (!$accountSetId) {
            return [
                'allowed' => false,
                'message' => '请先选择账套'
            ];
        }

        $accountSet = AccountSet::find($accountSetId);
        if (!$accountSet) {
            Log::error('账套不存在', ['account_set_id' => $accountSetId]);
            return [
                'allowed' => false,
                'message' => '账套不存在'
            ];
        }

        // 获取允许调整的月份
        $adjustmentMonths = [];
        if ($accountSet->base_adjustment_months) {
            try {
                // 如果已经是数组，直接使用；否则解析JSON
                if (is_array($accountSet->base_adjustment_months)) {
                    $adjustmentMonths = $accountSet->base_adjustment_months;
                } else {
                    $adjustmentMonths = json_decode($accountSet->base_adjustment_months, true);
                }
            } catch (\Exception $e) {
                Log::error('解析基数调整月份配置失败', [
                    'account_set_id' => $accountSetId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (empty($adjustmentMonths)) {
            return [
                'allowed' => false,
                'message' => '该账套未配置基数调整月份，请在账套设置中配置'
            ];
        }

        $currentMonth = (int) date('n'); // 1-12
        if (!in_array($currentMonth, $adjustmentMonths)) {
            return [
                'allowed' => false,
                'message' => '当前月份不允许调整基数，允许调整的月份为：' . implode('、', $adjustmentMonths) . '月'
            ];
        }

        return [
            'allowed' => true,
            'message' => '可以调整'
        ];
    }

    /**
     * 获取在职员工列表（带调差记录）
     */
    public function index(Request $request)
    {
        // 基数调差查看权限
        if ($response = $this->checkPermission('base_adjustment.view')) {
            return $response;
        }

        // 兼容前端传入的 account_set_id 或请求拦截器添加的 current_account_set_id
        $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        // 查询在职员工（合同状态为active或approved）
        $query = Employee::with(['projects'])
            ->where('account_set_id', $accountSetId)
            ->whereIn('contract_status', ['active', 'approved']);

        // 员工姓名搜索
        if ($request->has('employee_name') && $request->employee_name) {
            $query->where('name', 'like', '%' . $request->employee_name . '%');
        }

        // 项目筛选
        if ($request->has('project_id') && $request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        $employees = $query->orderBy('created_at', 'desc')->get();

        // 为每个员工添加调差记录信息
        $employeesWithAdjustments = $employees->map(function ($employee) {
            // 查找该员工最新的调差记录（包括待生效和已生效）
            $latestAdjustment = BaseAdjustment::where('employee_id', $employee->id)
                ->whereIn('status', ['pending', 'applied'])
                ->orderBy('created_at', 'desc')
                ->first();

            // 获取第一个项目（员工可能关联多个项目）
            $firstProject = $employee->projects->first();
            
            // 获取员工的大额配置的基数来源
            $largeMedicalBaseSource = 'employee'; // 默认普通地区
            if ($employee->large_medical_insurance_config_id) {
                $largeMedicalConfig = \App\Models\LargeMedicalInsuranceConfig::find($employee->large_medical_insurance_config_id);
                if ($largeMedicalConfig) {
                    $largeMedicalBaseSource = $largeMedicalConfig->base_source ?? 'employee';
                }
            }
            
            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'id_number' => $employee->id_number,
                'project_id' => $firstProject ? $firstProject->id : null,
                'project_name' => $firstProject ? $firstProject->name : '',
                'current_social_security_base' => $employee->social_security_base ?? 0,
                'current_medical_insurance_base' => $employee->medical_insurance_base ?? 0,
                'current_housing_fund_base' => $employee->housing_fund_base ?? 0,
                'current_large_medical_base' => $employee->large_medical_base ?? 0,
                'current_large_medical_company_base' => $employee->large_medical_company_base ?? 0,
                'large_medical_base_source' => $largeMedicalBaseSource,
                'adjustment' => $latestAdjustment ? [
                    'id' => $latestAdjustment->id,
                    'status' => $latestAdjustment->status,
                    'applied_at' => $latestAdjustment->applied_at,
                    'new_social_security_base' => $latestAdjustment->new_social_security_base,
                    'new_medical_insurance_base' => $latestAdjustment->new_medical_insurance_base,
                    'new_housing_fund_base' => $latestAdjustment->new_housing_fund_base,
                    'new_large_medical_base' => $latestAdjustment->new_large_medical_base,
                    'new_large_medical_company_base' => $latestAdjustment->new_large_medical_company_base,
                    'social_security_effective_date' => $latestAdjustment->social_security_effective_date ? $latestAdjustment->social_security_effective_date->format('Y-m-d') : null,
                    'medical_insurance_effective_date' => $latestAdjustment->medical_insurance_effective_date ? $latestAdjustment->medical_insurance_effective_date->format('Y-m-d') : null,
                    'housing_fund_effective_date' => $latestAdjustment->housing_fund_effective_date ? $latestAdjustment->housing_fund_effective_date->format('Y-m-d') : null,
                    'large_medical_effective_date' => $latestAdjustment->large_medical_effective_date ? $latestAdjustment->large_medical_effective_date->format('Y-m-d') : null,
                    'adjustment_reason' => $latestAdjustment->adjustment_reason,
                    'created_at' => $latestAdjustment->created_at ? $latestAdjustment->created_at->format('Y-m-d H:i:s') : null
                ] : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $employeesWithAdjustments
        ]);
    }

    /**
     * 创建或更新基数调整记录
     */
    public function store(Request $request)
    {
        // 基数调差新增/编辑权限
        if ($response = $this->checkPermission('base_adjustment.create')) {
            return $response;
        }

        // 检查权限
        $canAdjust = $this->canAdjustBase($request);
        if (!$canAdjust['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $canAdjust['message']
            ], 403);
        }

        // 动态验证规则：只有填写了基数的字段才需要验证对应的生效时间
        $rules = [
            'employee_id' => 'required|exists:employees,id',
            'account_set_id' => 'required|exists:account_sets,id',
            'adjustment_reason' => 'nullable|string|max:500'
        ];

        // 如果填写了社保基数，则验证社保生效时间
        if ($request->filled('new_social_security_base')) {
            $rules['new_social_security_base'] = 'numeric|min:0';
            $rules['social_security_effective_date'] = 'required|date|after_or_equal:today';
        }

        // 如果填写了医保基数，则验证医保生效时间
        if ($request->filled('new_medical_insurance_base')) {
            $rules['new_medical_insurance_base'] = 'numeric|min:0';
            $rules['medical_insurance_effective_date'] = 'required|date|after_or_equal:today';
        }

        // 如果填写了公积金基数，则验证公积金生效时间
        if ($request->filled('new_housing_fund_base')) {
            $rules['new_housing_fund_base'] = 'numeric|min:0';
            $rules['housing_fund_effective_date'] = 'required|date|after_or_equal:today';
        }

        // 如果填写了大额医疗基数（个人基数或公司基数），则验证大额医疗生效时间
        if ($request->filled('new_large_medical_base') || $request->filled('new_large_medical_company_base')) {
            $rules['new_large_medical_base'] = 'nullable|numeric|min:0';
            $rules['new_large_medical_company_base'] = 'nullable|numeric|min:0';
            $rules['large_medical_effective_date'] = 'required|date|after_or_equal:today';
        }

        // 检查是否至少填写了一个基数
        if (!$request->filled('new_social_security_base') && 
            !$request->filled('new_medical_insurance_base') && 
            !$request->filled('new_housing_fund_base') && 
            !$request->filled('new_large_medical_base') &&
            !$request->filled('new_large_medical_company_base')) {
            return response()->json([
                'success' => false,
                'message' => '请至少填写一个基数'
            ], 422);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 检查用户是否已登录（使用sanctum guard）
        if (!auth('sanctum')->check()) {
            return response()->json([
                'success' => false,
                'message' => '用户未登录'
            ], 401);
        }

        $employee = Employee::find($request->employee_id);
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '员工不存在'
            ], 404);
        }
        
        // 检查是否在职（合同状态为active或approved）
        if (!in_array($employee->contract_status, ['active', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => '员工不是在职状态'
            ], 403);
        }

        try {
            // 允许创建多条待生效的调整记录（不同生效日期可以独立管理）
            $createData = [
                'employee_id' => $request->employee_id,
                'account_set_id' => $request->account_set_id,
                'status' => 'pending',
                'adjustment_reason' => $request->input('adjustment_reason'),
                'created_by' => auth('sanctum')->id()
            ];

            // 只添加填写了基数的字段
            if ($request->filled('new_social_security_base')) {
                $createData['new_social_security_base'] = $request->new_social_security_base;
                $createData['social_security_effective_date'] = $request->social_security_effective_date;
                // 保存社保上下限
                $createData['social_security_min_base'] = $request->input('social_security_min_base');
                $createData['social_security_max_base'] = $request->input('social_security_max_base');
            }

            if ($request->filled('new_medical_insurance_base')) {
                $createData['new_medical_insurance_base'] = $request->new_medical_insurance_base;
                $createData['medical_insurance_effective_date'] = $request->medical_insurance_effective_date;
                // 保存医保上下限
                $createData['medical_insurance_min_base'] = $request->input('medical_insurance_min_base');
                $createData['medical_insurance_max_base'] = $request->input('medical_insurance_max_base');
            }

            if ($request->filled('new_housing_fund_base')) {
                $createData['new_housing_fund_base'] = $request->new_housing_fund_base;
                $createData['housing_fund_effective_date'] = $request->housing_fund_effective_date;
                // 保存公积金上下限
                $createData['housing_fund_min_base'] = $request->input('housing_fund_min_base');
                $createData['housing_fund_max_base'] = $request->input('housing_fund_max_base');
            }

            if ($request->filled('new_large_medical_base')) {
                $createData['new_large_medical_base'] = $request->new_large_medical_base;
                $createData['large_medical_effective_date'] = $request->large_medical_effective_date;
            }

            if ($request->filled('new_large_medical_company_base')) {
                $createData['new_large_medical_company_base'] = $request->new_large_medical_company_base;
                $createData['old_large_medical_company_base'] = $employee->large_medical_company_base;
                // 如果没有设置个人基数，确保生效日期已设置
                if (!isset($createData['large_medical_effective_date'])) {
                    $createData['large_medical_effective_date'] = $request->large_medical_effective_date;
                }
            }

            $adjustment = BaseAdjustment::create($createData);

            return response()->json([
                'success' => true,
                'message' => '基数调整创建成功',
                'data' => $adjustment->load(['employee', 'creator'])
            ]);
        } catch (\Exception $e) {
            Log::error('创建基数调整记录失败', [
                'employee_id' => $request->employee_id,
                'account_set_id' => $request->account_set_id,
                'user_id' => auth('sanctum')->id(),
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '操作失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除基数调整记录
     */
    public function destroy($id)
    {
        $adjustment = BaseAdjustment::find($id);
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        // 只有待生效状态可以删除
        if ($adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待生效状态的记录可以删除'
            ], 403);
        }

        try {
            $adjustment->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 手动应用基数调整（立即生效）
     */
    public function applyNow(Request $request, $id)
    {
        // 基数调差编辑权限
        if ($response = $this->checkPermission('base_adjustment.update')) {
            return $response;
        }

        $adjustment = BaseAdjustment::find($id);
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在'
            ], 404);
        }

        if ($adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '只有待生效状态的记录可以立即生效'
            ], 403);
        }

        try {
            $result = $adjustment->apply();
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => '基数调整已生效'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '应用失败'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('应用基数调整失败', [
                'adjustment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '应用失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量应用已到期的基数调整（定时任务调用）
     */
    public function applyDue(Request $request)
    {
        try {
            $adjustments = BaseAdjustment::pending()
                ->effective()
                ->whereNull('applied_at')
                ->get();

            $successCount = 0;
            $failCount = 0;

            foreach ($adjustments as $adjustment) {
                try {
                    if ($adjustment->apply()) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                } catch (\Exception $e) {
                    $failCount++;
                    Log::error('应用基数调整失败', [
                        'adjustment_id' => $adjustment->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => '批量应用完成',
                'data' => [
                    'total' => count($adjustments),
                    'success' => $successCount,
                    'fail' => $failCount
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('批量应用基数调整失败', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '批量应用失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取调整权限状态（当前月份是否允许调整、提示文案等）
     * 方法名与 Trait ChecksPermission 的 checkPermission($permission) 区分，避免 index 中调用权限检查时被误解析
     */
    public function getAdjustStatus(Request $request)
    {
        $result = $this->canAdjustBase($request);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * 获取调差记录历史
     */
    public function history(Request $request, $employeeId)
    {
        $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        $adjustments = BaseAdjustment::with(['creator'])
            ->where('employee_id', $employeeId)
            ->where('account_set_id', $accountSetId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $adjustments
        ]);
    }
}

