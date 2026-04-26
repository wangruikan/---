<?php

namespace App\Http\Controllers;

use App\Models\MedicalInsuranceRegion;
use App\Models\MedicalInsuranceType;
use App\Models\OperationLog;
use App\Services\InsuranceChangeDetectionService;
use App\Services\InsuranceLimitPendingService;
use App\Models\InsuranceLimitPendingChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedicalInsuranceController extends Controller
{
    /**
     * 获取医保地区列表
     */
    public function getRegions(Request $request)
    {
        $accountSetId = $request->input('account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        $regions = MedicalInsuranceRegion::where('account_set_id', $accountSetId)
            ->with(['creator', 'medicalInsuranceTypes'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingMap = InsuranceLimitPendingChange::where('target_type', 'medical_insurance_region')
            ->where('account_set_id', $accountSetId)
            ->where('status', 'pending')
            ->get()
            ->keyBy('target_id');

        // 为每个地区添加 has_template 字段
        $regions->each(function ($region) use ($accountSetId, $pendingMap) {
            $region->has_template = \App\Models\ReportTemplate::where('region_id', $region->id)
                ->where('region_type', 'medical_insurance')
                ->where('account_set_id', $accountSetId)
                ->exists();

            $region->current_limits = [
                'min_base_amount' => $region->min_base_amount,
                'max_base_amount' => $region->max_base_amount,
            ];

            $pending = $pendingMap->get($region->id);
            $region->pending_limits = $pending ? [
                'id' => $pending->id,
                'min_base_amount' => $pending->pending_min_base_amount,
                'max_base_amount' => $pending->pending_max_base_amount,
                'effective_date' => optional($pending->effective_date)->toDateString(),
                'status' => $pending->status,
            ] : null;
        });

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 创建医保地区
     */
    public function createRegion(Request $request)
    {
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

        $accountSetId = $request->input('account_set_id');
        $name = $request->input('name');

        // 检查该账套下是否已存在同名地区
        $existingRegion = MedicalInsuranceRegion::where('account_set_id', $accountSetId)
            ->where('name', $name)
            ->first();

        if ($existingRegion) {
            return response()->json([
                'success' => false,
                'message' => '该地区已存在'
            ], 422);
        }

        // 记录接收到的数据（调试用）
        \Log::info('创建医保地区 - 接收到的数据', [
            'name' => $name,
            'code' => $request->input('code'),
            'company' => $request->input('company'),
            'min_base_amount' => $request->input('min_base_amount'),
            'max_base_amount' => $request->input('max_base_amount'),
            'account_set_id' => $accountSetId,
        ]);

        $region = MedicalInsuranceRegion::create([
            'name' => $name,
            'code' => $request->input('code'),
            'company' => $request->input('company'),
            'min_base_amount' => $request->input('min_base_amount'),
            'max_base_amount' => $request->input('max_base_amount'),
            'account_set_id' => $accountSetId,
            'created_by' => $request->user()->id
        ]);

        $region->load('creator');

        return response()->json([
            'success' => true,
            'message' => '医保地区创建成功',
            'data' => $region
        ]);
    }

    /**
     * 获取单个医保地区详情
     */
    public function showRegion($id)
    {
        $region = MedicalInsuranceRegion::with(['creator', 'medicalInsuranceTypes'])->findOrFail($id);
        $pending = InsuranceLimitPendingChange::where('target_type', 'medical_insurance_region')
            ->where('target_id', $region->id)
            ->where('status', 'pending')
            ->first();

        $region->current_limits = [
            'min_base_amount' => $region->min_base_amount,
            'max_base_amount' => $region->max_base_amount,
        ];
        $region->pending_limits = $pending ? [
            'id' => $pending->id,
            'min_base_amount' => $pending->pending_min_base_amount,
            'max_base_amount' => $pending->pending_max_base_amount,
            'effective_date' => optional($pending->effective_date)->toDateString(),
            'status' => $pending->status,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => $region
        ]);
    }

    /**
     * 更新医保地区
     */
    public function updateRegion(Request $request, $id)
    {
        \Log::info('=== 进入医保地区更新方法 ===', [
            'id' => $id,
            'request_all' => $request->all(),
        ]);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'min_base_amount' => 'nullable|numeric|min:0',
            'max_base_amount' => 'nullable|numeric|min:0',
            'limit_effective_date' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $region = MedicalInsuranceRegion::find($id);
        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => '医保地区不存在'
            ], 404);
        }

        // 检查权限
        $accountSetId = $request->input('account_set_id');
        if ($region->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该医保地区'
            ], 403);
        }

        // 保存旧数据用于变更检测
        $oldData = $region->toArray();

        $updateData = [];
        if ((string) $request->input('name') !== (string) $region->name) {
            $updateData['name'] = $request->input('name');
        }
        if ($request->has('code') && (string) $request->input('code') !== (string) $region->code) {
            $updateData['code'] = $request->input('code');
        }
        if ($request->has('company') && (string) $request->input('company') !== (string) $region->company) {
            $updateData['company'] = $request->input('company');
        }

        $importResult = null;
        $shouldDetect = false;

        $requestedMin = $request->has('min_base_amount') ? $request->input('min_base_amount') : $region->min_base_amount;
        $requestedMax = $request->has('max_base_amount') ? $request->input('max_base_amount') : $region->max_base_amount;
        $hasLimitChange = ($request->has('min_base_amount') || $request->has('max_base_amount'))
            && ((string) $requestedMin !== (string) $region->min_base_amount || (string) $requestedMax !== (string) $region->max_base_amount);

        if ($hasLimitChange) {
            if ($request->filled('limit_effective_date')) {
                $pendingService = app(InsuranceLimitPendingService::class);
                $pending = $pendingService->savePendingChange(
                    'medical_insurance_region',
                    $region->id,
                    $region->account_set_id,
                    $requestedMin,
                    $requestedMax,
                    $request->input('limit_effective_date'),
                    $request->user() ? $request->user()->id : null
                );

                \Log::info('医保上下限待生效变更已保存', [
                    'region_id' => $id,
                    'pending_id' => $pending->id,
                    'effective_date' => $pending->effective_date,
                ]);
            } else {
                $region->update([
                    'min_base_amount' => $requestedMin,
                    'max_base_amount' => $requestedMax,
                ]);
                $shouldDetect = true;
            }
        }

        // 记录接收到的数据（调试用）
        \Log::info('更新医保地区 - 接收到的数据', [
            'region_id' => $id,
            'region_name' => $region->name,
            'request_all' => $request->all(),
            'update_data' => $updateData,
        ]);

        if (!empty($updateData)) {
            $region->update($updateData);
            $shouldDetect = true;
        }

        if ($shouldDetect) {
            $detectionService = app(InsuranceChangeDetectionService::class);
            $importResult = $detectionService->detectAndImport('medical_insurance', $oldData, $region->toArray(), $region->id);
        }

        $pending = InsuranceLimitPendingChange::where('target_type', 'medical_insurance_region')
            ->where('target_id', $region->id)
            ->where('status', 'pending')
            ->first();

        $region->load('creator');
        $region->current_limits = [
            'min_base_amount' => $region->min_base_amount,
            'max_base_amount' => $region->max_base_amount,
        ];
        $region->pending_limits = $pending ? [
            'id' => $pending->id,
            'min_base_amount' => $pending->pending_min_base_amount,
            'max_base_amount' => $pending->pending_max_base_amount,
            'effective_date' => optional($pending->effective_date)->toDateString(),
            'status' => $pending->status,
        ] : null;

        return response()->json([
            'success' => true,
            'message' => '医保地区更新成功',
            'data' => $region,
            'import_result' => $importResult
        ]);
    }

    /**
     * 删除医保地区
     */
    public function deleteRegion(Request $request, $id)
    {
        $region = MedicalInsuranceRegion::find($id);
        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => '医保地区不存在'
            ], 404);
        }

        // 检查权限
        $accountSetId = $request->input('account_set_id');
        if ($region->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该医保地区'
            ], 403);
        }

        $region->delete();

        return response()->json([
            'success' => true,
            'message' => '医保地区删除成功'
        ]);
    }

    /**
     * 添加医保类型
     */
    public function addType(Request $request, $regionId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'employee_ratio' => 'required|numeric|min:0|max:1',
            'company_ratio' => 'required|numeric|min:0|max:1',
            'account_set_id' => 'required|exists:account_sets,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $accountSetId = $request->input('account_set_id');

        // 检查地区是否存在
        $region = MedicalInsuranceRegion::find($regionId);
        if (!$region || $region->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '医保地区不存在或无权限访问'
            ], 404);
        }

        $type = MedicalInsuranceType::create([
            'region_id' => $regionId,
            'name' => $request->input('name'),
            'employee_ratio' => $request->input('employee_ratio'),
            'company_ratio' => $request->input('company_ratio'),
            'account_set_id' => $accountSetId,
            'created_by' => $request->user()->id
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('medical_insurance', [], $type->toArray(), $regionId);

        return response()->json([
            'success' => true,
            'message' => '医保类型添加成功',
            'data' => $type,
            'import_result' => $importResult
        ]);
    }

    /**
     * 更新医保类型
     */
    public function updateType(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'employee_ratio' => 'required|numeric|min:0|max:1',
            'company_ratio' => 'required|numeric|min:0|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $type = MedicalInsuranceType::find($id);
        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => '医保类型不存在'
            ], 404);
        }

        // 检查权限
        $accountSetId = $request->input('account_set_id');
        if ($type->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该医保类型'
            ], 403);
        }

        // 保存旧数据用于变更检测
        $oldData = $type->toArray();

        $type->update([
            'name' => $request->input('name'),
            'employee_ratio' => $request->input('employee_ratio'),
            'company_ratio' => $request->input('company_ratio')
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('medical_insurance', $oldData, $type->toArray(), $type->region_id);

        return response()->json([
            'success' => true,
            'message' => '医保类型更新成功',
            'data' => $type,
            'import_result' => $importResult
        ]);
    }

    /**
     * 删除医保类型
     */
    public function deleteType(Request $request, $id)
    {
        $type = MedicalInsuranceType::find($id);
        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => '医保类型不存在'
            ], 404);
        }

        // 检查权限
        $accountSetId = $request->input('account_set_id');
        if ($type->account_set_id != $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该医保类型'
            ], 403);
        }

        // 保存删除前的数据用于变更检测
        $oldData = $type->toArray();
        $regionId = $type->region_id;

        $type->delete();

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('medical_insurance', $oldData, [], $regionId);

        return response()->json([
            'success' => true,
            'message' => '医保类型删除成功',
            'import_result' => $importResult
        ]);
    }

    /**
     * 获取医保地区上下限历史
     */
    public function getRegionLimitHistories(Request $request, $id)
    {
        $region = MedicalInsuranceRegion::find($id);
        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => '医保地区不存在'
            ], 404);
        }

        $accountSetId = $request->input('account_set_id');
        if ((int) $region->account_set_id !== (int) $accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '无权限访问该医保地区'
            ], 403);
        }

        $histories = OperationLog::where('model_type', MedicalInsuranceRegion::class)
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

