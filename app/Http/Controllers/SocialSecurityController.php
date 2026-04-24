<?php

namespace App\Http\Controllers;

use App\Models\SocialSecurityRegion;
use App\Models\SocialSecurityType;
use App\Models\OperationLog;
use App\Models\AccountSet;
use App\Services\InsuranceChangeDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Traits\ChecksPermission;

class SocialSecurityController extends Controller
{
    use ChecksPermission;
    /**
     * 获取社保地区列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('social_security.view')) {
            return $response;
        }
        
        $user = $request->user();
        $accountSetId = $request->input('account_set_id');

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $accountSetId)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        $regions = SocialSecurityRegion::where('account_set_id', $accountSetId)
            ->with(['creator', 'socialSecurityTypes'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 为每个地区添加 has_template 字段
        $regions->each(function ($region) use ($accountSetId) {
            $region->has_template = \App\Models\ReportTemplate::where('region_id', $region->id)
                ->where('region_type', 'social_security')
                ->where('account_set_id', $accountSetId)
                ->exists();
        });

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 创建社保地区
     */
    public function store(Request $request)
    {
        if ($response = $this->checkPermission('social_security.create')) {
            return $response;
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'account_set_id' => 'required|exists:account_sets,id',
            'min_base_amount' => 'nullable|numeric|min:0',
            'max_base_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $request->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        // 检查地区名称是否重复
        $exists = SocialSecurityRegion::where('account_set_id', $request->account_set_id)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '该地区已存在'
            ], 422);
        }

        // 记录接收到的数据（调试用）
        \Log::info('创建社保地区 - 接收到的数据', [
            'name' => $request->name,
            'code' => $request->code,
            'company' => $request->company,
            'min_base_amount' => $request->min_base_amount,
            'max_base_amount' => $request->max_base_amount,
            'account_set_id' => $request->account_set_id,
        ]);

        $region = SocialSecurityRegion::create([
            'name' => $request->name,
            'code' => $request->code,
            'company' => $request->company,
            'min_base_amount' => $request->min_base_amount,
            'max_base_amount' => $request->max_base_amount,
            'account_set_id' => $request->account_set_id,
            'created_by' => $user->id,
        ]);

        $region->load(['creator', 'socialSecurityTypes']);

        return response()->json([
            'success' => true,
            'message' => '社保地区创建成功',
            'data' => $region
        ]);
    }

    /**
     * 获取单个社保地区详情
     */
    public function show($id)
    {
        if ($response = $this->checkPermission('social_security.view')) {
            return $response;
        }
        
        $region = SocialSecurityRegion::with(['creator', 'socialSecurityTypes'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $region
        ]);
    }

    /**
     * 更新社保地区
     */
    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('social_security.update')) {
            return $response;
        }
        
        \Log::info('=== 进入社保地区更新方法 ===', [
            'id' => $id,
            'request_all' => $request->all(),
        ]);
        $region = SocialSecurityRegion::findOrFail($id);
        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $region->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        // 检查是否允许调整基数
        $accountSet = $region->accountSet;
        if ($request->has('adjustment_base') && !$accountSet->canAdjustBaseInCurrentMonth()) {
            return response()->json([
                'success' => false,
                'message' => '当前月份不允许调整基数，请在账套设置中配置允许调整的月份'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'min_base_amount' => 'nullable|numeric|min:0',
            'max_base_amount' => 'nullable|numeric|min:0',
            'adjustment_base' => 'nullable|numeric|min:0',
            'effective_date' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $region = SocialSecurityRegion::findOrFail($id);
        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $region->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        // 检查地区名称是否重复（仅在更新名称时检查）
        if ($request->has('name')) {
            $exists = SocialSecurityRegion::where('account_set_id', $region->account_set_id)
                ->where('name', $request->name)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => '该地区已存在'
                ], 422);
            }
        }

        // 准备更新数据
        $updateData = [];
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('code')) {
            $updateData['code'] = $request->code;
        }
        if ($request->has('company')) {
            $updateData['company'] = $request->company;
        }
        if ($request->has('min_base_amount')) {
            $updateData['min_base_amount'] = $request->min_base_amount;
        }
        if ($request->has('max_base_amount')) {
            $updateData['max_base_amount'] = $request->max_base_amount;
        }
        if ($request->has('adjustment_base')) {
            $updateData['adjustment_base'] = $request->adjustment_base;
        }
        if ($request->has('effective_date')) {
            $updateData['effective_date'] = $request->effective_date;
        }

        // 记录接收到的数据（调试用）
        \Log::info('更新社保地区 - 接收到的数据', [
            'region_id' => $id,
            'region_name' => $region->name,
            'request_all' => $request->all(),
            'update_data' => $updateData,
        ]);

        // 保存旧数据用于变更检测
        $oldData = $region->toArray();

        // ✅ 处理上下限更新和补差
        $compensationResult = null;
        if ($request->has('min_base_amount') || $request->has('max_base_amount')) {
            $limitUpdateService = app(\App\Services\BaseLimitUpdateService::class);
            $updateResult = $limitUpdateService->updateLimits(
                $region,
                $request->input('min_base_amount'),
                $request->input('max_base_amount')
            );

            \Log::info('上下限更新结果', $updateResult);

            // 如果需要补差，调用补差服务
            if ($updateResult['should_compensate']) {
                $compensationService = app(\App\Services\BaseLimitCompensationService::class);
                $compensationResult = $compensationService->calculateSocialSecurityCompensation(
                    $region->id,
                    $updateResult['old_min'],
                    $updateResult['old_max'],
                    $updateResult['new_min'],
                    $updateResult['new_max']
                );
                
                \Log::info('补差计算结果', $compensationResult);
            }
        }

        $region->update($updateData);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('social_security', $oldData, $updateData, $region->id);

        $region->load(['creator', 'socialSecurityTypes']);

        return response()->json([
            'success' => true,
            'message' => '社保地区更新成功',
            'data' => $region,
            'import_result' => $importResult,
            'compensation_result' => $compensationResult
        ]);
    }

    /**
     * 删除社保地区
     */
    public function destroy(Request $request, $id)
    {
        if ($response = $this->checkPermission('social_security.delete')) {
            return $response;
        }
        
        $region = SocialSecurityRegion::findOrFail($id);
        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $region->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            // 删除关联的社保类型
            $region->socialSecurityTypes()->delete();
            // 删除地区
            $region->delete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => '社保地区删除成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 添加社保类型
     */
    public function addType(Request $request, $regionId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'employee_ratio' => 'required|numeric|min:0|max:1',
            'company_ratio' => 'required|numeric|min:0|max:1',
            'only_company_pay' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $region = SocialSecurityRegion::findOrFail($regionId);
        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $region->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        // 检查保险名称是否重复
        $exists = SocialSecurityType::where('region_id', $regionId)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '该保险类型已存在'
            ], 422);
        }

        $employeeRatio = $request->boolean('only_company_pay') ? 0 : $request->employee_ratio;

        $type = SocialSecurityType::create([
            'region_id' => $regionId,
            'name' => $request->name,
            'employee_ratio' => $employeeRatio,
            'company_ratio' => $request->company_ratio,
            'created_by' => $user->id,
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('social_security', [], $type->toArray(), $type->region_id);

        $type->load(['creator']);

        return response()->json([
            'success' => true,
            'message' => '社保类型添加成功',
            'data' => $type,
            'import_result' => $importResult
        ]);
    }

    /**
     * 更新社保类型
     */
    public function updateType(Request $request, $typeId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'employee_ratio' => 'required|numeric|min:0|max:1',
            'company_ratio' => 'required|numeric|min:0|max:1',
            'only_company_pay' => 'nullable|boolean',
            'min_base_amount' => 'nullable|numeric|min:0',
            'max_base_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $type = SocialSecurityType::findOrFail($typeId);
        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $type->region->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        // 检查保险名称是否重复
        $exists = SocialSecurityType::where('region_id', $type->region_id)
            ->where('name', $request->name)
            ->where('id', '!=', $typeId)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '该保险类型已存在'
            ], 422);
        }

        // 保存旧数据用于变更检测
        $oldData = $type->toArray();

        $employeeRatio = $request->boolean('only_company_pay') ? 0 : $request->employee_ratio;

        // 更新基本信息（类型不处理上下限，上下限在地区层级）
        $type->update([
            'name' => $request->name,
            'employee_ratio' => $employeeRatio,
            'company_ratio' => $request->company_ratio,
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('social_security', $oldData, $type->toArray(), $type->region_id);

        $type->load(['creator']);

        return response()->json([
            'success' => true,
            'message' => '社保类型更新成功',
            'data' => $type,
            'import_result' => $importResult
        ]);
    }

    /**
     * 删除社保类型
     */
    public function destroyType(Request $request, $typeId)
    {
        $type = SocialSecurityType::findOrFail($typeId);
        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $type->region->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        // 保存删除前的数据用于变更检测
        $oldData = $type->toArray();
        $regionId = $type->region_id;

        $type->delete();

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('social_security', $oldData, [], $regionId);

        return response()->json([
            'success' => true,
            'message' => '社保类型删除成功',
            'import_result' => $importResult
        ]);
    }

    /**
     * 获取社保地区上下限历史
     */
    public function getRegionLimitHistories(Request $request, $id)
    {
        if ($response = $this->checkPermission('social_security.view')) {
            return $response;
        }

        $region = SocialSecurityRegion::findOrFail($id);
        $user = $request->user();

        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $region->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        $histories = OperationLog::where('model_type', SocialSecurityRegion::class)
            ->where('model_id', $region->id)
            ->where('action', 'updated')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                $oldValues = $log->old_values ?? [];
                $newValues = $log->new_values ?? [];
                return [
                    'changed_at' => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : null,
                    'min_base_amount' => array_key_exists('min_base_amount', $newValues) ? $newValues['min_base_amount'] : ($oldValues['min_base_amount'] ?? null),
                    'max_base_amount' => array_key_exists('max_base_amount', $newValues) ? $newValues['max_base_amount'] : ($oldValues['max_base_amount'] ?? null),
                ];
            })
            ->filter(function ($item) {
                return !is_null($item['min_base_amount']) || !is_null($item['max_base_amount']);
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $histories,
        ]);
    }
}
