<?php

namespace App\Http\Controllers;

use App\Models\HousingFundRegion;
use App\Models\HousingFundConfig;
use App\Models\InsuranceLimitPendingChange;
use App\Models\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\ChecksPermission;

class HousingFundRegionController extends Controller
{
    use ChecksPermission;
    /**
     * 获取地区列表
     */
    public function index(Request $request)
    {
        // 公积金查看权限
        if ($response = $this->checkPermission('housing_fund.view')) {
            return $response;
        }

        $user = $request->user();
        $accountSetId = $request->input('account_set_id');
        
        $query = HousingFundRegion::with(['creator', 'configs', 'defaultConfig']);
        
        if ($accountSetId) {
            $query->where('account_set_id', $accountSetId);
        }
        
        $regions = $query->orderBy('region_name')
            ->get();
        
        // 为每个地区添加配置统计信息和模板状态
        $regions->each(function ($region) use ($accountSetId) {
            $region->config_count = $region->configs->count();
            $region->has_default = $region->defaultConfig ? true : false;
            
            // 添加 has_template 字段
            $region->has_template = \App\Models\ReportTemplate::where('region_id', $region->id)
                ->where('region_type', 'housing_fund')
                ->where('account_set_id', $accountSetId)
                ->exists();
        });
        
        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 创建地区
     */
    public function store(Request $request)
    {
        // 公积金新增权限
        if ($response = $this->checkPermission('housing_fund.create')) {
            return $response;
        }

        $user = $request->user();
        
        $request->validate([
            'region_name' => 'required|string|max:255',
            'account_set_id' => 'required|exists:account_sets,id',
        ]);

        // 检查地区是否已存在
        $existingRegion = HousingFundRegion::where('region_name', $request->region_name)
            ->where('account_set_id', $request->account_set_id)
            ->first();
            
        if ($existingRegion) {
            return response()->json([
                'success' => false,
                'message' => '该地区已存在'
            ], 400);
        }

        $region = HousingFundRegion::create([
            'region_name' => $request->region_name,
            'account_number' => $request->account_number,
            'company_name' => $request->company_name,
            'account_set_id' => $request->account_set_id,
            'created_by' => $user->id,
        ]);

        Log::info('公积金地区创建成功', [
            'region_id' => $region->id,
            'region_name' => $region->region_name,
            'created_by' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => '地区创建成功',
            'data' => $region->load('creator')
        ], 201);
    }

    /**
     * 获取单个地区
     */
    public function show($id)
    {
        $region = HousingFundRegion::with(['creator', 'configs.creator', 'defaultConfig'])
            ->find($id);

        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => '地区未找到'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $region
        ]);
    }

    /**
     * 更新地区
     */
    public function update(Request $request, $id)
    {
        // 公积金编辑权限
        if ($response = $this->checkPermission('housing_fund.edit')) {
            return $response;
        }

        $user = $request->user();
        
        $region = HousingFundRegion::find($id);

        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => '地区未找到'
            ], 404);
        }
        
        $request->validate([
            'region_name' => 'required|string|max:255',
        ]);

        // 检查新名称是否与其他地区冲突
        $existingRegion = HousingFundRegion::where('region_name', $request->region_name)
            ->where('account_set_id', $region->account_set_id)
            ->where('id', '!=', $id)
            ->first();
            
        if ($existingRegion) {
            return response()->json([
                'success' => false,
                'message' => '该地区名称已存在'
            ], 400);
        }

        $region->update($request->only(['region_name', 'account_number', 'company_name']));

        Log::info('公积金地区更新成功', [
            'region_id' => $region->id,
            'region_name' => $region->region_name,
            'updated_by' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => '地区更新成功',
            'data' => $region->load('creator')
        ]);
    }

    /**
     * 删除地区
     */
    public function destroy($id)
    {
        // 公积金删除权限
        if ($response = $this->checkPermission('housing_fund.delete')) {
            return $response;
        }

        $region = HousingFundRegion::find($id);

        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => '地区未找到'
            ], 404);
        }

        // 检查是否有关联的配置
        $configCount = $region->configs()->count();
        if ($configCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "该地区下有 {$configCount} 个配置，无法删除"
            ], 400);
        }

        $region->delete();

        Log::info('公积金地区删除成功', [
            'region_id' => $id,
            'region_name' => $region->region_name
        ]);

        return response()->json([
            'success' => true,
            'message' => '地区删除成功'
        ]);
    }

    /**
     * 获取地区的所有配置
     */
    public function getConfigs($regionId)
    {
        // 公积金查看权限（查看某地区配置也需要查看权限）
        if ($response = $this->checkPermission('housing_fund.view')) {
            return $response;
        }

        $region = HousingFundRegion::find($regionId);

        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => '地区未找到'
            ], 404);
        }

        $configs = $region->configs()->with('creator')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $pendingMap = InsuranceLimitPendingChange::where('target_type', 'housing_fund_config')
            ->where('status', 'pending')
            ->whereIn('target_id', $configs->pluck('id')->all())
            ->get()
            ->keyBy('target_id');

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
     * 获取地区下配置的上下限历史
     */
    public function getRegionLimitHistories(Request $request, $id)
    {
        if ($response = $this->checkPermission('housing_fund.view')) {
            return $response;
        }

        $region = HousingFundRegion::find($id);
        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => '地区未找到'
            ], 404);
        }

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

        $configIds = HousingFundConfig::where('region_id', $region->id)->pluck('id');

        $histories = OperationLog::where('model_type', HousingFundConfig::class)
            ->whereIn('model_id', $configIds)
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
