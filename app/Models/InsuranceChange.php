<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceChange extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '保险变更';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'employee_name' => '员工姓名',
        'employee_id_number' => '身份证号',
        'employee_gender' => '性别',
        'employee_birth_date' => '出生日期',
        'employee_phone' => '联系电话',
        'employee_status' => '员工状态',
        'change_type' => '变更类型',
        'status' => '状态',
        'fully_confirmed' => '完整确认',
        'other_insurance_processed' => '其他保险已处理',
        'attachment_path' => '附件路径',
        'notes' => '备注',
        'employee_social_security_base' => '社保基数',
        'employee_medical_insurance_base' => '医保基数',
        'employee_housing_fund_base' => '公积金基数',
        'employee_large_medical_base' => '大额医疗基数',
        'employee_large_medical_company_base' => '大额医疗单位基数',
        'large_medical_insurance_enabled' => '启用大额医疗',
        'social_security_changed' => '社保变更',
        'medical_insurance_changed' => '医保变更',
        'housing_fund_changed' => '公积金变更',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->employee_name} {$this->change_type}";
    }

    protected $fillable = [
        'employee_id',
        'employee_name',  // 员工姓名
        'employee_id_number',  // 员工身份证号
        'employee_gender',  // 员工性别
        'employee_birth_date',  // 员工出生日期
        'employee_phone',  // 员工联系电话
        'employee_status',  // 员工状态
        'project_id',
        'account_set_id',
        'change_type',  // 增减类型：increase=新增参保，decrease=减少参保
        'status',
        'fully_confirmed',  // 是否已完整确认处理
        'other_insurance_processed',  // 是否已单独处理其他保险
        'attachment_path',
        'attachment_uploaded_at',
        'processed_at',
        'submitted_at',
        'completed_at',
        'notes',
        'created_by',
        'processed_by',
        'last_snapshot',
        'change_summary',
        'change_details',
        'used_quotas',  // 已使用的保险名额
        // 保险地区和配置ID
        'social_security_region_id',  // 社保地区ID
        'medical_insurance_region_id',  // 医保地区ID
        'housing_fund_region_id',  // 公积金地区ID
        'housing_fund_config_id',  // 公积金配置ID
        'large_medical_insurance_config_id',  // 大额医疗保险配置ID
        'large_medical_insurance_enabled',  // 是否启用大额医疗保险
        // 员工基数
        'employee_social_security_base',  // 员工社保基数
        'employee_medical_insurance_base',  // 员工医保基数
        'employee_housing_fund_base',  // 员工公积金基数
        'employee_large_medical_base',  // 员工大额医疗保险基数
        'employee_large_medical_company_base',  // 员工大额医疗保险单位基数（特殊地区）
        // 保险配置快照（JSON字段）
        'social_security_types',  // 社保险种配置快照
        'medical_insurance_types',  // 医保险种配置快照
        'housing_fund_params',  // 公积金配置快照
        'other_insurance_policies',  // 其他保险配置快照
        'large_medical_insurance_config',  // 大额医疗保险配置快照
        // 变更标记字段
        'social_security_changed',  // 社保是否发生变更
        'medical_insurance_changed',  // 医保是否发生变更
        'housing_fund_changed',  // 公积金是否发生变更
    ];

    protected $casts = [
        'used_quotas' => 'array',
        'attachment_uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'social_security_changed' => 'boolean',
        'medical_insurance_changed' => 'boolean',
        'housing_fund_changed' => 'boolean',
    ];

    /**
     * 获取解析后的变更详情
     */
    public function getParsedChangeDetailsAttribute()
    {
        return $this->parseChangeDetails();
    }

    /**
     * 生成变更描述
     */
    private function generateChangeDescription($category, $action, $item)
    {
        $categoryMap = [
            'social_security' => '社保',
            'medical_insurance' => '医保',
            'housing_fund' => '公积金',
            'other_insurance' => '其他保险'
        ];
        
        $actionMap = [
            'added' => '新增',
            'removed' => '删除',
            'modified' => '修改'
        ];
        
        $categoryName = $categoryMap[$category] ?? $category;
        $actionName = $actionMap[$action] ?? $action;
        
        return "{$categoryName}{$actionName}：{$item}";
    }


    /**
     * 员工关联
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 项目关联
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 账套关联
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }


    /**
     * 创建人关联
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 处理人关联
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * 附件关联
     */
    public function attachments()
    {
        return $this->hasMany(InsuranceChangeAttachment::class);
    }

    /**
     * 参保明细关联
     */
    public function details()
    {
        return $this->hasMany(InsuranceChangeDetail::class);
    }

    /**
     * 获取员工当前的社保配置（实时）
     * 应该始终从员工关联的项目中获取最新配置
     */
    public function getCurrentSocialSecurityConfig()
    {
        if ($this->employee && $this->employee->socialSecurityRegion && $this->employee->socialSecurityRegion->socialSecurityTypes) {
            return $this->employee->socialSecurityRegion->socialSecurityTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'region_id' => $type->region_id,
                    'name' => $type->name,
                    'base_amount' => $type->base_amount,
                    'employee_ratio' => (float)$type->employee_ratio,  // 确保转换为float
                    'company_ratio' => (float)$type->company_ratio,    // 确保转换为float
                ];
            })->toArray();
        }
        return [];
    }

    /**
     * 获取员工当前的医保配置（实时）
     * 应该始终从员工关联的项目中获取最新配置
     */
    public function getCurrentMedicalInsuranceConfig()
    {
        if ($this->employee && $this->employee->medicalInsuranceRegion && $this->employee->medicalInsuranceRegion->medicalInsuranceTypes) {
            return $this->employee->medicalInsuranceRegion->medicalInsuranceTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'region_id' => $type->region_id,
                    'name' => $type->name,
                    'base_amount' => $type->base_amount,
                    'employee_ratio' => (float)$type->employee_ratio,  // 确保转换为float
                    'company_ratio' => (float)$type->company_ratio,    // 确保转换为float
                ];
            })->toArray();
        }
        return [];
    }

    /**
     * 获取员工当前的公积金配置（实时）
     * 应该始终从员工关联的项目中获取最新配置
     */
    public function getCurrentHousingFundConfig()
    {
        if ($this->employee && $this->employee->housingFundConfig) {
            $config = $this->employee->housingFundConfig;
            return [
                'id' => $config->id,
                'config_name' => $config->config_name,
                'region_name' => $config->region ? $config->region->name : null,
                'base_amount' => $config->base_amount,
                'employee_ratio' => $config->employee_ratio,
                'company_ratio' => $config->company_ratio,
            ];
        }
        return null;
    }

    /**
     * 获取员工当前的其他保险配置（实时）
     * 应该始终从员工关联的项目中获取最新配置
     */
    public function getCurrentOtherInsuranceConfig()
    {
        $otherInsurancePolicies = [];
        if ($this->employee && $this->employee->projects) {
            foreach ($this->employee->projects as $project) {
                if ($project->otherInsurancePolicies) {
                    foreach ($project->otherInsurancePolicies as $policy) {
                        // 获取当前参保记录中已使用名额的保险ID列表
                        $usedQuotas = $this->used_quotas ?? [];
                        if (is_string($usedQuotas)) { // Manual parsing if it's a string
                            try {
                                $usedQuotas = json_decode($usedQuotas, true) ?? [];
                            } catch (\Exception $e) {
                                \Log::error('解析used_quotas失败: ' . $e->getMessage(), ['insurance_change_id' => $this->id, 'used_quotas_data' => $this->used_quotas]);
                                $usedQuotas = [];
                            }
                        }
                        if (!is_array($usedQuotas)) {
                            $usedQuotas = [];
                        }

                        $quotaUsed = false;
                        $removedPersonName = null;

                        foreach ($usedQuotas as $usedQuota) {
                            if (is_array($usedQuota)) {
                                if (($usedQuota['policy_id'] ?? null) == $policy->id) {
                                    $quotaUsed = true;
                                    $removedPersonName = $usedQuota['removed_person_name'] ?? null;
                                    break;
                                }
                            } else {
                                if ($usedQuota == $policy->id) {
                                    $quotaUsed = true;
                                    break;
                                }
                            }
                        }

                        // ✅ 修复：只根据 used_quotas 判断，不因为名额为0就标记为已用
                        // 名额为0可能是其他员工用完了，不代表当前员工使用了名额

                        $otherInsurancePolicies[] = [
                            'id' => $policy->id,
                            'name' => $policy->policy_name,
                            'type' => $policy->type ? $policy->type->name : '其他保险',
                            'coverage' => $policy->description ?: '已保障',
                            'employee_per_capita_cost' => $policy->employee_per_capita_cost,
                            'contact_name' => $policy->contact_name,
                            'contact_phone' => $policy->contact_phone,
                            'available_quota' => $policy->quota ?? 0,
                            'quota_used' => $quotaUsed,
                            'removed_person_name' => $removedPersonName,
                            'personnel_name_list' => $policy->personnel_name_list ?? [],
                            'endorsement_number' => $policy->endorsement_number,
                            'policy_end_date' => $policy->policy_end_date ?: $policy->end_date,
                        ];
                    }
                }
            }
        }
        return $otherInsurancePolicies;
    }

    /**
     * 获取员工当前的大额医疗保险配置（实时）
     */
    public function getCurrentLargeMedicalInsuranceConfig()
    {
        // 如果增减记录中有大额医疗保险配置ID，使用该配置
        if ($this->large_medical_insurance_config_id) {
            $config = \App\Models\LargeMedicalInsuranceConfig::find($this->large_medical_insurance_config_id);
            
            if ($config) {
                return [
                    'id' => $config->id,
                    'region_name' => $config->region_name,
                    'calculation_type' => $config->calculation_type,
                    'calculation_type_text' => $config->calculation_type === 'base' ? '按基数' : '按固定金额',
                    'base_amount' => $config->base_amount,
                    'company_ratio' => $config->company_ratio,
                    'employee_ratio' => $config->employee_ratio,
                    'company_amount' => $config->company_amount,
                    'employee_amount' => $config->employee_amount,
                    'payment_cycle' => $config->payment_cycle,
                    'payment_cycle_text' => $config->payment_cycle === 'month' ? '按月' : '按年',
                    'company_cost' => $config->calculation_type === 'base' ? ($config->base_amount * $config->company_ratio) : $config->company_amount,
                    'employee_cost' => $config->calculation_type === 'base' ? ($config->base_amount * $config->employee_ratio) : $config->employee_amount,
                    'total_cost' => $config->calculation_type === 'base' ? ($config->base_amount * ($config->company_ratio + $config->employee_ratio)) : ($config->company_amount + $config->employee_amount),
                    'is_enabled' => $this->large_medical_insurance_enabled,
                ];
            }
        }
        
        // 兼容旧数据：从员工档案中获取
        if (!$this->employee || !$this->employee->large_medical_insurance_config_id) {
            return null;
        }

        $config = \App\Models\LargeMedicalInsuranceConfig::find($this->employee->large_medical_insurance_config_id);
        if (!$config) {
            return null;
        }

        return [
            'id' => $config->id,
            'region_name' => $config->region_name,
            'calculation_type' => $config->calculation_type,
            'calculation_type_text' => $config->calculation_type === 'base' ? '按基数' : '按固定金额',
            'base_amount' => $config->base_amount,
            'company_ratio' => $config->company_ratio,
            'employee_ratio' => $config->employee_ratio,
            'company_amount' => $config->company_amount,
            'employee_amount' => $config->employee_amount,
            'payment_cycle' => $config->payment_cycle,
            'payment_cycle_text' => $config->payment_cycle === 'month' ? '按月' : '按年',
            'company_cost' => $config->calculation_type === 'base' ? ($config->base_amount * $config->company_ratio) : $config->company_amount,
            'employee_cost' => $config->calculation_type === 'base' ? ($config->base_amount * $config->employee_ratio) : $config->employee_amount,
            'total_cost' => $config->calculation_type === 'base' ? ($config->base_amount * ($config->company_ratio + $config->employee_ratio)) : ($config->company_amount + $config->employee_amount),
            'is_enabled' => $this->large_medical_insurance_enabled ?? false,
        ];
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statusMap = [
            'pending' => '待处理',
            'processing' => '处理中',
            'submitted' => '待提交汇总审批',
            'completed' => '已完成'
        ];

        return $statusMap[$this->status] ?? '未知';
    }

    /**
     * 获取状态标签类型
     */
    public function getStatusTagTypeAttribute()
    {
        $typeMap = [
            'pending' => 'warning',
            'processing' => 'info',
            'submitted' => 'primary',
            'completed' => 'success'
        ];

        return $typeMap[$this->status] ?? 'info';
    }

    /**
     * 检查是否需要重新处理
     */
    public function needsReprocessing()
    {
        // 如果保险参数发生变化，需要重新处理
        return $this->status === 'submitted' || $this->status === 'completed';
    }

    /**
     * 标记为待处理
     */
    public function markAsPending()
    {
        $this->update([
            'status' => 'pending',
            'attachment_path' => null,
            'attachment_uploaded_at' => null,
            'processed_at' => null,
            'submitted_at' => null,
            'completed_at' => null
        ]);
    }

    /**
     * 上传附件
     */
    public function uploadAttachment($path)
    {
        $this->update([
            'attachment_path' => $path,
            'attachment_uploaded_at' => now(),
            'status' => 'submitted'
        ]);
    }

    /**
     * 标记为已完成
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    /**
     * 检查并记录保险配置变更（详细检测新增/删除项目）
     * 
     * @param bool $updateSnapshot 是否更新快照（默认false，只有确认处理时才为true）
     * @return array 变更列表
     */
    public function checkAndRecordChanges($updateSnapshot = false)
    {
        // 如果员工不存在，无法检测
        if (!$this->employee) {
            return [];
        }

        // 重新加载员工数据以确保获取最新的保险配置
        $this->load(['employee.socialSecurityRegion.socialSecurityTypes', 'employee.medicalInsuranceRegion.medicalInsuranceTypes', 'employee.housingFundConfig', 'employee.projects.otherInsurancePolicies']);

        // 获取当前的保险配置详情（包含具体项目名称）
        $currentSnapshot = $this->buildCurrentSnapshot();
        
        // 获取上次的快照
        $lastSnapshot = $this->parseSnapshot($this->last_snapshot);

        // 如果是第一次检查，保存快照和完整的保险配置信息
        if (empty($lastSnapshot)) {
            $this->last_snapshot = $this->serializeSnapshot($currentSnapshot);
            
            // 保存完整的保险配置信息到对应字段
            $this->saveCompleteInsuranceConfig();
            
            // 生成初始变化详情（所有项目都标记为新增）
            $initialChanges = $this->generateInitialChanges($currentSnapshot);
            $this->change_summary = implode('；', $initialChanges['summary']);
            $this->change_details = $this->serializeChangeDetails($initialChanges['details']);
            
            $this->save();
            return $initialChanges['summary'];
        }

        // 详细对比差异
        $changes = [];
        $changeDetailsList = [];

        // 1. 对比社保类型
        $ssChanges = $this->compareInsuranceTypes(
            $currentSnapshot['social_security_types'],
            $lastSnapshot['social_security_types'],
            'social_security'
        );
        if (!empty($ssChanges['summary'])) {
            $changes[] = $ssChanges['summary'];
            $changeDetailsList = array_merge($changeDetailsList, $ssChanges['details']);
        }

        // 2. 对比医保类型
        $miChanges = $this->compareInsuranceTypes(
            $currentSnapshot['medical_insurance_types'],
            $lastSnapshot['medical_insurance_types'],
            'medical_insurance'
        );
        if (!empty($miChanges['summary'])) {
            $changes[] = $miChanges['summary'];
            $changeDetailsList = array_merge($changeDetailsList, $miChanges['details']);
        }

        // 3. 对比公积金配置（检测参数变化）
        $hfChanges = $this->compareHousingFund(
            $currentSnapshot['housing_fund_params'],
            $lastSnapshot['housing_fund_params']
        );
        if (!empty($hfChanges['summary'])) {
            $changes[] = $hfChanges['summary'];
            $changeDetailsList = array_merge($changeDetailsList, $hfChanges['details']);
        }

        // 4. 对比其他保险
        $oiChanges = $this->compareOtherInsurance(
            $currentSnapshot['other_insurance'],
            $lastSnapshot['other_insurance']
        );
        if (!empty($oiChanges['summary'])) {
            $changes[] = $oiChanges['summary'];
            $changeDetailsList = array_merge($changeDetailsList, $oiChanges['details']);
        }

        // 5. 对比大额医疗保险
        $lmChanges = $this->compareLargeMedicalInsurance(
            $currentSnapshot['large_medical_insurance_enabled'] ?? false,
            $currentSnapshot['large_medical_insurance_config'] ?? null,
            $lastSnapshot['large_medical_insurance_enabled'] ?? false,
            $lastSnapshot['large_medical_insurance_config'] ?? null
        );
        if (!empty($lmChanges['summary'])) {
            $changes[] = $lmChanges['summary'];
            $changeDetailsList = array_merge($changeDetailsList, $lmChanges['details']);
        }

        // 如果有变更，只记录变更信息，不更新状态
        if (!empty($changes)) {
            // 只记录变更信息，不修改状态
            $this->change_summary = implode('；', $changes);
            $this->change_details = $this->serializeChangeDetails($changeDetailsList);
            $this->save();

            \Log::info('检测到保险配置变更（仅记录，不更新状态）', [
                'insurance_change_id' => $this->id,
                'employee_id' => $this->employee_id,
                'current_status' => $this->status,
                'changes' => $changes,
                'details' => $changeDetailsList
            ]);
        }
        
        // 只有在明确要求更新快照时才更新（比如确认处理时）
        if ($updateSnapshot) {
            $this->last_snapshot = $this->serializeSnapshot($currentSnapshot);
            $this->save();
        }

        return $changes;
    }

    /**
     * 保存完整的保险配置信息到对应字段
     */
    public function saveCompleteInsuranceConfig()
    {
        // 确保加载所有必要的关联关系
        $this->load([
            'employee.socialSecurityRegion.socialSecurityTypes',
            'employee.medicalInsuranceRegion.medicalInsuranceTypes', 
            'employee.housingFundConfig',
            'employee.largeMedicalInsuranceConfigRelation',
            'employee.projects.otherInsurancePolicies'
        ]);
        
        // 保存社保配置
        if ($this->employee->socialSecurityRegion) {
            $socialSecurityTypes = [];
            foreach ($this->employee->socialSecurityRegion->socialSecurityTypes as $type) {
                $socialSecurityTypes[] = [
                    'id' => $type->id,
                    'region_id' => $type->region_id,
                    'name' => $type->name,
                    'base_amount' => $type->base_amount,
                    'employee_ratio' => $type->employee_ratio,
                    'company_ratio' => $type->company_ratio,
                    'created_by' => $type->created_by,
                    'created_at' => $type->created_at,
                    'updated_at' => $type->updated_at,
                ];
            }
            $this->social_security_types = json_encode($socialSecurityTypes);
        }

        // 保存医保配置
        if ($this->employee->medicalInsuranceRegion) {
            $medicalInsuranceTypes = [];
            foreach ($this->employee->medicalInsuranceRegion->medicalInsuranceTypes as $type) {
                $medicalInsuranceTypes[] = [
                    'id' => $type->id,
                    'region_id' => $type->region_id,
                    'name' => $type->name,
                    'base_amount' => $type->base_amount,
                    'employee_ratio' => $type->employee_ratio,
                    'company_ratio' => $type->company_ratio,
                    'account_set_id' => $type->account_set_id,
                    'created_by' => $type->created_by,
                    'created_at' => $type->created_at,
                    'updated_at' => $type->updated_at,
                ];
            }
            $this->medical_insurance_types = json_encode($medicalInsuranceTypes);
        }

        // 保存公积金配置
        if ($this->employee->housingFundConfig) {
            $this->housing_fund_params = json_encode([
                'config_name' => $this->employee->housingFundConfig->config_name,
                'region_name' => $this->employee->housingFundConfig->region_name,
                'base_amount' => $this->employee->housingFundConfig->base_amount,
                'employee_ratio' => $this->employee->housingFundConfig->employee_ratio,
                'company_ratio' => $this->employee->housingFundConfig->company_ratio,
            ]);
        }

        // 保存大额医疗保险配置
        if ($this->employee->largeMedicalInsuranceConfigRelation) {
            $this->large_medical_insurance_config = json_encode([
                'id' => $this->employee->largeMedicalInsuranceConfigRelation->id,
                'region_name' => $this->employee->largeMedicalInsuranceConfigRelation->region_name,
                'calculation_type' => $this->employee->largeMedicalInsuranceConfigRelation->calculation_type,
                'payment_cycle' => $this->employee->largeMedicalInsuranceConfigRelation->payment_cycle,
                'company_ratio' => $this->employee->largeMedicalInsuranceConfigRelation->company_ratio,
                'employee_ratio' => $this->employee->largeMedicalInsuranceConfigRelation->employee_ratio,
                'company_amount' => $this->employee->largeMedicalInsuranceConfigRelation->company_amount,
                'employee_amount' => $this->employee->largeMedicalInsuranceConfigRelation->employee_amount,
                'is_enabled' => $this->large_medical_insurance_enabled ?? false,
            ]);
        }

        // 保存其他保险配置
        $project = $this->employee->projects->first();
        if ($project && $project->otherInsurancePolicies) {
            // 确保加载 type 关联
            $project->load('otherInsurancePolicies.type');
            
            // 获取当前参保记录中已使用名额的保险ID列表
            $usedQuotas = $this->used_quotas ?? [];
            if (is_string($usedQuotas)) {
                $usedQuotas = json_decode($usedQuotas, true) ?? [];
            }
            if (!is_array($usedQuotas)) {
                $usedQuotas = [];
            }
            
            $otherInsurancePolicies = [];
            foreach ($project->otherInsurancePolicies as $policy) {
                // 检查是否已使用名额
                $quotaUsed = false;
                $removedPersonName = null;
                
                // 首先检查used_quotas中是否有记录
                foreach ($usedQuotas as $usedQuota) {
                    if (is_array($usedQuota)) {
                        // 新数据结构：包含policy_id和removed_person_name
                        if ($usedQuota['policy_id'] == $policy->id) {
                            $quotaUsed = true;
                            $removedPersonName = $usedQuota['removed_person_name'] ?? null;
                            break;
                        }
                    } else {
                        // 旧数据结构：直接是policy_id
                        if ($usedQuota == $policy->id) {
                            $quotaUsed = true;
                            break;
                        }
                    }
                }
                
                // ✅ 修复：只根据 used_quotas 判断，不因为名额为0就标记为已用
                // 名额为0可能是其他员工用完了，不代表当前员工使用了名额
                
                $otherInsurancePolicies[] = [
                    'id' => $policy->id,
                    'policy_name' => $policy->policy_name,
                    'name' => $policy->policy_name, // 添加 name 字段
                    'type' => $policy->type ? $policy->type->name : '其他保险',
                    'type_id' => $policy->type_id,
                    'coverage' => $policy->description ?: '已保障',
                    'description' => $policy->description,
                    'employee_per_capita_cost' => $policy->employee_per_capita_cost,
                    'contact_name' => $policy->contact_name,
                    'contact_phone' => $policy->contact_phone,
                    'available_quota' => $policy->quota ?? 0,
                    'quota' => $policy->quota ?? 0,
                    'quota_used' => $quotaUsed, // 根据used_quotas正确设置
                    'removed_person_name' => $removedPersonName, // 根据used_quotas正确设置
                    'personnel_name_list' => $policy->personnel_name_list ?? [],
                    'endorsement_number' => $policy->endorsement_number,
                    'policy_end_date' => $policy->policy_end_date ?: $policy->end_date,
                    'end_date' => $policy->end_date,
                ];
            }
            $this->other_insurance_policies = json_encode($otherInsurancePolicies);
        }

        // 保存大额医疗保险配置
        if ($this->large_medical_insurance_config_id) {
            $config = \App\Models\LargeMedicalInsuranceConfig::find($this->large_medical_insurance_config_id);
            if ($config) {
                $this->large_medical_insurance_config = json_encode([
                    'id' => $config->id,
                    'region_name' => $config->region_name,
                    'calculation_type' => $config->calculation_type,
                    'calculation_type_text' => $config->calculation_type === 'base' ? '按基数' : '固定金额',
                    'base_amount' => $config->base_amount,
                    'company_ratio' => $config->company_ratio,
                    'employee_ratio' => $config->employee_ratio,
                    'company_amount' => $config->company_amount,
                    'employee_amount' => $config->employee_amount,
                    'payment_cycle' => $config->payment_cycle,
                    'payment_cycle_text' => $config->payment_cycle === 'monthly' ? '按月' : '按年',
                    'is_enabled' => $this->large_medical_insurance_enabled ?? false,
                ]);
            }
        }
        
        // 保存所有配置到数据库
        $this->save();
    }

    /**
     * 生成初始变化详情（第一次检查时，所有项目都标记为新增）
     */
    private function generateInitialChanges($currentSnapshot)
    {
        $summary = [];
        $details = [];

        // 社保类型变化
        if (!empty($currentSnapshot['social_security_types'])) {
            $summary[] = '社保类型新增';
            foreach ($currentSnapshot['social_security_types'] as $type) {
                $parts = explode(':', $type);
                $name = $parts[0] ?? '未知';
                $details[] = [
                    'category' => 'social_security',
                    'action' => 'added',
                    'item' => $name,
                    'description' => "新增社保类型：{$name}"
                ];
            }
        }

        // 医保类型变化
        if (!empty($currentSnapshot['medical_insurance_types'])) {
            $summary[] = '医保类型新增';
            foreach ($currentSnapshot['medical_insurance_types'] as $type) {
                $parts = explode(':', $type);
                $name = $parts[0] ?? '未知';
                $details[] = [
                    'category' => 'medical_insurance',
                    'action' => 'added',
                    'item' => $name,
                    'description' => "新增医保类型：{$name}"
                ];
            }
        }

        // 公积金配置变化
        if (!empty($currentSnapshot['housing_fund_params'])) {
            $summary[] = '公积金配置新增';
            $parts = explode(':', $currentSnapshot['housing_fund_params']);
            $name = $parts[0] ?? '未知';
            $details[] = [
                'category' => 'housing_fund',
                'action' => 'added',
                'item' => $name,
                'description' => "新增公积金配置：{$name}"
            ];
        }

        // 其他保险变化
        if (!empty($currentSnapshot['other_insurance'])) {
            $summary[] = '其他保险新增';
            foreach ($currentSnapshot['other_insurance'] as $insurance) {
                $parts = explode(':', $insurance);
                $name = $parts[0] ?? '未知';
                $details[] = [
                    'category' => 'other_insurance',
                    'action' => 'added',
                    'item' => $name,
                    'description' => "新增其他保险：{$name}"
                ];
            }
        }

        // 大额医疗保险变化
        if (!empty($currentSnapshot['large_medical_insurance_enabled']) && !empty($currentSnapshot['large_medical_insurance_config'])) {
            $summary[] = '开启大额医疗保险';
            $details[] = [
                'category' => 'large_medical_insurance',
                'action' => 'enabled',
                'item' => '大额医疗保险',
                'description' => '开启大额医疗保险参保'
            ];
        }

        return [
            'summary' => $summary,
            'details' => $details
        ];
    }

    /**
     * 构建当前保险配置快照（包含详细参数）
     */
    private function buildCurrentSnapshot()
    {
        $snapshot = [
            'social_security_region' => $this->employee->social_security_region_id ?? null,
            'social_security_types' => [],
            'medical_insurance_region' => $this->employee->medical_insurance_region_id ?? null,
            'medical_insurance_types' => [],
            'housing_fund_config' => $this->employee->housing_fund_config_id ?? null,
            'housing_fund_params' => null,
            'other_insurance' => []
        ];

        // 获取社保类型列表（包含详细参数）
        if ($this->employee->socialSecurityRegion) {
            foreach ($this->employee->socialSecurityRegion->socialSecurityTypes as $type) {
                // 格式：名称:基数:个人比例:公司比例
                $snapshot['social_security_types'][] = sprintf(
                    '%s:%s:%s:%s',
                    $type->name,
                    $type->base_amount ?? 0,
                    $type->employee_ratio ?? 0,
                    $type->company_ratio ?? 0
                );
            }
        }

        // 获取医保类型列表（包含详细参数）
        if ($this->employee->medicalInsuranceRegion) {
            foreach ($this->employee->medicalInsuranceRegion->medicalInsuranceTypes as $type) {
                // 格式：名称:基数:个人比例:公司比例
                $snapshot['medical_insurance_types'][] = sprintf(
                    '%s:%s:%s:%s',
                    $type->name,
                    $type->base_amount ?? 0,
                    $type->employee_ratio ?? 0,
                    $type->company_ratio ?? 0
                );
            }
        }

        // 获取公积金配置（包含详细参数）
        if ($this->employee->housingFundConfig) {
            // 格式：配置名:基数:个人比例:公司比例
            $snapshot['housing_fund_params'] = sprintf(
                '%s:%s:%s:%s',
                $this->employee->housingFundConfig->config_name,
                $this->employee->housingFundConfig->base_amount ?? 0,
                $this->employee->housingFundConfig->employee_ratio ?? 0,
                $this->employee->housingFundConfig->company_ratio ?? 0
            );
        }

        // 获取其他保险列表（包含人均费用）
        $project = $this->employee->projects->first();
        if ($project && $project->otherInsurancePolicies) {
            foreach ($project->otherInsurancePolicies as $policy) {
                // 格式：保单名:人均费用
                $snapshot['other_insurance'][] = sprintf(
                    '%s:%s',
                    $policy->policy_name,
                    $policy->employee_per_capita_cost ?? 0
                );
            }
        }

        // 获取大额医疗保险配置
        $snapshot['large_medical_insurance_enabled'] = $this->large_medical_insurance_enabled ?? false;
        $snapshot['large_medical_insurance_config'] = null;
        if ($this->large_medical_insurance_enabled && $this->large_medical_insurance_config_id) {
            $config = \App\Models\LargeMedicalInsuranceConfig::find($this->large_medical_insurance_config_id);
            if ($config) {
                // 格式：配置ID:计算方式:公司金额:员工金额:公司比例:员工比例
                $snapshot['large_medical_insurance_config'] = sprintf(
                    '%s:%s:%s:%s:%s:%s',
                    $config->id,
                    $config->calculation_type,
                    $config->company_amount ?? 0,
                    $config->employee_amount ?? 0,
                    $config->company_ratio ?? 0,
                    $config->employee_ratio ?? 0
                );
            }
        }

        return $snapshot;
    }

    /**
     * 序列化快照（不使用JSON，包含详细参数）
     */
    private function serializeSnapshot($snapshot)
    {
        $parts = [];
        $parts[] = 'ss:' . ($snapshot['social_security_region'] ?? '');
        $parts[] = 'ss_types:' . implode(',', $snapshot['social_security_types']);
        $parts[] = 'mi:' . ($snapshot['medical_insurance_region'] ?? '');
        $parts[] = 'mi_types:' . implode(',', $snapshot['medical_insurance_types']);
        $parts[] = 'hf:' . ($snapshot['housing_fund_config'] ?? '');
        $parts[] = 'hf_params:' . ($snapshot['housing_fund_params'] ?? '');
        $parts[] = 'oi:' . implode(',', $snapshot['other_insurance']);
        $parts[] = 'lm_enabled:' . ($snapshot['large_medical_insurance_enabled'] ? '1' : '0');
        $parts[] = 'lm_config:' . ($snapshot['large_medical_insurance_config'] ?? '');
        
        return implode('|', $parts);
    }

    /**
     * 解析快照（包含详细参数）
     */
    private function parseSnapshot($snapshotString)
    {
        if (empty($snapshotString)) {
            return [];
        }

        $snapshot = [
            'social_security_region' => null,
            'social_security_types' => [],
            'medical_insurance_region' => null,
            'medical_insurance_types' => [],
            'housing_fund_config' => null,
            'housing_fund_params' => null,
            'other_insurance' => [],
            'large_medical_insurance_enabled' => false,
            'large_medical_insurance_config' => null
        ];

        $parts = explode('|', $snapshotString);
        foreach ($parts as $part) {
            if (strpos($part, ':') === false) continue;
            
            list($key, $value) = explode(':', $part, 2);
            
            switch ($key) {
                case 'ss':
                    $snapshot['social_security_region'] = $value ?: null;
                    break;
                case 'ss_types':
                    $snapshot['social_security_types'] = $value ? explode(',', $value) : [];
                    break;
                case 'mi':
                    $snapshot['medical_insurance_region'] = $value ?: null;
                    break;
                case 'mi_types':
                    $snapshot['medical_insurance_types'] = $value ? explode(',', $value) : [];
                    break;
                case 'hf':
                    $snapshot['housing_fund_config'] = $value ?: null;
                    break;
                case 'hf_params':
                    $snapshot['housing_fund_params'] = $value ?: null;
                    break;
                case 'oi':
                    $snapshot['other_insurance'] = $value ? explode(',', $value) : [];
                    break;
                case 'lm_enabled':
                    $snapshot['large_medical_insurance_enabled'] = $value === '1';
                    break;
                case 'lm_config':
                    $snapshot['large_medical_insurance_config'] = $value ?: null;
                    break;
            }
        }

        return $snapshot;
    }

    /**
     * 对比保险类型（社保/医保）- 增强版，检测参数变化
     */
    private function compareInsuranceTypes($current, $last, $category)
    {
        $summary = '';
        $details = [];
        $categoryName = $category === 'social_security' ? '社保' : '医保';

        // 解析当前和上次的保险项目（带参数）
        $currentMap = [];
        foreach ($current as $item) {
            $parts = explode(':', $item);
            if (count($parts) >= 4) {
                $currentMap[$parts[0]] = [
                    'name' => $parts[0],
                    'base' => $parts[1],
                    'emp_ratio' => $parts[2],
                    'com_ratio' => $parts[3],
                    'full' => $item
                ];
            }
        }

        $lastMap = [];
        foreach ($last as $item) {
            $parts = explode(':', $item);
            if (count($parts) >= 4) {
                $lastMap[$parts[0]] = [
                    'name' => $parts[0],
                    'base' => $parts[1],
                    'emp_ratio' => $parts[2],
                    'com_ratio' => $parts[3],
                    'full' => $item
                ];
            }
        }

        // 检测新增
        $addedCount = 0;
        foreach ($currentMap as $name => $currentItem) {
            if (!isset($lastMap[$name])) {
                $addedCount++;
                $details[] = [
                    'category' => $category,
                    'action' => 'added',
                    'item' => $name
                ];
            }
        }

        // 检测删除
        $removedCount = 0;
        foreach ($lastMap as $name => $lastItem) {
            if (!isset($currentMap[$name])) {
                $removedCount++;
                $details[] = [
                    'category' => $category,
                    'action' => 'removed',
                    'item' => $name
                ];
            }
        }

        // 检测参数修改
        $modifiedCount = 0;
        foreach ($currentMap as $name => $currentItem) {
            if (isset($lastMap[$name])) {
                // 同名项目，检查参数是否变化
                if ($currentItem['full'] !== $lastMap[$name]['full']) {
                    $modifiedCount++;
                    
                    // 构建修改详情
                    $changeDesc = $name;
                    $changes = [];
                    
                    if ($currentItem['base'] != $lastMap[$name]['base']) {
                        $changes[] = sprintf('基数%s→%s', $lastMap[$name]['base'], $currentItem['base']);
                    }
                    if ($currentItem['emp_ratio'] != $lastMap[$name]['emp_ratio']) {
                        $changes[] = sprintf('个人比例%s→%s', 
                            number_format($lastMap[$name]['emp_ratio'] * 100, 2) . '%',
                            number_format($currentItem['emp_ratio'] * 100, 2) . '%'
                        );
                    }
                    if ($currentItem['com_ratio'] != $lastMap[$name]['com_ratio']) {
                        $changes[] = sprintf('公司比例%s→%s',
                            number_format($lastMap[$name]['com_ratio'] * 100, 2) . '%',
                            number_format($currentItem['com_ratio'] * 100, 2) . '%'
                        );
                    }
                    
                    if (!empty($changes)) {
                        $changeDesc .= '（' . implode('，', $changes) . '）';
                    }
                    
                    $details[] = [
                        'category' => $category,
                        'action' => 'modified',
                        'item' => $changeDesc
                    ];
                }
            }
        }

        // 生成摘要
        $summaryParts = [];
        if ($addedCount > 0) {
            $summaryParts[] = $categoryName . '新增' . $addedCount . '项';
        }
        if ($removedCount > 0) {
            $summaryParts[] = $categoryName . '减少' . $removedCount . '项';
        }
        if ($modifiedCount > 0) {
            $summaryParts[] = $categoryName . '参数修改' . $modifiedCount . '项';
        }
        
        $summary = implode('；', $summaryParts);

        return ['summary' => $summary, 'details' => $details];
    }

    /**
     * 对比公积金配置 - 检测参数变化
     */
    private function compareHousingFund($current, $last)
    {
        $summary = '';
        $details = [];

        // 如果都为空，无变化
        if (empty($current) && empty($last)) {
            return ['summary' => '', 'details' => []];
        }

        // 如果一个为空，一个不为空，说明新增或删除配置
        if (empty($last) && !empty($current)) {
            $parts = explode(':', $current);
            $name = $parts[0] ?? '公积金配置';
            return [
                'summary' => '公积金配置新增',
                'details' => [[
                    'category' => 'housing_fund',
                    'action' => 'added',
                    'item' => $name
                ]]
            ];
        }

        if (!empty($last) && empty($current)) {
            $parts = explode(':', $last);
            $name = $parts[0] ?? '公积金配置';
            return [
                'summary' => '公积金配置删除',
                'details' => [[
                    'category' => 'housing_fund',
                    'action' => 'removed',
                    'item' => $name
                ]]
            ];
        }

        // 对比参数是否变化
        if ($current !== $last) {
            $currentParts = explode(':', $current);
            $lastParts = explode(':', $last);
            
            if (count($currentParts) >= 4 && count($lastParts) >= 4) {
                $name = $currentParts[0];
                $changes = [];
                
                // 检查基数
                if ($currentParts[1] != $lastParts[1]) {
                    $changes[] = sprintf('基数%s→%s', $lastParts[1], $currentParts[1]);
                }
                // 检查个人比例
                if ($currentParts[2] != $lastParts[2]) {
                    $changes[] = sprintf('个人比例%s→%s',
                        number_format($lastParts[2] * 100, 2) . '%',
                        number_format($currentParts[2] * 100, 2) . '%'
                    );
                }
                // 检查公司比例
                if ($currentParts[3] != $lastParts[3]) {
                    $changes[] = sprintf('公司比例%s→%s',
                        number_format($lastParts[3] * 100, 2) . '%',
                        number_format($currentParts[3] * 100, 2) . '%'
                    );
                }
                
                if (!empty($changes)) {
                    $changeDesc = $name . '（' . implode('，', $changes) . '）';
                    return [
                        'summary' => '公积金参数修改',
                        'details' => [[
                            'category' => 'housing_fund',
                            'action' => 'modified',
                            'item' => $changeDesc
                        ]]
                    ];
                }
            }
        }

        return ['summary' => '', 'details' => []];
    }

    /**
     * 对比其他保险 - 增强版，检测参数变化
     */
    private function compareOtherInsurance($current, $last)
    {
        $summary = '';
        $details = [];

        // 解析当前和上次的其他保险（带参数）
        $currentMap = [];
        foreach ($current as $item) {
            $parts = explode(':', $item);
            if (count($parts) >= 2) {
                $currentMap[$parts[0]] = [
                    'name' => $parts[0],
                    'cost' => $parts[1],
                    'full' => $item
                ];
            }
        }

        $lastMap = [];
        foreach ($last as $item) {
            $parts = explode(':', $item);
            if (count($parts) >= 2) {
                $lastMap[$parts[0]] = [
                    'name' => $parts[0],
                    'cost' => $parts[1],
                    'full' => $item
                ];
            }
        }

        // 检测新增
        $addedCount = 0;
        foreach ($currentMap as $name => $currentItem) {
            if (!isset($lastMap[$name])) {
                $addedCount++;
                $details[] = [
                    'category' => 'other_insurance',
                    'action' => 'added',
                    'item' => $name
                ];
            }
        }

        // 检测删除
        $removedCount = 0;
        foreach ($lastMap as $name => $lastItem) {
            if (!isset($currentMap[$name])) {
                $removedCount++;
                $details[] = [
                    'category' => 'other_insurance',
                    'action' => 'removed',
                    'item' => $name
                ];
            }
        }

        // 检测参数修改
        $modifiedCount = 0;
        foreach ($currentMap as $name => $currentItem) {
            if (isset($lastMap[$name]) && $currentItem['full'] !== $lastMap[$name]['full']) {
                $modifiedCount++;
                
                // 构建修改详情
                $changeDesc = $name;
                if ($currentItem['cost'] != $lastMap[$name]['cost']) {
                    $changeDesc .= sprintf('（人均费用%s→%s）', $lastMap[$name]['cost'], $currentItem['cost']);
                }
                
                $details[] = [
                    'category' => 'other_insurance',
                    'action' => 'modified',
                    'item' => $changeDesc
                ];
            }
        }

        // 生成摘要
        $summaryParts = [];
        if ($addedCount > 0) {
            $summaryParts[] = '其他保险新增' . $addedCount . '项';
        }
        if ($removedCount > 0) {
            $summaryParts[] = '其他保险减少' . $removedCount . '项';
        }
        if ($modifiedCount > 0) {
            $summaryParts[] = '其他保险参数修改' . $modifiedCount . '项';
        }
        
        $summary = implode('；', $summaryParts);

        return ['summary' => $summary, 'details' => $details];
    }

    /**
     * 对比大额医疗保险配置
     */
    private function compareLargeMedicalInsurance($currentEnabled, $currentConfig, $lastEnabled, $lastConfig)
    {
        $summary = '';
        $details = [];

        // 情况1：从未启用到启用
        if (!$lastEnabled && $currentEnabled) {
            $summary = '开启大额医疗保险';
            $details[] = [
                'category' => 'large_medical_insurance',
                'action' => 'enabled',
                'item' => '大额医疗保险',
                'description' => '开启大额医疗保险参保'
            ];
            return ['summary' => $summary, 'details' => $details];
        }

        // 情况2：从启用到未启用
        if ($lastEnabled && !$currentEnabled) {
            $summary = '关闭大额医疗保险';
            $details[] = [
                'category' => 'large_medical_insurance',
                'action' => 'disabled',
                'item' => '大额医疗保险',
                'description' => '关闭大额医疗保险参保'
            ];
            return ['summary' => $summary, 'details' => $details];
        }

        // 情况3：都启用，但配置变更
        if ($currentEnabled && $lastEnabled && $currentConfig !== $lastConfig) {
            // 解析配置参数
            $currentParts = explode(':', $currentConfig);
            $lastParts = explode(':', $lastConfig);
            
            if (count($currentParts) >= 6 && count($lastParts) >= 6) {
                $changes = [];
                
                // 检查计算方式
                if ($currentParts[1] != $lastParts[1]) {
                    $changes[] = sprintf('计算方式%s→%s', 
                        $lastParts[1] === 'base' ? '按基数' : '固定金额',
                        $currentParts[1] === 'base' ? '按基数' : '固定金额'
                    );
                }
                
                // 检查公司金额
                if ($currentParts[2] != $lastParts[2]) {
                    $changes[] = sprintf('公司金额%s→%s', $lastParts[2], $currentParts[2]);
                }
                
                // 检查员工金额
                if ($currentParts[3] != $lastParts[3]) {
                    $changes[] = sprintf('员工金额%s→%s', $lastParts[3], $currentParts[3]);
                }
                
                // 检查公司比例
                if ($currentParts[4] != $lastParts[4]) {
                    $changes[] = sprintf('公司比例%s→%s',
                        number_format($lastParts[4] * 100, 2) . '%',
                        number_format($currentParts[4] * 100, 2) . '%'
                    );
                }
                
                // 检查员工比例
                if ($currentParts[5] != $lastParts[5]) {
                    $changes[] = sprintf('员工比例%s→%s',
                        number_format($lastParts[5] * 100, 2) . '%',
                        number_format($currentParts[5] * 100, 2) . '%'
                    );
                }
                
                if (!empty($changes)) {
                    $summary = '大额医疗保险配置变更';
                    $changeDesc = '大额医疗保险（' . implode('，', $changes) . '）';
                    $details[] = [
                        'category' => 'large_medical_insurance',
                        'action' => 'modified',
                        'item' => $changeDesc
                    ];
                }
            }
        }

        return ['summary' => $summary, 'details' => $details];
    }

    /**
     * 序列化变更详情（不使用JSON）
     */
    private function serializeChangeDetails($details)
    {
        $lines = [];
        foreach ($details as $detail) {
            $lines[] = $detail['category'] . '|' . $detail['action'] . '|' . $detail['item'];
        }
        return implode("\n", $lines);
    }

    /**
     * 解析变更详情
     */
    public function parseChangeDetails()
    {
        if (empty($this->change_details)) {
            return [];
        }

        try {
            // 尝试解析JSON格式的变更详情
            $details = json_decode($this->change_details, true);
            
            if ($details && isset($details['changes']) && is_array($details['changes'])) {
                // 新格式：返回changes数组
                return $details['changes'];
            }
            
            if (is_array($details)) {
                // 如果直接是数组格式，直接返回
                return $details;
            }
        } catch (\Exception $e) {
            // JSON解析失败，尝试旧格式
        }

        // 旧格式：用换行符和管道符分隔
        $details = [];
        $lines = explode("\n", $this->change_details);
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $parts = explode('|', $line);
            if (count($parts) >= 3) {
                $details[] = [
                    'category' => $parts[0],
                    'action' => $parts[1],
                    'item' => $parts[2]
                ];
            }
        }
        
        return $details;
    }

    /**
     * 清除变更标记（当处理完成后调用）
     */
    public function clearChangeFlag()
    {
        $this->change_summary = null;
        $this->change_details = null;
        $this->save();
    }
    
    /**
     * 判断是否应该显示变更标记
     * 只有待处理状态时才显示
     */
    public function shouldShowChanges()
    {
        return $this->status === 'pending' && !empty($this->change_summary);
    }
}
