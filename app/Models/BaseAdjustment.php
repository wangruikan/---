<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BaseAdjustment extends Model
{
    use HasFactory, Auditable;

    public const TYPE_SOCIAL_SECURITY = 'social_security';
    public const TYPE_MEDICAL_INSURANCE = 'medical_insurance';
    public const TYPE_HOUSING_FUND = 'housing_fund';
    public const TYPE_LARGE_MEDICAL = 'large_medical';

    protected $auditName = '基数调差';

    protected $auditableFields = [
        'old_social_security_base' => '原社保基数',
        'old_medical_insurance_base' => '原医保基数',
        'old_housing_fund_base' => '原公积金基数',
        'old_large_medical_base' => '原大额医疗基数',
        'new_social_security_base' => '新社保基数',
        'new_medical_insurance_base' => '新医保基数',
        'new_housing_fund_base' => '新公积金基数',
        'new_large_medical_base' => '新大额医疗基数',
        'status' => '状态',
        'adjustment_reason' => '调整原因',
    ];

    protected $fillable = [
        'employee_id',
        'project_id',
        'account_set_id',
        'old_social_security_base',
        'old_medical_insurance_base',
        'old_housing_fund_base',
        'old_large_medical_base',
        'old_large_medical_company_base',
        'new_social_security_base',
        'new_medical_insurance_base',
        'new_housing_fund_base',
        'new_large_medical_base',
        'new_large_medical_company_base',
        'effective_date',
        'social_security_effective_date',
        'medical_insurance_effective_date',
        'housing_fund_effective_date',
        'large_medical_effective_date',
        'social_security_min_base',
        'social_security_max_base',
        'medical_insurance_min_base',
        'medical_insurance_max_base',
        'housing_fund_min_base',
        'housing_fund_max_base',
        'status',
        'applied_at',
        'adjustment_reason',
        'reason',
        'remark',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'old_social_security_base' => 'float',
        'old_medical_insurance_base' => 'float',
        'old_housing_fund_base' => 'float',
        'old_large_medical_base' => 'float',
        'old_large_medical_company_base' => 'float',
        'new_social_security_base' => 'float',
        'new_medical_insurance_base' => 'float',
        'new_housing_fund_base' => 'float',
        'new_large_medical_base' => 'float',
        'new_large_medical_company_base' => 'float',
        'social_security_min_base' => 'float',
        'social_security_max_base' => 'float',
        'medical_insurance_min_base' => 'float',
        'medical_insurance_max_base' => 'float',
        'housing_fund_min_base' => 'float',
        'housing_fund_max_base' => 'float',
        'effective_date' => 'date',
        'social_security_effective_date' => 'date',
        'medical_insurance_effective_date' => 'date',
        'housing_fund_effective_date' => 'date',
        'large_medical_effective_date' => 'date',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public static function getSupportedTypes(): array
    {
        return [
            self::TYPE_SOCIAL_SECURITY,
            self::TYPE_MEDICAL_INSURANCE,
            self::TYPE_HOUSING_FUND,
            self::TYPE_LARGE_MEDICAL,
        ];
    }

    public static function getTypeLabel(string $type): string
    {
        return static::getTypeConfig($type)['label'] ?? $type;
    }

    public static function getTypeConfig(string $type): array
    {
        $configs = [
            self::TYPE_SOCIAL_SECURITY => [
                'label' => '社保',
                'old_fields' => ['old_social_security_base'],
                'new_fields' => ['new_social_security_base'],
                'snapshot_fields' => ['social_security_min_base', 'social_security_max_base'],
                'date_field' => 'social_security_effective_date',
                'employee_map' => ['social_security_base' => 'new_social_security_base'],
                'personnel_map' => ['employee_social_security_base' => 'new_social_security_base'],
            ],
            self::TYPE_MEDICAL_INSURANCE => [
                'label' => '医保',
                'old_fields' => ['old_medical_insurance_base'],
                'new_fields' => ['new_medical_insurance_base'],
                'snapshot_fields' => ['medical_insurance_min_base', 'medical_insurance_max_base'],
                'date_field' => 'medical_insurance_effective_date',
                'employee_map' => ['medical_insurance_base' => 'new_medical_insurance_base'],
                'personnel_map' => ['employee_medical_insurance_base' => 'new_medical_insurance_base'],
            ],
            self::TYPE_HOUSING_FUND => [
                'label' => '公积金',
                'old_fields' => ['old_housing_fund_base'],
                'new_fields' => ['new_housing_fund_base'],
                'snapshot_fields' => ['housing_fund_min_base', 'housing_fund_max_base'],
                'date_field' => 'housing_fund_effective_date',
                'employee_map' => ['housing_fund_base' => 'new_housing_fund_base'],
                'personnel_map' => ['employee_housing_fund_base' => 'new_housing_fund_base'],
            ],
            self::TYPE_LARGE_MEDICAL => [
                'label' => '大额医疗',
                'old_fields' => ['old_large_medical_base', 'old_large_medical_company_base'],
                'new_fields' => ['new_large_medical_base', 'new_large_medical_company_base'],
                'snapshot_fields' => [],
                'date_field' => 'large_medical_effective_date',
                'employee_map' => [
                    'large_medical_base' => 'new_large_medical_base',
                    'large_medical_company_base' => 'new_large_medical_company_base',
                ],
                'personnel_map' => [
                    'employee_large_medical_base' => 'new_large_medical_base',
                    'employee_large_medical_company_base' => 'new_large_medical_company_base',
                ],
            ],
        ];

        return $configs[$type] ?? [];
    }

    public static function emptyTypePayload(): array
    {
        return [
            'old_social_security_base' => null,
            'old_medical_insurance_base' => null,
            'old_housing_fund_base' => null,
            'old_large_medical_base' => null,
            'old_large_medical_company_base' => null,
            'new_social_security_base' => null,
            'new_medical_insurance_base' => null,
            'new_housing_fund_base' => null,
            'new_large_medical_base' => null,
            'new_large_medical_company_base' => null,
            'effective_date' => null,
            'social_security_effective_date' => null,
            'medical_insurance_effective_date' => null,
            'housing_fund_effective_date' => null,
            'large_medical_effective_date' => null,
            'social_security_min_base' => null,
            'social_security_max_base' => null,
            'medical_insurance_min_base' => null,
            'medical_insurance_max_base' => null,
            'housing_fund_min_base' => null,
            'housing_fund_max_base' => null,
        ];
    }

    public function getAuditIdentifier()
    {
        return $this->employee ? $this->employee->name : "ID:{$this->id}";
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getPresentTypes(): array
    {
        $types = [];

        foreach (static::getSupportedTypes() as $type) {
            $config = static::getTypeConfig($type);
            $trackedFields = array_merge($config['old_fields'], $config['new_fields']);

            if ($this->hasAnyValue($trackedFields)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function hasType(string $type): bool
    {
        return in_array($type, $this->getPresentTypes(), true);
    }

    public function isMixedRecord(): bool
    {
        return count($this->getPresentTypes()) > 1;
    }

    public function getTypeStatus(string $type): ?string
    {
        if (!$this->hasType($type)) {
            return null;
        }

        return $this->status;
    }

    public function getTypeEffectiveDate(string $type): ?Carbon
    {
        $field = static::getTypeConfig($type)['date_field'] ?? null;

        if (!$field || !$this->{$field}) {
            return null;
        }

        return $this->{$field} instanceof Carbon
            ? $this->{$field}->copy()
            : Carbon::parse($this->{$field});
    }

    public function isTypeDue(string $type, bool $forceNow = false): bool
    {
        if (!$this->hasType($type)) {
            return false;
        }

        if ($forceNow) {
            return true;
        }

        $effectiveDate = $this->getTypeEffectiveDate($type);

        return $effectiveDate && $effectiveDate->startOfDay()->lte(now()->startOfDay());
    }

    public function getDueTypes(bool $forceNow = false): array
    {
        return array_values(array_filter(
            $this->getPresentTypes(),
            fn (string $type) => $this->isTypeDue($type, $forceNow)
        ));
    }

    public function isEffective(): bool
    {
        return !empty($this->getDueTypes());
    }

    public function toTypeItem(string $type): array
    {
        $effectiveDate = $this->getTypeEffectiveDate($type);

        return [
            'id' => $this->id,
            'adjustment_type' => $type,
            'adjustment_type_label' => static::getTypeLabel($type),
            'status' => $this->getTypeStatus($type),
            'old_base' => $this->getPrimaryBaseValue($type, 'old'),
            'new_base' => $this->getPrimaryBaseValue($type, 'new'),
            'old_company_base' => $this->getCompanyBaseValue($type, 'old'),
            'new_company_base' => $this->getCompanyBaseValue($type, 'new'),
            'effective_date' => $effectiveDate ? $effectiveDate->format('Y-m-d') : null,
            'adjustment_reason' => $this->adjustment_reason,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'applied_at' => $this->applied_at ? $this->applied_at->format('Y-m-d H:i:s') : null,
            'is_legacy_mixed' => $this->isMixedRecord(),
        ];
    }

    public function apply(?string $type = null, bool $forceNow = false): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $targetTypes = $type ? [$type] : $this->getDueTypes($forceNow);
        $targetTypes = array_values(array_filter($targetTypes, fn (string $item) => $this->hasType($item)));

        if (empty($targetTypes)) {
            return false;
        }

        return DB::transaction(function () use ($targetTypes, $forceNow) {
            $success = false;

            foreach ($targetTypes as $targetType) {
                $current = $this->fresh();
                if (!$current || $current->status !== 'pending' || !$current->hasType($targetType)) {
                    continue;
                }

                if ($current->isMixedRecord()) {
                    $standalone = $current->extractTypeToStandaloneRecord($targetType, [
                        'status' => 'pending',
                        'applied_at' => null,
                    ]);

                    if ($standalone && $standalone->applySingleType($targetType, $forceNow)) {
                        $success = true;
                    }

                    continue;
                }

                if ($current->applySingleType($targetType, $forceNow)) {
                    $success = true;
                }
            }

            return $success;
        });
    }

    public function extractTypeToStandaloneRecord(string $type, array $overrides = []): ?self
    {
        if (!$this->hasType($type)) {
            return null;
        }

        if (!$this->isMixedRecord()) {
            return $this;
        }

        $record = new static();
        $record->fill(array_merge(
            static::emptyTypePayload(),
            $this->buildCloneAttributes(),
            $this->getTypePersistenceAttributes($type),
            $overrides
        ));
        $record->created_at = $overrides['created_at'] ?? $this->created_at;
        $record->updated_at = $overrides['updated_at'] ?? now();
        $record->save();

        $this->removeType($type);

        return $record->fresh();
    }

    public function removeType(string $type): bool
    {
        if (!$this->hasType($type)) {
            return false;
        }

        $this->fill($this->getNullAttributesForType($type));
        $this->effective_date = $this->resolveOverallEffectiveDate();

        if (empty($this->getPresentTypes())) {
            return (bool) $this->delete();
        }

        $this->status = 'pending';
        $this->applied_at = null;

        return $this->save();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    public function scopeEffective($query)
    {
        $today = now()->toDateString();

        return $query->where(function ($innerQuery) use ($today) {
            $innerQuery->where(function ($dateQuery) use ($today) {
                $dateQuery->whereNotNull('social_security_effective_date')
                    ->where('social_security_effective_date', '<=', $today);
            })->orWhere(function ($dateQuery) use ($today) {
                $dateQuery->whereNotNull('medical_insurance_effective_date')
                    ->where('medical_insurance_effective_date', '<=', $today);
            })->orWhere(function ($dateQuery) use ($today) {
                $dateQuery->whereNotNull('housing_fund_effective_date')
                    ->where('housing_fund_effective_date', '<=', $today);
            })->orWhere(function ($dateQuery) use ($today) {
                $dateQuery->whereNotNull('large_medical_effective_date')
                    ->where('large_medical_effective_date', '<=', $today);
            });
        });
    }

    public function scopeOfAccountSet($query, $accountSetId)
    {
        return $query->where('account_set_id', $accountSetId);
    }

    public function scopeContainingType($query, string $type)
    {
        $config = static::getTypeConfig($type);
        $fields = array_merge($config['old_fields'] ?? [], $config['new_fields'] ?? []);

        return $query->where(function ($innerQuery) use ($fields) {
            foreach ($fields as $index => $field) {
                if ($index === 0) {
                    $innerQuery->whereNotNull($field);
                } else {
                    $innerQuery->orWhereNotNull($field);
                }
            }
        });
    }

    protected function applySingleType(string $type, bool $forceNow = false): bool
    {
        if ($this->status !== 'pending' || !$this->hasType($type) || !$this->isTypeDue($type, $forceNow)) {
            return false;
        }

        $employee = $this->employee;
        if (!$employee) {
            return false;
        }

        $config = static::getTypeConfig($type);
        $dateField = $config['date_field'];
        $today = now()->startOfDay();

        if ($forceNow && (!$this->{$dateField} || Carbon::parse($this->{$dateField})->startOfDay()->gt($today))) {
            $this->{$dateField} = $today->copy();
        }

        $employeeUpdateData = [];
        foreach ($config['employee_map'] as $targetField => $sourceField) {
            if (!is_null($this->{$sourceField}) && $this->{$sourceField} !== '') {
                $employeeUpdateData[$targetField] = $this->{$sourceField};
            }
        }

        if (empty($employeeUpdateData)) {
            return false;
        }

        $personnelUpdateData = [];
        foreach ($config['personnel_map'] as $targetField => $sourceField) {
            if (!is_null($this->{$sourceField}) && $this->{$sourceField} !== '') {
                $personnelUpdateData[$targetField] = $this->{$sourceField};
            }
        }

        $insuranceChangePayload = $this->buildInsuranceChangePayload($type);

        $employee->update($employeeUpdateData);

        if (!empty($personnelUpdateData)) {
            $targetPersonnel = $this->resolveTargetInsurancePersonnel($employee);
            if ($targetPersonnel) {
                $targetPersonnel->update($personnelUpdateData);
            }
        }

        $this->triggerInsuranceChangeTask($employee, $type, $insuranceChangePayload['old_data'], $insuranceChangePayload['new_data']);

        $this->effective_date = $this->{$dateField};
        $this->status = 'applied';
        $this->applied_at = now();

        return $this->save();
    }

    protected function buildInsuranceChangePayload(string $type): array
    {
        $config = static::getTypeConfig($type);
        $payloadKeys = [
            'social_security_base' => 'employee_social_security_base',
            'medical_insurance_base' => 'employee_medical_insurance_base',
            'housing_fund_base' => 'employee_housing_fund_base',
            'large_medical_base' => 'employee_large_medical_base',
            'large_medical_company_base' => 'employee_large_medical_company_base',
        ];
        $oldData = [];
        $newData = [];

        foreach ($config['employee_map'] as $targetField => $sourceField) {
            if (!array_key_exists($targetField, $payloadKeys)) {
                continue;
            }

            if (is_null($this->{$sourceField}) || $this->{$sourceField} === '') {
                continue;
            }

            $oldField = preg_replace('/^new_/', 'old_', $sourceField);
            $payloadKey = $payloadKeys[$targetField];
            $oldData[$payloadKey] = $this->{$oldField};
            $newData[$payloadKey] = $this->{$sourceField};
        }

        return [
            'old_data' => $oldData,
            'new_data' => $newData,
        ];
    }

    protected function triggerInsuranceChangeTask(Employee $employee, string $type, array $oldData, array $newData): void
    {
        if (empty($oldData) || empty($newData)) {
            return;
        }

        $changeTypeMap = [
            self::TYPE_SOCIAL_SECURITY => 'social_security',
            self::TYPE_MEDICAL_INSURANCE => 'medical_insurance',
            self::TYPE_HOUSING_FUND => 'housing_fund',
            self::TYPE_LARGE_MEDICAL => 'large_medical_insurance',
        ];

        $changeType = $changeTypeMap[$type] ?? null;
        if (!$changeType) {
            return;
        }

        try {
            $result = app(\App\Services\InsuranceChangeDetectionService::class)->triggerChange([
                'scope' => \App\Services\InsuranceChangeDetectionService::SCOPE_EMPLOYEE,
                'change_type' => $changeType,
                'employee' => $employee,
                'project_id' => $this->project_id,
                'old_data' => $oldData,
                'new_data' => $newData,
                'source' => 'base_adjustment_applied',
            ]);

            if (empty($result['success'])) {
                logger()->warning('基数调整已生效，但参保增减变更任务生成失败', [
                    'base_adjustment_id' => $this->id,
                    'employee_id' => $employee->id,
                    'type' => $type,
                    'result' => $result,
                ]);
            }
        } catch (\Throwable $exception) {
            logger()->warning('基数调整已生效，但参保增减变更任务触发异常', [
                'base_adjustment_id' => $this->id,
                'employee_id' => $employee->id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function buildCloneAttributes(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'project_id' => $this->project_id,
            'account_set_id' => $this->account_set_id,
            'status' => $this->status,
            'applied_at' => $this->applied_at,
            'adjustment_reason' => $this->adjustment_reason,
            'reason' => $this->reason,
            'remark' => $this->remark,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'created_by' => $this->created_by,
        ];
    }

    protected function resolveTargetInsurancePersonnel(Employee $employee): ?InsurancePersonnel
    {
        $baseQuery = InsurancePersonnel::query()
            ->where('employee_id', $employee->id)
            ->where('account_set_id', $this->account_set_id ?? $employee->account_set_id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('is_compensation')
                    ->orWhere('is_compensation', 0);
            });

        if (!empty($this->project_id)) {
            $byProject = (clone $baseQuery)
                ->where('project_id', $this->project_id)
                ->orderByDesc('id')
                ->first();

            if ($byProject) {
                return $byProject;
            }
        }

        return $baseQuery->orderByDesc('id')->first();
    }

    protected function getTypePersistenceAttributes(string $type): array
    {
        $attributes = [];
        $config = static::getTypeConfig($type);
        $trackedFields = array_merge($config['old_fields'], $config['new_fields'], $config['snapshot_fields']);

        foreach ($trackedFields as $field) {
            $attributes[$field] = $this->{$field};
        }

        $dateField = $config['date_field'];
        $attributes[$dateField] = $this->{$dateField};
        $attributes['effective_date'] = $this->{$dateField};

        return $attributes;
    }

    protected function getNullAttributesForType(string $type): array
    {
        $nullAttributes = [];
        $config = static::getTypeConfig($type);
        $trackedFields = array_merge($config['old_fields'], $config['new_fields'], $config['snapshot_fields'], [$config['date_field']]);

        foreach ($trackedFields as $field) {
            $nullAttributes[$field] = null;
        }

        return $nullAttributes;
    }

    protected function resolveOverallEffectiveDate(): ?Carbon
    {
        $dates = [];

        foreach ($this->getPresentTypes() as $type) {
            $date = $this->getTypeEffectiveDate($type);
            if ($date) {
                $dates[] = $date;
            }
        }

        if (empty($dates)) {
            return null;
        }

        usort($dates, fn (Carbon $left, Carbon $right) => $left->timestamp <=> $right->timestamp);

        return $dates[0];
    }

    protected function getPrimaryBaseValue(string $type, string $stage): ?float
    {
        $fieldMap = [
            self::TYPE_SOCIAL_SECURITY => [
                'old' => 'old_social_security_base',
                'new' => 'new_social_security_base',
            ],
            self::TYPE_MEDICAL_INSURANCE => [
                'old' => 'old_medical_insurance_base',
                'new' => 'new_medical_insurance_base',
            ],
            self::TYPE_HOUSING_FUND => [
                'old' => 'old_housing_fund_base',
                'new' => 'new_housing_fund_base',
            ],
            self::TYPE_LARGE_MEDICAL => [
                'old' => 'old_large_medical_base',
                'new' => 'new_large_medical_base',
            ],
        ];

        $field = $fieldMap[$type][$stage] ?? null;

        return $field ? $this->{$field} : null;
    }

    protected function getCompanyBaseValue(string $type, string $stage): ?float
    {
        if ($type !== self::TYPE_LARGE_MEDICAL) {
            return null;
        }

        $field = $stage === 'old'
            ? 'old_large_medical_company_base'
            : 'new_large_medical_company_base';

        return $this->{$field};
    }

    protected function hasAnyValue(array $fields): bool
    {
        foreach ($fields as $field) {
            if (!is_null($this->{$field}) && $this->{$field} !== '') {
                return true;
            }
        }

        return false;
    }
}
