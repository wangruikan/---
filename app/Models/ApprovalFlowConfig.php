<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ApprovalFlowConfig extends Model
{
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
            '工资表审批' => '工资表审批',
            '考勤申请' => '考勤申请',
            '发票申请' => '发票申请',
            '发票申请（重新提交）' => '发票申请（重新提交）',
            '付款申请' => '付款申请',
            '工资付款申请' => '工资付款申请',
            '报销付款申请' => '报销付款申请',
            '保险汇总付款申请' => '保险汇总付款申请',
            '社保付款发票审批' => '社保付款发票审批',
            '公积金付款发票审批' => '公积金付款发票审批',
            '保险汇总' => '保险汇总审批',
            '报销申请' => '报销申请',
            'reimbursement' => '报销申请（兼容）',
            'material_request' => '资料申请',
            'travel_application' => '差旅申请',
            '差旅申请' => '差旅申请（兼容）',
            'personnel_change' => '人员汇总申请',
        ];

        foreach ($extraTypes as $type) {
            $type = trim((string) $type);
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
            ->filter(fn($level) => $level > 0)
            ->unique()
            ->values()
            ->all();
    }
}
