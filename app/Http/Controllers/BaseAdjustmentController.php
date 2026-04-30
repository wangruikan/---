<?php

namespace App\Http\Controllers;

use App\Models\AccountSet;
use App\Models\BaseAdjustment;
use App\Models\Employee;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BaseAdjustmentController extends Controller
{
    use ChecksPermission;

    private function canAdjustBase(Request $request): array
    {
        $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
        if (!$accountSetId) {
            return [
                'allowed' => false,
                'message' => '请先选择账套',
            ];
        }

        $accountSet = AccountSet::find($accountSetId);
        if (!$accountSet) {
            Log::error('账套不存在', ['account_set_id' => $accountSetId]);

            return [
                'allowed' => false,
                'message' => '账套不存在',
            ];
        }

        $adjustmentMonths = [];
        if ($accountSet->base_adjustment_months) {
            try {
                $adjustmentMonths = is_array($accountSet->base_adjustment_months)
                    ? $accountSet->base_adjustment_months
                    : json_decode($accountSet->base_adjustment_months, true);
            } catch (\Throwable $exception) {
                Log::error('解析基数调整月份配置失败', [
                    'account_set_id' => $accountSetId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if (empty($adjustmentMonths)) {
            return [
                'allowed' => false,
                'message' => '该账套未配置基数调整月份，请先在账套设置中配置',
            ];
        }

        $currentMonth = (int) date('n');
        if (!in_array($currentMonth, $adjustmentMonths, true)) {
            return [
                'allowed' => false,
                'message' => '当前月份不允许调基，允许调整月份为：' . implode('、', $adjustmentMonths) . '月',
            ];
        }

        return [
            'allowed' => true,
            'message' => '当前月份允许调基',
        ];
    }

    public function index(Request $request)
    {
        if ($response = $this->checkPermission('base_adjustment.view')) {
            return $response;
        }

        $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套',
            ], 422);
        }

        $query = Employee::with(['projects', 'largeMedicalInsuranceConfigRelation'])
            ->where('account_set_id', $accountSetId)
            ->whereIn('contract_status', ['active', 'approved']);

        if ($request->filled('employee_name')) {
            $query->where('name', 'like', '%' . $request->employee_name . '%');
        }

        if ($request->filled('project_id')) {
            $projectId = $request->input('project_id');
            $query->whereHas('projects', function ($projectQuery) use ($projectId) {
                $projectQuery->where('projects.id', $projectId);
            });
        }

        $employees = $query->orderBy('created_at', 'desc')->get();
        $employeeIds = $employees->pluck('id')->all();

        $adjustmentsByEmployee = BaseAdjustment::with(['creator'])
            ->whereIn('employee_id', $employeeIds ?: [0])
            ->whereIn('status', ['pending', 'applied'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('employee_id');

        $data = $employees->map(function (Employee $employee) use ($adjustmentsByEmployee) {
            $project = $employee->projects->first();
            $typedAdjustments = $this->mapAdjustmentsByType($adjustmentsByEmployee->get($employee->id, collect()));

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'id_number' => $employee->id_number,
                'project_id' => $project ? $project->id : null,
                'project_name' => $project ? $project->name : '',
                'current_social_security_base' => $employee->social_security_base ?? 0,
                'current_medical_insurance_base' => $employee->medical_insurance_base ?? 0,
                'current_housing_fund_base' => $employee->housing_fund_base ?? 0,
                'current_large_medical_base' => $employee->large_medical_base,
                'current_large_medical_company_base' => $employee->large_medical_company_base,
                'large_medical_base_source' => optional($employee->largeMedicalInsuranceConfigRelation)->base_source ?? 'employee',
                'adjustments' => $typedAdjustments,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->checkPermission('base_adjustment.create')) {
            return $response;
        }

        $canAdjust = $this->canAdjustBase($request);
        if (!$canAdjust['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $canAdjust['message'],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'adjustment_id' => 'nullable|exists:base_adjustments,id',
            'employee_id' => 'required|exists:employees,id',
            'account_set_id' => 'required|exists:account_sets,id',
            'adjustment_type' => 'required|in:' . implode(',', BaseAdjustment::getSupportedTypes()),
            'effective_date' => 'required|date|after_or_equal:today',
            'new_base' => 'nullable|numeric|min:0',
            'new_company_base' => 'nullable|numeric|min:0',
            'adjustment_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = $request->input('adjustment_type');
        $newBase = $request->input('new_base');
        $newCompanyBase = $request->input('new_company_base');

        if (in_array($type, [
            BaseAdjustment::TYPE_SOCIAL_SECURITY,
            BaseAdjustment::TYPE_MEDICAL_INSURANCE,
            BaseAdjustment::TYPE_HOUSING_FUND,
        ], true) && !$this->hasValue($newBase)) {
            return response()->json([
                'success' => false,
                'message' => '请填写调整后的基数',
            ], 422);
        }

        if ($type === BaseAdjustment::TYPE_LARGE_MEDICAL && !$this->hasValue($newBase) && !$this->hasValue($newCompanyBase)) {
            return response()->json([
                'success' => false,
                'message' => '请至少填写一个大额医疗调整值',
            ], 422);
        }

        $employee = Employee::with(['projects', 'socialSecurityRegion', 'medicalInsuranceRegion', 'housingFundConfig', 'largeMedicalInsuranceConfigRelation'])
            ->find($request->employee_id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '员工不存在',
            ], 404);
        }

        if (!in_array($employee->contract_status, ['active', 'approved'], true)) {
            return response()->json([
                'success' => false,
                'message' => '仅允许在职员工调基',
            ], 403);
        }

        if ($type === BaseAdjustment::TYPE_LARGE_MEDICAL && optional($employee->largeMedicalInsuranceConfigRelation)->base_source === 'config') {
            return response()->json([
                'success' => false,
                'message' => '该员工所属特殊地区，大额医疗请前往大额医疗保险管理中调整',
            ], 422);
        }

        if ($this->isSameAsCurrentValue($employee, $type, $newBase, $newCompanyBase)) {
            return response()->json([
                'success' => false,
                'message' => '调整后的基数不能与当前基数一致',
            ], 422);
        }

        try {
            $adjustment = DB::transaction(function () use ($request, $employee, $type) {
                $existingAdjustment = $this->findPendingAdjustmentByType(
                    $employee->id,
                    $request->account_set_id,
                    $type,
                    $request->input('adjustment_id')
                );

                if ($existingAdjustment && $existingAdjustment->isMixedRecord()) {
                    $existingAdjustment = $existingAdjustment->extractTypeToStandaloneRecord($type, [
                        'status' => 'pending',
                        'applied_at' => null,
                    ]);
                }

                $project = $employee->projects->first();
                $payload = array_merge(
                    BaseAdjustment::emptyTypePayload(),
                    [
                        'employee_id' => $employee->id,
                        'project_id' => $project ? $project->id : null,
                        'account_set_id' => $request->account_set_id,
                        'status' => 'pending',
                        'applied_at' => null,
                        'adjustment_reason' => $request->input('adjustment_reason'),
                    ],
                    $this->buildTypePayload($employee, $request, $type)
                );

                if ($existingAdjustment) {
                    $existingAdjustment->update($payload);

                    return $existingAdjustment->fresh(['employee', 'creator']);
                }

                $payload['created_by'] = auth('sanctum')->id();

                return BaseAdjustment::create($payload)->load(['employee', 'creator']);
            });

            return response()->json([
                'success' => true,
                'message' => '基数调整已保存',
                'data' => $adjustment,
            ]);
        } catch (\Throwable $exception) {
            Log::error('保存基数调整失败', [
                'employee_id' => $request->employee_id,
                'account_set_id' => $request->account_set_id,
                'adjustment_type' => $type,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '保存失败：' . $exception->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $adjustment = BaseAdjustment::find($id);
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在',
            ], 404);
        }

        if ($adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '仅待生效记录允许删除',
            ], 403);
        }

        $type = $request->input('adjustment_type');
        if ($type) {
            if (!$adjustment->hasType($type)) {
                return response()->json([
                    'success' => false,
                    'message' => '当前记录不包含该险种',
                ], 422);
            }

            try {
                $adjustment->removeType($type);

                return response()->json([
                    'success' => true,
                    'message' => '删除成功',
                ]);
            } catch (\Throwable $exception) {
                return response()->json([
                    'success' => false,
                    'message' => '删除失败：' . $exception->getMessage(),
                ], 500);
            }
        }

        try {
            $adjustment->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功',
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $exception->getMessage(),
            ], 500);
        }
    }

    public function applyNow(Request $request, $id)
    {
        if ($response = $this->checkPermission('base_adjustment.update')) {
            return $response;
        }

        $adjustment = BaseAdjustment::find($id);
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在',
            ], 404);
        }

        if ($adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '仅待生效记录允许立即生效',
            ], 403);
        }

        $type = $request->input('adjustment_type');
        if ($type && !$adjustment->hasType($type)) {
            return response()->json([
                'success' => false,
                'message' => '当前记录不包含该险种',
            ], 422);
        }

        try {
            $result = $adjustment->apply($type, true);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => '立即生效失败',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => '基数调整已立即生效',
            ]);
        } catch (\Throwable $exception) {
            Log::error('立即生效失败', [
                'adjustment_id' => $id,
                'adjustment_type' => $type,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '立即生效失败：' . $exception->getMessage(),
            ], 500);
        }
    }

    public function applyDue(Request $request)
    {
        try {
            $adjustments = BaseAdjustment::pending()
                ->effective()
                ->whereNull('applied_at')
                ->orderBy('id')
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
                } catch (\Throwable $exception) {
                    $failCount++;
                    Log::error('批量应用基数调整失败', [
                        'adjustment_id' => $adjustment->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => '批量应用完成',
                'data' => [
                    'total' => count($adjustments),
                    'success' => $successCount,
                    'fail' => $failCount,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('批量应用基数调整失败', ['error' => $exception->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '批量应用失败：' . $exception->getMessage(),
            ], 500);
        }
    }

    public function getAdjustStatus(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->canAdjustBase($request),
        ]);
    }

    public function history(Request $request, $employeeId)
    {
        if ($response = $this->checkPermission('base_adjustment.view')) {
            return $response;
        }

        $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套',
            ], 422);
        }

        $records = BaseAdjustment::with(['creator'])
            ->where('employee_id', $employeeId)
            ->where('account_set_id', $accountSetId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $historyItems = [];
        foreach ($records as $record) {
            foreach ($record->getPresentTypes() as $type) {
                $historyItems[] = $this->formatTypeItem($record, $type);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $historyItems,
        ]);
    }

    private function mapAdjustmentsByType($adjustments): array
    {
        $mapped = [];
        foreach (BaseAdjustment::getSupportedTypes() as $type) {
            $mapped[$type] = null;
        }

        foreach ($adjustments as $adjustment) {
            foreach ($adjustment->getPresentTypes() as $type) {
                if (!$mapped[$type]) {
                    $mapped[$type] = $this->formatTypeItem($adjustment, $type);
                }
            }
        }

        return $mapped;
    }

    private function formatTypeItem(BaseAdjustment $adjustment, string $type): array
    {
        return array_merge($adjustment->toTypeItem($type), [
            'creator_name' => optional($adjustment->creator)->name,
        ]);
    }

    private function findPendingAdjustmentByType(int $employeeId, int $accountSetId, string $type, $adjustmentId = null): ?BaseAdjustment
    {
        if ($adjustmentId) {
            $record = BaseAdjustment::pending()
                ->where('id', $adjustmentId)
                ->where('employee_id', $employeeId)
                ->where('account_set_id', $accountSetId)
                ->first();

            if ($record && $record->hasType($type)) {
                return $record;
            }
        }

        return BaseAdjustment::pending()
            ->where('employee_id', $employeeId)
            ->where('account_set_id', $accountSetId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->first(function (BaseAdjustment $record) use ($type) {
                return $record->hasType($type);
            });
    }

    private function buildTypePayload(Employee $employee, Request $request, string $type): array
    {
        $effectiveDate = $request->input('effective_date');
        $payload = ['effective_date' => $effectiveDate];

        switch ($type) {
            case BaseAdjustment::TYPE_SOCIAL_SECURITY:
                $payload['old_social_security_base'] = $employee->social_security_base;
                $payload['new_social_security_base'] = $request->input('new_base');
                $payload['social_security_effective_date'] = $effectiveDate;
                $payload['social_security_min_base'] = optional($employee->socialSecurityRegion)->min_base_amount;
                $payload['social_security_max_base'] = optional($employee->socialSecurityRegion)->max_base_amount;
                break;

            case BaseAdjustment::TYPE_MEDICAL_INSURANCE:
                $payload['old_medical_insurance_base'] = $employee->medical_insurance_base;
                $payload['new_medical_insurance_base'] = $request->input('new_base');
                $payload['medical_insurance_effective_date'] = $effectiveDate;
                $payload['medical_insurance_min_base'] = optional($employee->medicalInsuranceRegion)->min_base_amount;
                $payload['medical_insurance_max_base'] = optional($employee->medicalInsuranceRegion)->max_base_amount;
                break;

            case BaseAdjustment::TYPE_HOUSING_FUND:
                $payload['old_housing_fund_base'] = $employee->housing_fund_base;
                $payload['new_housing_fund_base'] = $request->input('new_base');
                $payload['housing_fund_effective_date'] = $effectiveDate;
                $payload['housing_fund_min_base'] = optional($employee->housingFundConfig)->min_base_amount;
                $payload['housing_fund_max_base'] = optional($employee->housingFundConfig)->max_base_amount;
                break;

            case BaseAdjustment::TYPE_LARGE_MEDICAL:
                $payload['old_large_medical_base'] = $employee->large_medical_base;
                $payload['new_large_medical_base'] = $this->hasValue($request->input('new_base')) ? $request->input('new_base') : null;
                $payload['old_large_medical_company_base'] = $employee->large_medical_company_base;
                $payload['new_large_medical_company_base'] = $this->hasValue($request->input('new_company_base')) ? $request->input('new_company_base') : null;
                $payload['large_medical_effective_date'] = $effectiveDate;
                break;
        }

        return $payload;
    }

    private function isSameAsCurrentValue(Employee $employee, string $type, $newBase, $newCompanyBase): bool
    {
        switch ($type) {
            case BaseAdjustment::TYPE_SOCIAL_SECURITY:
                return $this->hasValue($newBase) && (float) $newBase === (float) ($employee->social_security_base ?? 0);

            case BaseAdjustment::TYPE_MEDICAL_INSURANCE:
                return $this->hasValue($newBase) && (float) $newBase === (float) ($employee->medical_insurance_base ?? 0);

            case BaseAdjustment::TYPE_HOUSING_FUND:
                return $this->hasValue($newBase) && (float) $newBase === (float) ($employee->housing_fund_base ?? 0);

            case BaseAdjustment::TYPE_LARGE_MEDICAL:
                $samePersonal = !$this->hasValue($newBase)
                    || ($this->hasValue($employee->large_medical_base) && (float) $newBase === (float) $employee->large_medical_base);
                $sameCompany = !$this->hasValue($newCompanyBase)
                    || ($this->hasValue($employee->large_medical_company_base) && (float) $newCompanyBase === (float) $employee->large_medical_company_base);

                return $samePersonal && $sameCompany;
        }

        return false;
    }

    private function hasValue($value): bool
    {
        return !is_null($value) && $value !== '';
    }
}
