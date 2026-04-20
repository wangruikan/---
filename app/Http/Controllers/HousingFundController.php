<?php

namespace App\Http\Controllers;

use App\Models\HousingFund;
use App\Services\InsuranceChangeDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

class HousingFundController extends Controller
{
    use ChecksPermission;
    /**
     * 获取公积金列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('housing_fund.view')) {
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

        $housingFunds = HousingFund::where('account_set_id', $accountSetId)
            ->with(['creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $housingFunds
        ]);
    }

    /**
     * 创建公积金
     */
    public function store(Request $request)
    {
        if ($response = $this->checkPermission('housing_fund.create')) {
            return $response;
        }
        
        $validator = Validator::make($request->all(), [
            'region_name' => 'required|string|max:100',
            'base_amount' => 'required|numeric|min:0',
            'employee_ratio' => 'required|numeric|min:0|max:1',
            'company_ratio' => 'required|numeric|min:0|max:1',
            'account_set_id' => 'required|exists:account_sets,id',
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
        $exists = HousingFund::where('account_set_id', $request->account_set_id)
            ->where('region_name', $request->region_name)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '该地区的公积金配置已存在'
            ], 422);
        }

        $housingFund = HousingFund::create([
            'region_name' => $request->region_name,
            'base_amount' => $request->base_amount,
            'employee_ratio' => $request->employee_ratio,
            'company_ratio' => $request->company_ratio,
            'account_set_id' => $request->account_set_id,
            'created_by' => $user->id,
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('housing_fund', [], $housingFund->toArray(), $housingFund->id);

        $housingFund->load(['creator']);

        return response()->json([
            'success' => true,
            'message' => '公积金配置创建成功',
            'data' => $housingFund,
            'import_result' => $importResult
        ]);
    }

    /**
     * 更新公积金
     */
    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('housing_fund.edit')) {
            return $response;
        }
        
        $validator = Validator::make($request->all(), [
            'region_name' => 'required|string|max:100',
            'base_amount' => 'required|numeric|min:0',
            'employee_ratio' => 'required|numeric|min:0|max:1',
            'company_ratio' => 'required|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $housingFund = HousingFund::findOrFail($id);
        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $housingFund->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        // 检查地区名称是否重复
        $exists = HousingFund::where('account_set_id', $housingFund->account_set_id)
            ->where('region_name', $request->region_name)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '该地区的公积金配置已存在'
            ], 422);
        }

        // 保存旧数据用于变更检测
        $oldData = $housingFund->toArray();

        $housingFund->update([
            'region_name' => $request->region_name,
            'base_amount' => $request->base_amount,
            'employee_ratio' => $request->employee_ratio,
            'company_ratio' => $request->company_ratio,
        ]);

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('housing_fund', $oldData, $housingFund->toArray(), $housingFund->id);

        $housingFund->load(['creator']);

        return response()->json([
            'success' => true,
            'message' => '公积金配置更新成功',
            'data' => $housingFund,
            'import_result' => $importResult
        ]);
    }

    /**
     * 删除公积金
     */
    public function destroy(Request $request, $id)
    {
        if ($response = $this->checkPermission('housing_fund.delete')) {
            return $response;
        }
        
        $housingFund = HousingFund::findOrFail($id);
        $user = $request->user();

        // 检查账套权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $housingFund->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        // 保存删除前的数据用于变更检测
        $oldData = $housingFund->toArray();
        $regionId = $housingFund->id;
        
        $housingFund->delete();

        // 检测保险信息变更并自动导入到增减模块
        $detectionService = app(InsuranceChangeDetectionService::class);
        $importResult = $detectionService->detectAndImport('housing_fund', $oldData, [], $regionId);

        return response()->json([
            'success' => true,
            'message' => '公积金配置删除成功',
            'import_result' => $importResult
        ]);
    }
}
