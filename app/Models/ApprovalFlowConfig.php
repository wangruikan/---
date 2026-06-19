<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalFlowConfig extends Model
{
    public const MIN_APPROVAL_LEVEL = 1;
    public const MAX_APPROVAL_LEVEL = 10;
    public const APPROVER_MIN_LEVEL = 2;

    protected $fillable = [
        'account_set_id',
        'business_type',
        'enabled_levels',
    ];

    protected $casts = [
        'enabled_levels' => 'array',
    ];

    public static function businessTypeLabels(array $extraTypes = []): array
    {
        $types = [
            'employee_contract' => '员工合同审批',
            'offline_onboarding' => '线下入职审批',
            'employee_deletion' => '员工删除审批',
            'employee_salary_adjustment' => '员工调薪审批',
            'employee_registration_form_update' => '登记表修改审批',
            'insurance_enrollment' => '参保入职',
            'document_upload' => '资料收集',
            'document_delivery' => '资料交付',
            'probation_period' => '试用期提醒',
            'tax_declaration' => '税费申报',
            '工资表审批' => '工资表审批',
            '考勤申请' => '考勤申请',
            '发票申请' => '发票申请',
            '付款申请' => '付款申请',
            '工资付款申请' => '工资付款申请',
            '报销付款申请' => '报销付款申请',
            '保险汇总付款申请' => '保险汇总付款申请',
            '社保付款发票审批' => '社保付款发票审批',
            '公积金付款发票审批' => '公积金付款发票审批',
            '保险汇总' => '保险汇总审批',
            '文件盖章' => '文件盖章',
            '报销申请' => '报销申请',
            'reimbursement' => '报销申请（兼容）',
            'material_request' => '资料申请',
            'travel_application' => '差旅申请',
            '差旅申请' => '差旅申请（兼容）',
            'personnel_change' => '人员汇总申请',
        ];

        foreach ($extraTypes as $type) {
            $type = trim((string) $type);
            if ($type === '发票申请（重新提交）') {
                continue;
            }
            if ($type !== '' && !isset($types[$type])) {
                $types[$type] = $type;
            }
        }

        return $types;
    }

    public static function businessTypes(array $extraTypes = []): array
    {
        return collect(self::businessTypeLabels($extraTypes))
            ->map(fn($label, $type) => [
                'business_type' => $type,
                'business_label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function formatLevelName(?int $level, ?string $levelName = null): ?string
    {
        if ($level === null) {
            return $levelName;
        }

        if ($level < self::MIN_APPROVAL_LEVEL || $level > self::MAX_APPROVAL_LEVEL) {
            return $levelName;
        }

        return '第' . $level . '级审批';
    }

    public static function getEnabledLevels(int $accountSetId, string $businessType): ?array
    {
        if (!Schema::hasTable('approval_flow_configs')) {
            return null;
        }

        $config = self::where('account_set_id', $accountSetId)
            ->where('business_type', $businessType)
            ->first();

        if (!$config) {
            return null;
        }

        return collect($config->enabled_levels ?? [])
            ->map(fn($level) => (int) $level)
            ->filter(fn($level) => $level >= self::MIN_APPROVAL_LEVEL && $level <= self::MAX_APPROVAL_LEVEL)
            ->unique()
            ->values()
            ->all();
    }

    public static function approvalLevelRange(int $minLevel = self::MIN_APPROVAL_LEVEL): array
    {
        $minLevel = max(self::MIN_APPROVAL_LEVEL, min(self::MAX_APPROVAL_LEVEL, $minLevel));

        return range($minLevel, self::MAX_APPROVAL_LEVEL);
    }

    public static function getEffectiveLevels(int $accountSetId, ?string $businessType = null, int $minLevel = self::MIN_APPROVAL_LEVEL): array
    {
        $minLevel = max(self::MIN_APPROVAL_LEVEL, min(self::MAX_APPROVAL_LEVEL, $minLevel));
        $enabledLevels = $businessType ? self::getEnabledLevels($accountSetId, $businessType) : null;

        if (is_array($enabledLevels)) {
            return collect($enabledLevels)
                ->filter(fn($level) => $level >= $minLevel && $level <= self::MAX_APPROVAL_LEVEL)
                ->sort()
                ->values()
                ->all();
        }

        return DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->whereNotNull('approval_level')
            ->whereBetween('approval_level', [$minLevel, self::MAX_APPROVAL_LEVEL])
            ->distinct()
            ->orderBy('approval_level')
            ->pluck('approval_level')
            ->map(fn($level) => (int) $level)
            ->values()
            ->all();
    }

    public static function getEnabledApprovers(
        int $accountSetId,
        ?string $businessType = null,
        int $minLevel = self::MIN_APPROVAL_LEVEL,
        ?int $excludeUserId = null
    ) {
        $levels = self::getEffectiveLevels($accountSetId, $businessType, $minLevel);
        if (empty($levels)) {
            return collect();
        }

        $query = DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->whereIn('account_set_users.approval_level', $levels)
            ->where('users.is_active', true);

        if ($excludeUserId) {
            $query->where('users.id', '!=', $excludeUserId);
        }

        return $query
            ->orderBy('account_set_users.approval_level')
            ->select(
                'users.id',
                'users.id as user_id',
                'users.name',
                'users.name as user_name',
                'users.nickname',
                'users.email',
                'account_set_users.approval_level',
                'account_set_users.approval_level_name as level_name'
            )
            ->get()
            ->map(function ($approver) {
                $approver->level_name = self::formatLevelName(
                    isset($approver->approval_level) ? (int) $approver->approval_level : null,
                    $approver->level_name ?? null
                );

                return $approver;
            });
    }

    public static function getFirstEffectiveLevel(
        int $accountSetId,
        ?string $businessType = null,
        int $minLevel = self::MIN_APPROVAL_LEVEL
    ): ?int {
        $approver = self::getFirstEffectiveApprover($accountSetId, $businessType, $minLevel);

        return $approver ? (int) $approver->approval_level : null;
    }

    public static function getFirstEffectiveApprovers(
        int $accountSetId,
        ?string $businessType = null,
        int $minLevel = self::MIN_APPROVAL_LEVEL,
        ?int $excludeUserId = null
    ) {
        $approvers = self::getEnabledApprovers($accountSetId, $businessType, $minLevel, $excludeUserId);
        $firstApprover = $approvers->first();

        if (!$firstApprover) {
            return collect();
        }

        $firstLevel = (int) $firstApprover->approval_level;

        return $approvers
            ->filter(fn($approver) => (int) $approver->approval_level === $firstLevel)
            ->values();
    }

    public static function getFirstEffectiveApprover(
        int $accountSetId,
        ?string $businessType = null,
        int $minLevel = self::MIN_APPROVAL_LEVEL,
        ?int $excludeUserId = null
    ) {
        return self::getFirstEffectiveApprovers($accountSetId, $businessType, $minLevel, $excludeUserId)
            ->first();
    }

    public static function userCanApproveBusiness(
        int $accountSetId,
        ?string $businessType,
        int $userId,
        int $minLevel = self::MIN_APPROVAL_LEVEL
    ): bool {
        $levels = self::getEffectiveLevels($accountSetId, $businessType, $minLevel);
        if (empty($levels)) {
            return false;
        }

        return DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('user_id', $userId)
            ->whereIn('approval_level', $levels)
            ->exists();
    }
}
