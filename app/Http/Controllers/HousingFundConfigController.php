<?php

namespace App\Http\Controllers;

use App\Models\HousingFundConfig;
use App\Models\HousingFundRegion;
use App\Models\InsuranceLimitPendingChange;
use App\Services\InsuranceLimitPendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\ChecksPermission;

class HousingFundConfigController extends Controller
{
    use ChecksPermission;
    /**
     * 获取公积金配置列表
     */
    public function index(Request $request)
    {
        // 公积金查看权限
        if ($response = $this->checkPermission('housing_fund.view')) {
            return $response;
        }

        $user = $request->user();
        $regionId = $request->input('region_id');
        $accountSetId = $request->input('account_set_id');
        
        $query = HousingFundConfig::with(['creator', 'region']);
        
        if ($accountSetId) {
            $query->where('account_set_id', $accountSetId);
        }
        
        // 按地区筛选
        if ($regionId) {
            $query->where('region_id', $regionId);
        }
        
        $configs = $query->orderBy('region_id')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $pendingMap = InsuranceLimitPendingChange::where('target_type', 'housing_fund_config')
            ->where('status', 'pending')
            ->whereIn('target_id', $configs->pluck('id')->all())
            ->get()
            ->keyBy('target_id');

        Log::info('HousingFund configs pending check', [
            'config_ids' => $configs->pluck('id')->all(),
            'pending_records' => $pendingMap->toArray()
        ]);

        $configs->each(function ($config) use ($pendingMap) {
            $config->current_limits = [
                'min_base_amount' => $config->min_base_amount,
                'max_base_amount' => $config->max_base_amount,
            ];

            $pending = $pendingMap->get($config->id);
            $config->pending_limits = $pending ? [
                'id' => $pending->id,
                'min_base_amount' => $pending->pending_min_base_amount,
                'max_base_amount' => $pending->pending_max_base_amount,
                'effective_date' => optional($pending->effective_date)->toDateString(),
                'status' => $pending->status,
            ] : null;
        });

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * 创建公积金配置
     */
    public function store(Request $request)
    {
        // 公积金新增权限
        if ($response = $this->checkPermission('housing_fund.create')) {
            return $response;
        }

        $user = $request->user();
        
        $request->validate([
            'region_id' => 'required|exists:housing_fund_regions,id',
            'config_name' => 'required|string|max:255',
            'min_base_amount' => 'required|numeric|min:0',
            'max_base_amount' => 'required|numeric|min:0',
            'employee_ratio' => 'required|numeric|min:0|max:1',
            'company_ratio' => 'required|numeric|min:0|max:1',
            'account_set_id' => 'required|exists:account_sets,id',
        ]);
        
        $config = HousingFundConfig::create([
            'region_id' => $request->region_id,
            'config_name' => $request->config_name,
            'base_amount' => $request->base_amount ?? 0,
            'min_base_amount' => $request->min_base_amount,
            'max_base_amount' => $request->max_base_amount,
            'employee_ratio' => $request->employee_ratio,
            'company_ratio' => $request->company_ratio,
            'account_set_id' => $request->account_set_id,
            'created_by' => $user->id,
        ]);
        
        Log::info('公积金配置创建成功', [
            'config_id' => $config->id,
            'region_id' => $config->region_id,
            'config_name' => $config->config_name,
            'created_by' => $user->id
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '公积金配置创建成功',
            'data' => $config->load(['creator', 'region'])
        ], 201);
    }

    /**
     * 获取单个公积金配置
     */
    public function show($id)
    {
        $config = HousingFundConfig::with(['creator', 'region'])->find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => '公积金配置未找到'
            ], 404);
        }

        $pending = InsuranceLimitPendingChange::where('target_type', 'housing_fund_config')
            ->where('target_id', $config->id)
            ->where('status', 'pending')
            ->first();

        $config->current_limits = [
            'min_base_amount' => $config->min_base_amount,
            'max_base_amount' => $config->max_base_amount,
        ];
        $config->pending_limits = $pending ? [
            'id' => $pending->id,
            'min_base_amount' => $pending->pending_min_base_amount,
            'max_base_amount' => $pending->pending_max_base_amount,
            'effective_date' => optional($pending->effective_date)->toDateString(),
            'status' => $pending->status,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * 更新公积金配置
     */
    public function update(Request $request, $id)
    {
        // 公积金编辑权限
        if ($response = $this->checkPermission('housing_fund.edit')) {
            return $response;
        }

        $user = $request->user();
        
        $config = HousingFundConfig::find($id);
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => '公积金配置未找到'
            ], 404);
        }
        
        $request->validate([
            'region_id' => 'required|exists:housing_fund_regions,id',
            'config_name' => 'required|string|max:255',
            'min_base_amount' => 'required|numeric|min:0',
            'max_base_amount' => 'required|numeric|min:0',
            'employee_ratio' => 'required|numeric|min:0|max:1',
            'company_ratio' => 'required|numeric|min:0|max:1',
            'limit_effective_date' => 'nullable|date|after_or_equal:today'
        ]);
        
        $compensationResult = null;

        // 保存旧数据用于变更检测
        $oldData = $config->toArray();

        $importResult = null;
        $shouldDetect = false;

        $requestedMin = $request->has('min_base_amount') ? $request->input('min_base_amount') : $config->min_base_amount;
        $requestedMax = $request->has('max_base_amount') ? $request->input('max_base_amount') : $config->max_base_amount;
        $hasLimitInput = $request->has('min_base_amount') || $request->has('max_base_amount');
        $hasLimitValueDiff = (string) $requestedMin !== (string) $config->min_base_amount || (string) $requestedMax !== (string) $config->max_base_amount;
        $hasLimitChange = $hasLimitInput && ($hasLimitValueDiff || $request->filled('limit_effective_date'));

        if ($hasLimitChange) {
            if ($request->filled('limit_effective_date')) {
                Log::info('HousingFund pending change check', [
                    'config_id' => $config->id,
                    'hasLimitInput' => $hasLimitInput,
                    'hasLimitValueDiff' => $hasLimitValueDiff,
                    'hasEffectiveDate' => $request->filled('limit_effective_date'),
                    'requestedMin' => $requestedMin,
                    'requestedMax' => $requestedMax,
                    'dbMin' => $config->min_base_amount,
                    'dbMax' => $config->max_base_amount,
                    'effectiveDate' => $request->input('limit_effective_date'),
                ]);
                $pendingService = app(InsuranceLimitPendingService::class);
                $pending = $pendingService->savePendingChange(
                    'housing_fund_config',
                    $config->id,
                    $config->account_set_id,
                    $requestedMin,
                    $requestedMax,
                    $request->input('limit_effective_date'),
                    $user->id
                );

                Log::info('公积金上下限待生效变更已保存', [
                    'config_id' => $config->id,
                    'pending_id' => $pending->id,
                    'effective_date' => $pending->effective_date,
                ]);
            } else {
                $limitUpdateService = app(\App\Services\BaseLimitUpdateService::class);
                $updateResult = $limitUpdateService->updateLimits($config, $requestedMin, $requestedMax);

                if ($updateResult['should_compensate']) {
                    $compensationService = app(\App\Services\BaseLimitCompensationService::class);
                    $compensationResult = $compensationService->calculateHousingFundCompensation(
                        $config->id,
                        $updateResult['old_min'],
                        $updateResult['old_max'],
                        $updateResult['new_min'],
                        $updateResult['new_max']
                    );
                }
                $shouldDetect = true;
            }
        }

        $config->update([
            'region_id' => $request->region_id,
            'config_name' => $request->config_name,
            'employee_ratio' => $request->employee_ratio,
            'company_ratio' => $request->company_ratio,
        ]);
        $shouldDetect = true;

        if ($shouldDetect) {
            $detectionService = app(\App\Services\InsuranceChangeDetectionService::class);
            $importResult = $detectionService->detectAndImport('housing_fund', $oldData, $config->toArray(), $config->region_id);
        }
        
        $pending = InsuranceLimitPendingChange::where('target_type', 'housing_fund_config')
            ->where('target_id', $config->id)
            ->where('status', 'pending')
            ->first();

        $config->load(['creator', 'region']);
        $config->current_limits = [
            'min_base_amount' => $config->min_base_amount,
            'max_base_amount' => $config->max_base_amount,
        ];
        $config->pending_limits = $pending ? [
            'id' => $pending->id,
            'min_base_amount' => $pending->pending_min_base_amount,
            'max_base_amount' => $pending->pending_max_base_amount,
            'effective_date' => optional($pending->effective_date)->toDateString(),
            'status' => $pending->status,
        ] : null;

        Log::info('公积金配置更新成功', [
            'config_id' => $config->id,
            'region_id' => $config->region_id,
            'config_name' => $config->config_name,
            'updated_by' => $user->id,
            'import_result' => $importResult
        ]);

        return response()->json([
            'success' => true,
            'message' => '公积金配置更新成功',
            'data' => $config,
            'compensation_result' => $compensationResult,
            'import_result' => $importResult
        ]);
    }

    /**
     * 删除公积金配置
     */
    public function destroy($id)
    {
        // 公积金删除权限
        if ($response = $this->checkPermission('housing_fund.delete')) {
            return $response;
        }

        $config = HousingFundConfig::find($id);
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => '公积金配置未找到'
            ], 404);
        }
        
        $config->delete();
        
        Log::info('公积金配置删除成功', [
            'config_id' => $id,
            'region_id' => $config->region_id,
            'config_name' => $config->config_name
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '公积金配置删除成功'
        ]);
    }

}