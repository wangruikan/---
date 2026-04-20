<?php

namespace App\Http\Controllers;

use App\Models\OtherInsuranceType;
use App\Models\OtherInsurancePolicy;
use App\Services\InsuranceChangeDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

class OtherInsuranceController extends Controller
{
    use ChecksPermission;
    /**
     * 获取保险种类列表
     */
    public function getTypes(Request $request)
    {
        // 其他保险查看权限
        if ($response = $this->checkPermission('other_insurance.view')) {
            return $response;
        }

        $accountSetId = $request->input('current_account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        $types = OtherInsuranceType::where('account_set_id', $accountSetId)
            ->with(['creator', 'policies' => function($query) {
                $query->where('status', 'active');
            }])
            ->withCount(['policies as active_policies_count' => function($query) {
                $query->where('status', 'active');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * 创建保险种类
     */
    public function createType(Request $request)
    {
        // 其他保险新增权限
        if ($response = $this->checkPermission('other_insurance.create')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'current_account_set_id' => 'required|exists:account_sets,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $accountSetId = $request->input('current_account_set_id');
        $name = $request->input('name');

        // 检查该账套下是否已存在同名的保险种类
        $existingType = OtherInsuranceType::where('account_set_id', $accountSetId)
            ->where('name', $name)
            ->first();

        if ($existingType) {
            return response()->json([
                'success' => false,
                'message' => '该保险种类已存在'
            ], 422);
        }

        $type = OtherInsuranceType::create([
            'name' => $name,
            'description' => $request->input('description'),
            'account_set_id' => $accountSetId,
            'created_by' => $request->user()->id
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('other_insurance', [], $type->toArray(), $type->id);

        $type->load('creator');

        return response()->json([
            'success' => true,
            'message' => '保险种类创建成功',
            'data' => $type,
            'import_result' => $importResult
        ]);
    }

    /**
     * 更新保险种类
     */
    public function updateType(Request $request, $id)
    {
        // 其他保险编辑权限
        if ($response = $this->checkPermission('other_insurance.edit')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $type = OtherInsuranceType::find($id);
        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => '保险种类不存在'
            ], 404);
        }

        // 检查权限
        $accountSetId = $request->input('current_account_set_id');
        if ($type->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该保险种类'
            ], 403);
        }

        // 检查名称是否重复
        $existingType = OtherInsuranceType::where('account_set_id', $accountSetId)
            ->where('name', $request->input('name'))
            ->where('id', '!=', $id)
            ->first();

        if ($existingType) {
            return response()->json([
                'success' => false,
                'message' => '该保险种类名称已存在'
            ], 422);
        }

        // 保存旧数据用于变更检测
        $oldData = $type->toArray();

        $type->update([
            'name' => $request->input('name'),
            'description' => $request->input('description')
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('other_insurance', $oldData, $type->toArray(), $type->id);

        $type->load('creator');

        return response()->json([
            'success' => true,
            'message' => '保险种类更新成功',
            'data' => $type,
            'import_result' => $importResult
        ]);
    }

    /**
     * 删除保险种类
     */
    public function deleteType(Request $request, $id)
    {
        // 其他保险删除权限
        if ($response = $this->checkPermission('other_insurance.delete')) {
            return $response;
        }

        $type = OtherInsuranceType::find($id);
        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => '保险种类不存在'
            ], 404);
        }

        // 检查权限
        $accountSetId = $request->input('current_account_set_id');
        if ($type->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该保险种类'
            ], 403);
        }

        // 检查是否有关联的保单
        if ($type->policies()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '该保险种类下还有保单，无法删除'
            ], 422);
        }

        // 保存删除前的数据用于变更检测
        $oldData = $type->toArray();
        $regionId = $type->id;
        
        $type->delete();

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('other_insurance', $oldData, [], $regionId);

        return response()->json([
            'success' => true,
            'message' => '保险种类删除成功',
            'import_result' => $importResult
        ]);
    }

    /**
     * 获取保单列表
     */
    public function getPolicies(Request $request, $typeId)
    {
        // 其他保险查看权限
        if ($response = $this->checkPermission('other_insurance.view')) {
            return $response;
        }

        $accountSetId = $request->input('current_account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        $type = OtherInsuranceType::find($typeId);
        if (!$type || $type->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '保险种类不存在或无权限访问'
            ], 404);
        }

        $policies = OtherInsurancePolicy::where('type_id', $typeId)
            ->where('account_set_id', $accountSetId)
            ->with(['creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $policies
        ]);
    }

    /**
     * 创建保单
     */
    public function createPolicy(Request $request, $typeId)
    {
        // 其他保险新增权限
        if ($response = $this->checkPermission('other_insurance.create')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'policy_number' => 'required|string|max:100',
            'policy_name' => 'required|string|max:200',
            'insurance_company' => 'required|string|max:200',
            'coverage_amount' => 'required|numeric|min:0',
            'employee_per_capita_cost' => 'required|numeric|min:0',
            'quota' => 'nullable|integer|min:0',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'personnel_name_list' => 'nullable|array',
            'personnel_name_list.*' => 'string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string',
            'current_account_set_id' => 'required|exists:account_sets,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $accountSetId = $request->input('current_account_set_id');

        // 检查保险种类是否存在
        $type = OtherInsuranceType::find($typeId);
        if (!$type || $type->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '保险种类不存在或无权限访问'
            ], 404);
        }

        // 检查保单号是否重复
        $existingPolicy = OtherInsurancePolicy::where('account_set_id', $accountSetId)
            ->where('policy_number', $request->input('policy_number'))
            ->first();

        if ($existingPolicy) {
            return response()->json([
                'success' => false,
                'message' => '该保单号已存在'
            ], 422);
        }

        $policy = OtherInsurancePolicy::create([
            'type_id' => $typeId,
            'policy_number' => $request->input('policy_number'),
            'policy_name' => $request->input('policy_name'),
            'insurance_company' => $request->input('insurance_company'),
            'coverage_amount' => $request->input('coverage_amount'),
            'employee_per_capita_cost' => $request->input('employee_per_capita_cost'),
            'quota' => $request->input('quota', 0),
            'contact_name' => $request->input('contact_name'),
            'contact_phone' => $request->input('contact_phone'),
            'personnel_name_list' => $request->input('personnel_name_list', []),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'description' => $request->input('description'),
            'account_set_id' => $accountSetId,
            'created_by' => $request->user()->id
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('other_insurance', [], $policy->toArray(), $policy->id);

        $policy->load(['creator', 'type']);

        return response()->json([
            'success' => true,
            'message' => '保单创建成功',
            'data' => $policy,
            'import_result' => $importResult
        ]);
    }

    /**
     * 更新保单
     */
    public function updatePolicy(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'policy_number' => 'required|string|max:100',
            'policy_name' => 'required|string|max:200',
            'insurance_company' => 'required|string|max:200',
            'coverage_amount' => 'required|numeric|min:0',
            'employee_per_capita_cost' => 'required|numeric|min:0',
            'quota' => 'nullable|integer|min:0',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'personnel_name_list' => 'nullable|array',
            'personnel_name_list.*' => 'string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:active,expired,cancelled',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $policy = OtherInsurancePolicy::find($id);
        if (!$policy) {
            return response()->json([
                'success' => false,
                'message' => '保单不存在'
            ], 404);
        }

        // 检查权限
        $accountSetId = $request->input('current_account_set_id');
        if ($policy->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该保单'
            ], 403);
        }

        // 检查保单号是否重复（排除当前保单）
        $existingPolicy = OtherInsurancePolicy::where('account_set_id', $accountSetId)
            ->where('policy_number', $request->input('policy_number'))
            ->where('id', '!=', $id)
            ->first();

        if ($existingPolicy) {
            return response()->json([
                'success' => false,
                'message' => '该保单号已存在'
            ], 422);
        }

        // 保存旧数据用于变更检测
        $oldData = $policy->toArray();

        $policy->update([
            'policy_number' => $request->input('policy_number'),
            'policy_name' => $request->input('policy_name'),
            'insurance_company' => $request->input('insurance_company'),
            'coverage_amount' => $request->input('coverage_amount'),
            'employee_per_capita_cost' => $request->input('employee_per_capita_cost'),
            'quota' => $request->input('quota', 0),
            'contact_name' => $request->input('contact_name'),
            'contact_phone' => $request->input('contact_phone'),
            'personnel_name_list' => $request->input('personnel_name_list', []),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'status' => $request->input('status'),
            'description' => $request->input('description')
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('other_insurance', $oldData, $policy->toArray(), $policy->id);

        $policy->load(['creator', 'type']);

        return response()->json([
            'success' => true,
            'message' => '保单更新成功',
            'data' => $policy,
            'import_result' => $importResult
        ]);
    }

    /**
     * 删除保单
     */
    public function deletePolicy(Request $request, $id)
    {
        $policy = OtherInsurancePolicy::find($id);
        if (!$policy) {
            return response()->json([
                'success' => false,
                'message' => '保单不存在'
            ], 404);
        }

        // 检查权限
        $accountSetId = $request->input('current_account_set_id');
        if ($policy->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该保单'
            ], 403);
        }

        // 保存删除前的数据用于变更检测
        $oldData = $policy->toArray();
        $regionId = $policy->id;
        
        $policy->delete();

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('other_insurance', $oldData, [], $regionId);

        return response()->json([
            'success' => true,
            'message' => '保单删除成功',
            'import_result' => $importResult
        ]);
    }
}
