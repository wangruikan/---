<?php

namespace App\Http\Controllers;

use App\Models\ApprovalFlowConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ApprovalFlowConfigController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$this->canView($user)) {
            return response()->json([
                'success' => false,
                'message' => '没有权限执行此操作',
            ], 403);
        }

        $accountSetId = $this->resolveAccountSetId($request);
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套',
            ], 400);
        }

        if (!$this->canAccessAccountSet($user, $accountSetId)) {
            return response()->json([
                'success' => false,
                'message' => '无权访问该账套',
            ], 403);
        }

        $approvalLevels = $this->getApprovalLevels($accountSetId);
        $allLevelValues = collect($approvalLevels)->pluck('level')->all();
        $extraTypes = $this->getExistingBusinessTypes($accountSetId);
        $configs = ApprovalFlowConfig::where('account_set_id', $accountSetId)
            ->get()
            ->keyBy('business_type');

        $rows = collect(ApprovalFlowConfig::businessTypes($extraTypes))
            ->map(function ($business) use ($configs, $allLevelValues) {
                $config = $configs->get($business['business_type']);
                $enabledLevels = $config
                    ? collect($config->enabled_levels ?? [])->map(fn($level) => (int) $level)->values()->all()
                    : $allLevelValues;

                return [
                    'business_type' => $business['business_type'],
                    'business_label' => $business['business_label'],
                    'enabled_levels' => $enabledLevels,
                    'is_default' => !$config,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'account_set_id' => $accountSetId,
                'approval_levels' => $approvalLevels,
                'configs' => $rows,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$this->canUpdate($user)) {
            return response()->json([
                'success' => false,
                'message' => '没有权限执行此操作',
            ], 403);
        }

        $accountSetId = $this->resolveAccountSetId($request);
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请先选择账套',
            ], 400);
        }

        if (!$this->canAccessAccountSet($user, $accountSetId)) {
            return response()->json([
                'success' => false,
                'message' => '无权访问该账套',
            ], 403);
        }

        $allowedTypes = array_keys(ApprovalFlowConfig::businessTypeLabels($this->getExistingBusinessTypes($accountSetId)));
        $validator = Validator::make($request->all(), [
            'business_type' => 'required|string',
            'enabled_levels' => 'required|array|min:1',
            'enabled_levels.*' => 'integer',
        ], [
            'business_type.required' => '业务类型不能为空',
            'enabled_levels.required' => '请至少启用一个审批节点',
            'enabled_levels.array' => '审批节点格式错误',
            'enabled_levels.min' => '请至少启用一个审批节点',
            'enabled_levels.*.integer' => '审批节点格式错误',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessType = $request->input('business_type');
        if (!in_array($businessType, $allowedTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => '业务类型不支持配置',
            ], 422);
        }

        $availableLevels = collect($this->getApprovalLevels($accountSetId))->pluck('level')->all();
        if (empty($availableLevels)) {
            return response()->json([
                'success' => false,
                'message' => '该账套还没有配置审批节点',
            ], 422);
        }

        $enabledLevels = collect($request->input('enabled_levels', []))
            ->map(fn($level) => (int) $level)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $invalidLevels = array_values(array_diff($enabledLevels, $availableLevels));
        if (!empty($invalidLevels)) {
            return response()->json([
                'success' => false,
                'message' => '包含当前账套不存在的审批节点',
            ], 422);
        }

        $config = ApprovalFlowConfig::updateOrCreate(
            [
                'account_set_id' => $accountSetId,
                'business_type' => $businessType,
            ],
            [
                'enabled_levels' => $enabledLevels,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '审批流程配置保存成功',
            'data' => [
                'business_type' => $config->business_type,
                'enabled_levels' => $config->enabled_levels,
            ],
        ]);
    }

    private function resolveAccountSetId(Request $request): ?int
    {
        $value = $request->input('current_account_set_id')
            ?: $request->input('account_set_id')
            ?: $request->header('X-Account-Set-Id');

        return $value ? (int) $value : null;
    }

    private function getApprovalLevels(int $accountSetId): array
    {
        return DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->whereNotNull('account_set_users.approval_level')
            ->orderBy('account_set_users.approval_level')
            ->select(
                'account_set_users.approval_level',
                'account_set_users.approval_level_name',
                'users.name as user_name'
            )
            ->get()
            ->groupBy('approval_level')
            ->map(function ($items, $level) {
                $first = $items->first();
                return [
                    'level' => (int) $level,
                    'level_name' => ApprovalFlowConfig::formatLevelName((int) $level, $first->approval_level_name),
                    'approver_names' => $items->pluck('user_name')->filter()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function getExistingBusinessTypes(int $accountSetId): array
    {
        if (!Schema::hasTable('approval_instances')) {
            return [];
        }

        return DB::table('approval_instances')
            ->where('account_set_id', $accountSetId)
            ->whereNotNull('business_type')
            ->distinct()
            ->pluck('business_type')
            ->map(fn($type) => (string) $type)
            ->reject(fn($type) => in_array($type, ['发票申请（重新提交）', 'reimbursement'], true))
            ->all();
    }

    private function canView($user): bool
    {
        return in_array($user->role, ['admin', 'super_admin'], true)
            || $user->hasPermission('approval_flow_configs.view');
    }

    private function canUpdate($user): bool
    {
        return in_array($user->role, ['admin', 'super_admin'], true)
            || $user->hasPermission('approval_flow_configs.update');
    }

    private function canAccessAccountSet($user, int $accountSetId): bool
    {
        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            return true;
        }

        return DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('user_id', $user->id)
            ->exists();
    }
}
