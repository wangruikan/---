<?php

namespace App\Services;

use App\Models\InsuranceChange;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InsuranceChangeDetectionService
{
    public const SCOPE_AFFECTED = 'affected';
    public const SCOPE_EMPLOYEE = 'employee';

    /**
     * 检测保险信息变更并自动导入到增减模块
     */
    public function detectAndImport($changeType, $oldData, $newData, $regionId = null)
    {
        return $this->triggerChange([
            'scope' => self::SCOPE_AFFECTED,
            'change_type' => $changeType,
            'old_data' => $oldData,
            'new_data' => $newData,
            'region_id' => $regionId,
            'source' => 'config_change',
        ]);
    }

    /**
     * 统一触发参保增减变更任务
     */
    public function triggerChange(array $event)
    {
        try {
            $scope = $event['scope'] ?? self::SCOPE_AFFECTED;
            $changeType = $event['change_type'] ?? null;
            $oldData = $event['old_data'] ?? [];
            $newData = $event['new_data'] ?? [];
            $regionId = $event['region_id'] ?? null;
            $projectId = $event['project_id'] ?? null;
            $year = $event['year'] ?? date('Y');
            $month = $event['month'] ?? date('n');

            if (!$changeType) {
                return [
                    'success' => false,
                    'message' => '变更类型不能为空',
                    'imported_count' => 0,
                ];
            }

            if ($scope === self::SCOPE_EMPLOYEE) {
                $employee = $event['employee'] ?? null;
                if (!$employee && !empty($event['employee_id'])) {
                    $employee = Employee::with(['projects'])->find($event['employee_id']);
                }

                if (!$employee) {
                    return [
                        'success' => false,
                        'message' => '员工不存在',
                        'imported_count' => 0,
                    ];
                }

                $record = $this->createOrUpdateInsuranceChange(
                    $employee,
                    $changeType,
                    $year,
                    $month,
                    $oldData,
                    $newData,
                    ['project_id' => $projectId]
                );

                return [
                    'success' => (bool) $record,
                    'message' => $record ? '参保增减变更任务已生成' : '参保增减变更任务生成失败',
                    'imported_count' => $record ? 1 : 0,
                    'record' => $record,
                ];
            }

            $affectedEmployees = $this->getAffectedEmployees($changeType, $regionId);
            if ($affectedEmployees->isEmpty()) {
                return [
                    'success' => true,
                    'message' => '保险信息已更新，但没有员工使用此配置',
                    'imported_count' => 0,
                ];
            }

            $importedCount = 0;
            foreach ($affectedEmployees as $employee) {
                $record = $this->createOrUpdateInsuranceChange(
                    $employee,
                    $changeType,
                    $year,
                    $month,
                    $oldData,
                    $newData,
                    ['project_id' => $projectId]
                );

                if ($record) {
                    $importedCount++;
                }
            }

            return [
                'success' => true,
                'message' => "保险信息已更新，已自动导入 {$importedCount} 名员工到增减模块",
                'imported_count' => $importedCount,
                'affected_employees' => $affectedEmployees->pluck('name')->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('统一触发参保增减变更失败', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => '生成参保增减变更任务失败：' . $e->getMessage(),
                'imported_count' => 0,
            ];
        }
    }

    private function getAffectedEmployees($changeType, $regionId = null)
    {
        $query = Employee::with(['projects']);
        
        // 只检测在职状态的员工，未入职的员工不检测
        $query->where('contract_status', 'active');

        switch ($changeType) {
            case 'social_security':
                if ($regionId) {
                    $query->where('social_security_region_id', $regionId);
                }
                break;
                
            case 'medical_insurance':
                if ($regionId) {
                    $query->where('medical_insurance_region_id', $regionId);
                }
                break;
                
            case 'housing_fund':
                if ($regionId) {
                    $query->where('housing_fund_region_id', $regionId);
                }
                break;
                
            case 'large_medical_insurance':
                // 大额医疗保险：只获取使用特定配置的员工
                if ($regionId) {
                    // regionId 在这里实际上是 config_id
                    $query->where('large_medical_insurance_config_id', $regionId);
                } else {
                    // 如果没有指定配置ID，获取所有启用了大额医疗保险的员工
                    $query->where('large_medical_insurance_config_id', '!=', null)
                          ->where('large_medical_insurance_config_id', '>', 0);
                }
                break;
                
            case 'other_insurance':
                // 其他保险：获取所有绑定了项目的员工
                $query->whereHas('projects');
                break;
        }

        return $query->get();
    }

    /**
     * 检查是否需要导入到增减模块
     */
    private function resolveProjectForEmployee($employee, $projectId = null)
    {
        if ($projectId) {
            return Project::find($projectId);
        }

        try {
            $activeProject = $employee->activeProjects()->first();
            if ($activeProject) {
                return $activeProject;
            }
        } catch (\Throwable $e) {
            // Some older call sites only load the projects relation.
        }

        if (!$employee->relationLoaded('projects')) {
            $employee->load('projects');
        }

        return $employee->projects->first();
    }

    private function checkNeedImport($employee, $year, $month)
    {
        // 检查是否有本月的增减模块记录（基于创建时间判断）
        $startOfMonth = "{$year}-{$month}-01 00:00:00";
        $endOfMonth = date('Y-m-t 23:59:59', strtotime($startOfMonth));
        
        $existingRecord = InsuranceChange::where('employee_id', $employee->id)
            ->where('project_id', $employee->projects->first()->id)
            ->where('account_set_id', $employee->account_set_id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->first();

        if (!$existingRecord) {
            // 没有记录，需要创建
            return true;
        }

        // 如果有记录且状态为"已处理"，需要创建新记录
        if ($existingRecord->status === 'completed') {
            return true;
        }

        // 如果有记录且状态为"待处理"或"已提交"，需要更新现有记录
        if ($existingRecord->status === 'pending' || $existingRecord->status === 'submitted') {
            return true;
        }

        return false;
    }

    /**
     * 创建或更新增减模块记录
     */
    public function createOrUpdateInsuranceChange($employee, $changeType, $year, $month, $oldData, $newData, array $options = [])
    {
        try {
            // 获取员工的项目信息
            $project = $this->resolveProjectForEmployee($employee, $options['project_id'] ?? null);
            if (!$project) {
                Log::warning('员工没有绑定项目，跳过导入', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name
                ]);
                return null;
            }

            // 检查是否已存在本月的记录（基于创建时间判断）
            $startOfMonth = "{$year}-{$month}-01 00:00:00";
            $endOfMonth = date('Y-m-t 23:59:59', strtotime($startOfMonth));
            
            $existingRecord = InsuranceChange::where('employee_id', $employee->id)
                ->where('project_id', $project->id)
                ->where('account_set_id', $employee->account_set_id)
                ->whereIn('status', ['pending', 'submitted'])
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->orderByDesc('id')
                ->first();

            if ($existingRecord) {
                // 更新现有记录 - 合并变更详情而不是覆盖
                $changeTypeText = $this->getChangeTypeText($changeType);
                $newChangeDetailsData = $this->generateChangeDetails($changeType, $oldData, $newData);
                $newChangeDetails = $newChangeDetailsData['changes'] ?? [];
                
                // 获取现有的变更详情
                $existingChangeDetails = [];
                if ($existingRecord->change_details) {
                    $existingData = json_decode($existingRecord->change_details, true);
                    $existingChangeDetails = $existingData['changes'] ?? [];
                }
                
                Log::info('合并变更详情 - 开始', [
                    'employee_id' => $employee->id,
                    'change_type' => $changeType,
                    'existing_changes_count' => count($existingChangeDetails),
                    'existing_changes' => $existingChangeDetails,
                    'new_changes_count' => count($newChangeDetails),
                    'new_changes' => $newChangeDetails
                ]);
                
                // 合并变更详情
                $mergedChangeDetails = $this->mergeChangeDetails($existingChangeDetails, $newChangeDetails);
                
                Log::info('合并变更详情 - 完成', [
                    'merged_changes_count' => count($mergedChangeDetails),
                    'merged_changes' => $mergedChangeDetails
                ]);
                
                // 生成新的变更摘要 - 从合并后的变更中提取所有类别
                $changeTypes = [];
                foreach ($mergedChangeDetails as $change) {
                    $changeTypes[$change['category']] = $this->getChangeTypeText($change['category']);
                }
                $changeSummary = "检测到" . implode('、', array_values($changeTypes)) . "配置变更，自动更新";
                
                Log::info('生成变更摘要', [
                    'change_types' => $changeTypes,
                    'change_summary' => $changeSummary
                ]);
                
                $existingRecord->update([
                    'status' => 'pending',
                    'change_summary' => $changeSummary,
                    'change_details' => json_encode([
                        'change_type' => $changeType,
                        'change_time' => now()->format('Y-m-d H:i:s'),
                        'auto_import' => true,
                        'changes' => $mergedChangeDetails
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now()
                ]);

                // 只更新当前变更类型的保险配置快照，而不是重新生成所有配置
                $this->updateSpecificInsuranceConfig($existingRecord, $changeType);
                $existingRecord->save();

                return $existingRecord;
            } else {
                // 创建新记录
                $changeTypeText = $this->getChangeTypeText($changeType);
                $changeDetailsData = $this->generateChangeDetails($changeType, $oldData, $newData);
                $changeDetails = $changeDetailsData['changes'] ?? [];
                
                $insuranceChange = InsuranceChange::create([
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'employee_id_number' => $employee->id_number,
                    'employee_gender' => $employee->gender === 'male' ? 1 : 2,
                    'employee_birth_date' => $employee->birth_date,
                    'employee_phone' => $employee->phone,
                    'employee_status' => null, // 暂时设为null，避免类型错误
                    'project_id' => $project->id,
                    'account_set_id' => $employee->account_set_id,
                    'social_security_region_id' => $employee->social_security_region_id,
                    'medical_insurance_region_id' => $employee->medical_insurance_region_id,
                    'housing_fund_region_id' => $employee->housing_fund_region_id,
                    'housing_fund_config_id' => $employee->housing_fund_config_id,
                    'large_medical_insurance_config_id' => $employee->large_medical_insurance_config_id,
                    'large_medical_insurance_enabled' => false,  // 默认关闭，需要手动开启
                    'employee_social_security_base' => $employee->social_security_base,
                    'employee_medical_insurance_base' => $employee->medical_insurance_base,
                    'employee_housing_fund_base' => $employee->housing_fund_base,
                    'employee_large_medical_base' => $employee->large_medical_base,
                    'employee_large_medical_company_base' => $employee->large_medical_company_base,
                    'change_type' => 'increase',  // 检测到的变更默认为新增记录
                    'status' => 'pending',
                    'change_summary' => "检测到{$changeTypeText}配置变更，自动导入",
                    'change_details' => json_encode([
                        'change_type' => $changeType,
                        'change_time' => now()->format('Y-m-d H:i:s'),
                        'auto_import' => true,
                        'changes' => $changeDetails
                    ], JSON_UNESCAPED_UNICODE),
                    'created_by' => 1, // 系统自动创建
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // 生成保险配置快照
                $insuranceChange->saveCompleteInsuranceConfig();
                $insuranceChange->save();

                return $insuranceChange;
            }

        } catch (\Exception $e) {
            Log::error('创建或更新增减模块记录失败', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 只更新特定保险类型的配置快照
     * @param InsuranceChange $record 保险变更记录
     * @param string $changeType 变更类型
     */
    private function updateSpecificInsuranceConfig($record, $changeType)
    {
        // 确保加载必要的关联关系
        $record->load([
            'employee.socialSecurityRegion.socialSecurityTypes',
            'employee.medicalInsuranceRegion.medicalInsuranceTypes', 
            'employee.housingFundConfig',
            'employee.largeMedicalInsuranceConfigRelation',
            'employee.projects.otherInsurancePolicies'
        ]);

        switch ($changeType) {
            case 'social_security':
                // 累积更新社保配置快照
                $existingTypes = [];
                if ($record->social_security_types) {
                    try {
                        $existingTypes = json_decode($record->social_security_types, true) ?: [];
                    } catch (Exception $e) {
                        $existingTypes = [];
                    }
                }
                
                // 获取当前数据库中的所有社保类型
                if ($record->employee->socialSecurityRegion) {
                    $currentTypes = [];
                    foreach ($record->employee->socialSecurityRegion->socialSecurityTypes as $type) {
                        $currentTypes[] = [
                            'id' => $type->id,
                            'region_id' => $type->region_id,
                            'name' => $type->name,
                            'min_base_amount' => $type->min_base_amount,
                            'max_base_amount' => $type->max_base_amount,
                            'employee_ratio' => $type->employee_ratio,
                            'company_ratio' => $type->company_ratio,
                            'created_by' => $type->created_by,
                            'created_at' => $type->created_at,
                            'updated_at' => $type->updated_at,
                        ];
                    }
                    
                    // 合并现有类型和当前类型，避免重复
                    $mergedTypes = $existingTypes;
                    foreach ($currentTypes as $currentType) {
                        $exists = false;
                        foreach ($mergedTypes as &$existingType) {
                            if ($existingType['id'] === $currentType['id']) {
                                // 更新现有类型
                                $existingType = $currentType;
                                $exists = true;
                                break;
                            }
                        }
                        unset($existingType);
                        if (!$exists) {
                            // 添加新类型
                            $mergedTypes[] = $currentType;
                        }
                    }
                    
                    $record->social_security_types = json_encode($mergedTypes);
                }
                break;
                
            case 'medical_insurance':
                // 累积更新医保配置快照
                $existingTypes = [];
                if ($record->medical_insurance_types) {
                    try {
                        $existingTypes = json_decode($record->medical_insurance_types, true) ?: [];
                    } catch (Exception $e) {
                        $existingTypes = [];
                    }
                }
                
                // 获取当前数据库中的所有医保类型
                if ($record->employee->medicalInsuranceRegion) {
                    $currentTypes = [];
                    foreach ($record->employee->medicalInsuranceRegion->medicalInsuranceTypes as $type) {
                        $currentTypes[] = [
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
                    
                    // 合并现有类型和当前类型，避免重复
                    $mergedTypes = $existingTypes;
                    foreach ($currentTypes as $currentType) {
                        $exists = false;
                        foreach ($mergedTypes as &$existingType) {
                            if ($existingType['id'] === $currentType['id']) {
                                // 更新现有类型
                                $existingType = $currentType;
                                $exists = true;
                                break;
                            }
                        }
                        unset($existingType);
                        if (!$exists) {
                            // 添加新类型
                            $mergedTypes[] = $currentType;
                        }
                    }
                    
                    $record->medical_insurance_types = json_encode($mergedTypes);
                }
                break;
                
            case 'housing_fund':
                // 只更新公积金配置
                if ($record->employee->housingFundConfig) {
                    $record->housing_fund_params = json_encode([
                        'config_name' => $record->employee->housingFundConfig->config_name,
                        'region_name' => $record->employee->housingFundConfig->region_name,
                        'base_amount' => $record->employee->housingFundConfig->base_amount,
                        'employee_ratio' => $record->employee->housingFundConfig->employee_ratio,
                        'company_ratio' => $record->employee->housingFundConfig->company_ratio,
                    ]);
                }
                break;
                
            case 'large_medical_insurance':
                // 更新大额医疗保险配置和基数
                if ($record->employee->largeMedicalInsuranceConfigRelation) {
                    $config = $record->employee->largeMedicalInsuranceConfigRelation;
                    
                    // 构建配置快照JSON
                    $configJson = json_encode([
                        'id' => $config->id,
                        'region_name' => $config->region_name,
                        'calculation_type' => $config->calculation_type,
                        'payment_cycle' => $config->payment_cycle,
                        'base_source' => $config->base_source,
                        'base_amount' => $config->base_amount,
                        'employee_base_amount' => $config->employee_base_amount,
                        'company_ratio' => $config->company_ratio,
                        'employee_ratio' => $config->employee_ratio,
                        'company_amount' => $config->company_amount,
                        'employee_amount' => $config->employee_amount,
                        'is_enabled' => $record->large_medical_insurance_enabled ?? false,
                    ]);
                    
                    // 更新增减记录的配置快照
                    $record->large_medical_insurance_config = $configJson;
                    
                    // 如果是特殊地区（base_source === 'config'），从配置同步基数
                    if ($config->base_source === 'config' && $config->calculation_type === 'base') {
                        $employeeBase = $config->employee_base_amount ?? $config->base_amount;
                        $companyBase = $config->base_amount;
                        
                        // 更新增减记录中的基数
                        $record->employee_large_medical_base = $employeeBase;
                        $record->employee_large_medical_company_base = $companyBase;
                        
                        // 同时更新员工档案中的基数
                        if ($record->employee) {
                            $record->employee->large_medical_base = $employeeBase;
                            $record->employee->large_medical_company_base = $companyBase;
                            $record->employee->save();
                        }
                        
                        // 同时更新参保人员表中的基数
                        $personnels = \App\Models\InsurancePersonnel::where('employee_id', $record->employee_id)
                            ->where('large_medical_insurance_config_id', $config->id)
                            ->get();
                        foreach ($personnels as $personnel) {
                            $personnel->employee_large_medical_base = $employeeBase;
                            $personnel->employee_large_medical_company_base = $companyBase;
                            $personnel->large_medical_insurance_config = $configJson;
                            $personnel->save();
                        }
                    }
                }
                break;
                
            case 'other_insurance':
                // 只更新其他保险配置
                // 重新加载员工和项目关联，确保获取最新数据
                $record->load(['employee.projects.otherInsurancePolicies']);
                
                $project = $record->employee->projects->first();
                if ($project && $project->otherInsurancePolicies) {
                    // 强制刷新 otherInsurancePolicies 关联
                    $project->load('otherInsurancePolicies');
                    
                    $usedQuotas = $record->used_quotas ?? [];
                    if (is_string($usedQuotas)) {
                        $usedQuotas = json_decode($usedQuotas, true) ?? [];
                    }
                    if (!is_array($usedQuotas)) {
                        $usedQuotas = [];
                    }
                    
                    $otherInsurancePolicies = [];
                    foreach ($project->otherInsurancePolicies as $policy) {
                        $quotaUsed = false;
                        $removedPersonName = null;
                        
                        foreach ($usedQuotas as $usedQuota) {
                            if (is_array($usedQuota)) {
                                if ($usedQuota['policy_id'] == $policy->id) {
                                    $quotaUsed = true;
                                    $removedPersonName = $usedQuota['removed_person_name'] ?? null;
                                    break;
                                }
                            }
                        }
                        
                        $otherInsurancePolicies[] = [
                            'id' => $policy->id,
                            'type_id' => $policy->type_id,
                            'policy_number' => $policy->policy_number,
                            'policy_name' => $policy->policy_name,
                            'insurance_company' => $policy->insurance_company,
                            'coverage_amount' => $policy->coverage_amount,
                            'employee_per_capita_cost' => $policy->employee_per_capita_cost,
                            'quota' => $policy->quota,
                            'contact_name' => $policy->contact_name,
                            'contact_phone' => $policy->contact_phone,
                            'personnel_name_list' => $policy->personnel_name_list,
                            'start_date' => $policy->start_date,
                            'end_date' => $policy->end_date,
                            'status' => $policy->status,
                            'description' => $policy->description,
                            'quota_used' => $quotaUsed,
                            'removed_person_name' => $removedPersonName,
                        ];
                    }
                    $record->other_insurance_policies = json_encode($otherInsurancePolicies);
                }
                break;
        }
    }

    /**
     * 合并变更详情
     * @param array $existingChanges 现有的变更详情
     * @param array $newChanges 新的变更详情
     * @return array 合并后的变更详情
     */
    private function mergeChangeDetails(array $existingChanges, array $newChanges): array
    {
        // 如果没有新变更，保留所有现有变更
        if (empty($newChanges)) {
            return $existingChanges;
        }

        // 使用一个临时映射来高效地查找和合并
        $mergedMap = [];

        // 1. 将所有现有变更（包括 'deleted' 动作）放入映射中
        foreach ($existingChanges as $change) {
            $key = $change['category'] . '_' . $change['item'];
            $mergedMap[$key] = $change;
        }

        // 2. 合并新的变更，解决冲突
        foreach ($newChanges as $newChange) {
            $key = $newChange['category'] . '_' . $newChange['item'];

            if (isset($mergedMap[$key])) {
                // 冲突：项目在现有变更和新变更中都存在。
                // 新变更优先。
                // 如果新动作是 'deleted'，它将覆盖任何先前的状态。
                // 如果新动作是 'modified' 或 'added'，它也将覆盖先前的状态。
                $mergedMap[$key] = $newChange;
            } else {
                // 没有冲突：添加新的变更。
                $mergedMap[$key] = $newChange;
            }
        }

        // 将映射转换回数组
        return array_values($mergedMap);
    }

    /**
     * 获取变更类型的中文文本
     */
    private function getChangeTypeText($changeType)
    {
        $typeMap = [
            'social_security' => '社保',
            'medical_insurance' => '医保',
            'housing_fund' => '公积金',
            'large_medical_insurance' => '大额医疗保险',
            'other_insurance' => '其他保险'
        ];

        return $typeMap[$changeType] ?? $changeType;
    }

    /**
     * 生成详细的变更信息
     */
    private function generateChangeDetails($changeType, $oldData, $newData)
    {
        $details = [
            'change_type' => $changeType,
            'change_time' => now()->toDateTimeString(),
            'auto_import' => true,
            'changes' => []
        ];

        switch ($changeType) {
            case 'social_security':
                $details['changes'] = $this->analyzeSocialSecurityChanges($oldData, $newData);
                break;
            case 'medical_insurance':
                $details['changes'] = $this->analyzeMedicalInsuranceChanges($oldData, $newData);
                break;
            case 'housing_fund':
                $details['changes'] = $this->analyzeHousingFundChanges($oldData, $newData);
                break;
            case 'large_medical_insurance':
                $details['changes'] = $this->analyzeLargeMedicalInsuranceChanges($oldData, $newData);
                break;
            case 'other_insurance':
                $details['changes'] = $this->analyzeOtherInsuranceChanges($oldData, $newData);
                break;
        }

        return $details;
    }

    private function addFieldChange(array &$changes, string $category, string $item, array $oldData, array $newData, string $key): void
    {
        if (!array_key_exists($key, $oldData) || !array_key_exists($key, $newData)) {
            return;
        }

        if ($this->isSameChangeValue($oldData[$key], $newData[$key])) {
            return;
        }

        $changes[] = [
            'category' => $category,
            'action' => 'modified',
            'item' => $item,
            'old_value' => $this->formatChangeValue($oldData[$key]),
            'new_value' => $this->formatChangeValue($newData[$key]),
        ];
    }

    private function isSameChangeValue($oldValue, $newValue): bool
    {
        if (($oldValue === null || $oldValue === '') && ($newValue === null || $newValue === '')) {
            return true;
        }

        if (is_numeric($oldValue) || is_numeric($newValue)) {
            return (float) $oldValue == (float) $newValue;
        }

        return $oldValue == $newValue;
    }

    private function formatChangeValue($value)
    {
        return ($value === null || $value === '') ? '无' : $value;
    }

    /**
     * 分析社保配置变更
     */
    private function analyzeSocialSecurityChanges($oldData, $newData)
    {
        $changes = [];

        $this->addFieldChange($changes, 'social_security', '社保地区', $oldData, $newData, 'region_id');
        $this->addFieldChange($changes, 'social_security', '员工社保基数', $oldData, $newData, 'employee_social_security_base');
        
        // 检查是否是删除操作（oldData有数据，newData为空）
        if (!empty($oldData) && empty($newData)) {
            // 这是删除操作
            $typeName = isset($oldData['name']) ? $oldData['name'] : '未知类型';
            $changes[] = [
                'category' => 'social_security',
                'action' => 'deleted',
                'item' => $typeName,
                'old_value' => '存在',
                'new_value' => '已删除',
            ];
            return $changes;
        }
        
        // 检查是否是新增操作（oldData为空，newData有数据）
        if (empty($oldData) && !empty($newData)) {
            // 这是新增操作
            $typeName = isset($newData['name']) ? $newData['name'] : '未知类型';
            $changes[] = [
                'category' => 'social_security',
                'action' => 'added',
                'item' => $typeName,
                'old_value' => '无',
                'new_value' => '已新增',
            ];
            return $changes;
        }
        
        // 检查是否是社保类型的变更（包含base_amount, employee_ratio, company_ratio字段）
        if (isset($oldData['base_amount']) && isset($oldData['employee_ratio']) && isset($oldData['company_ratio']) && !isset($oldData['social_security_types'])) {
            // 这是社保类型的变更
            $typeName = isset($oldData['name']) ? $oldData['name'] : '未知类型';
            
            // 检查基数变更
            if (isset($oldData['base_amount']) && isset($newData['base_amount']) && $oldData['base_amount'] != $newData['base_amount']) {
                $changes[] = [
                    'category' => 'social_security',
                    'action' => 'modified',
                    'item' => $typeName . ' - 基数',
                    'old_value' => $oldData['base_amount'],
                    'new_value' => $newData['base_amount']
                ];
            }
            
            // 检查员工比例变更
            if (isset($oldData['employee_ratio']) && isset($newData['employee_ratio']) && $oldData['employee_ratio'] != $newData['employee_ratio']) {
                $changes[] = [
                    'category' => 'social_security',
                    'action' => 'modified',
                    'item' => $typeName . ' - 员工比例',
                    'old_value' => ($oldData['employee_ratio'] * 100) . '%',
                    'new_value' => ($newData['employee_ratio'] * 100) . '%'
                ];
            }
            
            // 检查公司比例变更
            if (isset($oldData['company_ratio']) && isset($newData['company_ratio']) && $oldData['company_ratio'] != $newData['company_ratio']) {
                $changes[] = [
                    'category' => 'social_security',
                    'action' => 'modified',
                    'item' => $typeName . ' - 公司比例',
                    'old_value' => ($oldData['company_ratio'] * 100) . '%',
                    'new_value' => ($newData['company_ratio'] * 100) . '%'
                ];
            }
            
            // 检查类型名称变更
            if (isset($oldData['name']) && isset($newData['name']) && $oldData['name'] !== $newData['name']) {
                $changes[] = [
                    'category' => 'social_security',
                    'action' => 'modified',
                    'item' => '社保类型名称',
                    'old_value' => $oldData['name'],
                    'new_value' => $newData['name']
                ];
            }
        } else {
            // 检查是否包含social_security_types数组
            if (isset($oldData['social_security_types']) && isset($newData['social_security_types'])) {
                // 比较社保类型
                $oldTypes = collect($oldData['social_security_types'] ?? []);
                $newTypes = collect($newData['social_security_types'] ?? []);

                // 检查现有类型是否被修改或删除
                foreach ($oldTypes as $oldType) {
                    $newType = $newTypes->firstWhere('id', $oldType['id']);
                    if ($newType) {
                        // 类型存在，检查是否修改
                        if (($oldType['base_amount'] ?? null) !== ($newType['base_amount'] ?? null)) {
                            $changes[] = [
                                'category' => 'social_security',
                                'action' => 'modified',
                                'item' => $oldType['name'] . ' - 基数',
                                'old_value' => $oldType['base_amount'] ?? '无',
                                'new_value' => $newType['base_amount'] ?? '无',
                            ];
                        }
                        if (($oldType['employee_ratio'] ?? null) !== ($newType['employee_ratio'] ?? null)) {
                            $changes[] = [
                                'category' => 'social_security',
                                'action' => 'modified',
                                'item' => $oldType['name'] . ' - 员工比例',
                                'old_value' => ($oldType['employee_ratio'] ?? 0) * 100 . '%',
                                'new_value' => ($newType['employee_ratio'] ?? 0) * 100 . '%',
                            ];
                        }
                        if (($oldType['company_ratio'] ?? null) !== ($newType['company_ratio'] ?? null)) {
                            $changes[] = [
                                'category' => 'social_security',
                                'action' => 'modified',
                                'item' => $oldType['name'] . ' - 公司比例',
                                'old_value' => ($oldType['company_ratio'] ?? 0) * 100 . '%',
                                'new_value' => ($newType['company_ratio'] ?? 0) * 100 . '%',
                            ];
                        }
                    } else {
                        // 类型被删除
                        $changes[] = [
                            'category' => 'social_security',
                            'action' => 'deleted',
                            'item' => $oldType['name'],
                            'old_value' => '存在',
                            'new_value' => '已删除',
                        ];
                    }
                }

                // 检查新增类型
                foreach ($newTypes as $newType) {
                    $oldType = $oldTypes->firstWhere('id', $newType['id']);
                    if (!$oldType) {
                        $changes[] = [
                            'category' => 'social_security',
                            'action' => 'added',
                            'item' => $newType['name'],
                            'old_value' => '无',
                            'new_value' => '已新增',
                        ];
                    }
                }
            } else {
                // 这是社保地区的变更
                
                // 检查地区名称变更
                if (isset($oldData['name']) && isset($newData['name']) && $oldData['name'] !== $newData['name']) {
                    $changes[] = [
                        'category' => 'social_security',
                        'action' => 'modified',
                        'item' => '社保地区名称',
                        'old_value' => $oldData['name'],
                        'new_value' => $newData['name']
                    ];
                }

                // 检查调整基数变更
                if (isset($oldData['adjustment_base']) && isset($newData['adjustment_base']) && $oldData['adjustment_base'] != $newData['adjustment_base']) {
                    $changes[] = [
                        'category' => 'social_security',
                        'action' => 'modified',
                        'item' => '社保调整基数',
                        'old_value' => $oldData['adjustment_base'],
                        'new_value' => $newData['adjustment_base']
                    ];
                }
            }
        }

        // 如果没有检测到具体变更，但数据不同，则标记为一般配置变更
        if (empty($changes) && $oldData != $newData) {
            $changes[] = [
                'category' => 'social_security',
                'action' => 'modified',
                'item' => '社保配置',
                'old_value' => isset($oldData['name']) ? $oldData['name'] : '未知',
                'new_value' => isset($newData['name']) ? $newData['name'] : '未知'
            ];
        }

        return $changes;
    }

    /**
     * 分析医保配置变更
     */
    private function analyzeMedicalInsuranceChanges($oldData, $newData)
    {
        $changes = [];

        $this->addFieldChange($changes, 'medical_insurance', '医保地区', $oldData, $newData, 'region_id');
        $this->addFieldChange($changes, 'medical_insurance', '员工医保基数', $oldData, $newData, 'employee_medical_insurance_base');
        
        Log::info('分析医保配置变更', [
            'oldData' => $oldData,
            'newData' => $newData
        ]);
        
        // 检查是否是删除操作（oldData有数据，newData为空）
        if (!empty($oldData) && empty($newData)) {
            // 这是删除操作
            $typeName = isset($oldData['name']) ? $oldData['name'] : '未知类型';
            $changes[] = [
                'category' => 'medical_insurance',
                'action' => 'deleted',
                'item' => $typeName,
                'old_value' => '存在',
                'new_value' => '已删除',
            ];
            return $changes;
        }
        
        // 检查是否是新增操作（oldData为空，newData有数据）
        if (empty($oldData) && !empty($newData)) {
            // 这是新增操作
            $typeName = isset($newData['name']) ? $newData['name'] : '未知类型';
            $changes[] = [
                'category' => 'medical_insurance',
                'action' => 'added',
                'item' => $typeName,
                'old_value' => '无',
                'new_value' => '已新增',
            ];
            return $changes;
        }
        
        // 检查是否是医保类型的变更（有employee_ratio和company_ratio字段）
        if (isset($oldData['employee_ratio']) && isset($oldData['company_ratio'])) {
            // 这是医保类型的变更
            $typeName = isset($oldData['name']) ? $oldData['name'] : '未知类型';
            
            // 检查基数变更（如果有base_amount字段）
            if (isset($oldData['base_amount']) && isset($newData['base_amount']) && $oldData['base_amount'] != $newData['base_amount']) {
                $changes[] = [
                    'category' => 'medical_insurance',
                    'action' => 'modified',
                    'item' => $typeName . ' - 基数',
                    'old_value' => $oldData['base_amount'],
                    'new_value' => $newData['base_amount']
                ];
            }
            
            // 检查员工比例变更
            if (isset($oldData['employee_ratio']) && isset($newData['employee_ratio']) && $oldData['employee_ratio'] != $newData['employee_ratio']) {
                $changes[] = [
                    'category' => 'medical_insurance',
                    'action' => 'modified',
                    'item' => $typeName . ' - 员工比例',
                    'old_value' => ($oldData['employee_ratio'] * 100) . '%',
                    'new_value' => ($newData['employee_ratio'] * 100) . '%'
                ];
            }
            
            // 检查公司比例变更
            if (isset($oldData['company_ratio']) && isset($newData['company_ratio']) && $oldData['company_ratio'] != $newData['company_ratio']) {
                $changes[] = [
                    'category' => 'medical_insurance',
                    'action' => 'modified',
                    'item' => $typeName . ' - 公司比例',
                    'old_value' => ($oldData['company_ratio'] * 100) . '%',
                    'new_value' => ($newData['company_ratio'] * 100) . '%'
                ];
            }
            
            // 检查类型名称变更
            if (isset($oldData['name']) && isset($newData['name']) && $oldData['name'] !== $newData['name']) {
                $changes[] = [
                    'category' => 'medical_insurance',
                    'action' => 'modified',
                    'item' => '医保类型名称',
                    'old_value' => $oldData['name'],
                    'new_value' => $newData['name']
                ];
            }
        } else {
            // 这是医保地区的变更
            if (isset($oldData['name']) && isset($newData['name']) && $oldData['name'] !== $newData['name']) {
                $changes[] = [
                    'category' => 'medical_insurance',
                    'action' => 'modified',
                    'item' => '医保地区名称',
                    'old_value' => $oldData['name'],
                    'new_value' => $newData['name']
                ];
            }
        }

        Log::info('医保变更分析结果', [
            'changes_count' => count($changes),
            'changes' => $changes
        ]);

        return $changes;
    }

    /**
     * 分析公积金配置变更
     */
    private function analyzeHousingFundChanges($oldData, $newData)
    {
        $changes = [];

        $this->addFieldChange($changes, 'housing_fund', '公积金地区', $oldData, $newData, 'region_id');
        $this->addFieldChange($changes, 'housing_fund', '公积金配置', $oldData, $newData, 'config_id');
        $this->addFieldChange($changes, 'housing_fund', '员工公积金基数', $oldData, $newData, 'employee_housing_fund_base');
        
        // 检查是否是删除操作（oldData有数据，newData为空）
        if (!empty($oldData) && empty($newData)) {
            // 这是删除操作
            $configName = isset($oldData['region_name']) ? $oldData['region_name'] : (isset($oldData['config_name']) ? $oldData['config_name'] : '未知配置');
            $changes[] = [
                'category' => 'housing_fund',
                'action' => 'deleted',
                'item' => $configName,
                'old_value' => '存在',
                'new_value' => '已删除',
            ];
            return $changes;
        }
        
        // 检测地区名称变更
        if (isset($oldData['region_name']) && isset($newData['region_name']) && $oldData['region_name'] !== $newData['region_name']) {
            $changes[] = [
                'category' => 'housing_fund',
                'action' => 'modified',
                'item' => '公积金地区',
                'old_value' => $oldData['region_name'],
                'new_value' => $newData['region_name']
            ];
        }
        
        // 检测配置名称变更
        if (isset($oldData['config_name']) && isset($newData['config_name']) && $oldData['config_name'] !== $newData['config_name']) {
            $changes[] = [
                'category' => 'housing_fund',
                'action' => 'modified',
                'item' => '公积金配置名称',
                'old_value' => $oldData['config_name'],
                'new_value' => $newData['config_name']
            ];
        }
        
        // 检测基数变更
        if (isset($oldData['base_amount']) && isset($newData['base_amount']) && $oldData['base_amount'] != $newData['base_amount']) {
            $changes[] = [
                'category' => 'housing_fund',
                'action' => 'modified',
                'item' => '公积金基数',
                'old_value' => '¥' . number_format($oldData['base_amount'], 2),
                'new_value' => '¥' . number_format($newData['base_amount'], 2)
            ];
        }
        
        // 检测公司比例变更
        if (isset($oldData['company_ratio']) && isset($newData['company_ratio']) && $oldData['company_ratio'] != $newData['company_ratio']) {
            $changes[] = [
                'category' => 'housing_fund',
                'action' => 'modified',
                'item' => '公积金公司比例',
                'old_value' => ($oldData['company_ratio'] * 100) . '%',
                'new_value' => ($newData['company_ratio'] * 100) . '%'
            ];
        }
        
        // 检测员工比例变更
        if (isset($oldData['employee_ratio']) && isset($newData['employee_ratio']) && $oldData['employee_ratio'] != $newData['employee_ratio']) {
            $changes[] = [
                'category' => 'housing_fund',
                'action' => 'modified',
                'item' => '公积金员工比例',
                'old_value' => ($oldData['employee_ratio'] * 100) . '%',
                'new_value' => ($newData['employee_ratio'] * 100) . '%'
            ];
        }

        return $changes;
    }

    /**
     * 分析大额医疗保险配置变更
     */
    private function analyzeLargeMedicalInsuranceChanges($oldData, $newData)
    {
        $changes = [];

        $this->addFieldChange($changes, 'large_medical_insurance', '员工大额医疗个人基数', $oldData, $newData, 'employee_large_medical_base');
        $this->addFieldChange($changes, 'large_medical_insurance', '员工大额医疗公司基数', $oldData, $newData, 'employee_large_medical_company_base');
        
        // 检查是否是删除操作（oldData有数据，newData为空）
        if (!empty($oldData) && empty($newData)) {
            // 这是删除操作
            $configName = isset($oldData['region_name']) ? $oldData['region_name'] : '未知配置';
            $changes[] = [
                'category' => 'large_medical_insurance',
                'action' => 'deleted',
                'item' => $configName,
                'old_value' => '存在',
                'new_value' => '已删除',
            ];
            return $changes;
        }
        
        // 检测配置ID变更（启用/禁用）
        if (isset($oldData['config_id']) && isset($newData['config_id']) && $oldData['config_id'] != $newData['config_id']) {
            if ($oldData['config_id'] && !$newData['config_id']) {
                $changes[] = [
                    'category' => 'large_medical_insurance',
                    'action' => 'disabled',
                    'item' => '大额医疗保险',
                    'old_value' => '已启用',
                    'new_value' => '已禁用'
                ];
            } elseif (!$oldData['config_id'] && $newData['config_id']) {
                $changes[] = [
                    'category' => 'large_medical_insurance',
                    'action' => 'enabled',
                    'item' => '大额医疗保险',
                    'old_value' => '已禁用',
                    'new_value' => '已启用'
                ];
            } else {
                $changes[] = [
                    'category' => 'large_medical_insurance',
                    'action' => 'modified',
                    'item' => '大额医疗保险配置',
                    'old_value' => '配置ID: ' . $oldData['config_id'],
                    'new_value' => '配置ID: ' . $newData['config_id']
                ];
            }
        }
        
        // 检测地区名称变更
        if (isset($oldData['region_name']) && isset($newData['region_name']) && $oldData['region_name'] !== $newData['region_name']) {
            $changes[] = [
                'category' => 'large_medical_insurance',
                'action' => 'modified',
                'item' => '大额医疗保险地区',
                'old_value' => $oldData['region_name'],
                'new_value' => $newData['region_name']
            ];
        }
        
        // 检测计算方式变更
        if (isset($oldData['calculation_type']) && isset($newData['calculation_type']) && $oldData['calculation_type'] !== $newData['calculation_type']) {
            $oldType = $oldData['calculation_type'] === 'fixed' ? '固定金额' : '按基数';
            $newType = $newData['calculation_type'] === 'fixed' ? '固定金额' : '按基数';
            $changes[] = [
                'category' => 'large_medical_insurance',
                'action' => 'modified',
                'item' => '大额医疗保险计算方式',
                'old_value' => $oldType,
                'new_value' => $newType
            ];
        }
        
        // 检测公司比例变更
        if (isset($oldData['company_ratio']) && isset($newData['company_ratio']) && $oldData['company_ratio'] != $newData['company_ratio']) {
            $changes[] = [
                'category' => 'large_medical_insurance',
                'action' => 'modified',
                'item' => '大额医疗保险公司比例',
                'old_value' => ($oldData['company_ratio'] * 100) . '%',
                'new_value' => ($newData['company_ratio'] * 100) . '%'
            ];
        }
        
        // 检测员工比例变更
        if (isset($oldData['employee_ratio']) && isset($newData['employee_ratio']) && $oldData['employee_ratio'] != $newData['employee_ratio']) {
            $changes[] = [
                'category' => 'large_medical_insurance',
                'action' => 'modified',
                'item' => '大额医疗保险员工比例',
                'old_value' => ($oldData['employee_ratio'] * 100) . '%',
                'new_value' => ($newData['employee_ratio'] * 100) . '%'
            ];
        }
        
        // 检测付款周期变更
        if (isset($oldData['payment_cycle']) && isset($newData['payment_cycle']) && $oldData['payment_cycle'] !== $newData['payment_cycle']) {
            $oldCycle = $oldData['payment_cycle'] === 'yearly' ? '按年' : '按月';
            $newCycle = $newData['payment_cycle'] === 'yearly' ? '按年' : '按月';
            $changes[] = [
                'category' => 'large_medical_insurance',
                'action' => 'modified',
                'item' => '大额医疗保险付款周期',
                'old_value' => $oldCycle,
                'new_value' => $newCycle
            ];
        }
        
        // 检测公司金额变更
        if (isset($oldData['company_amount']) && isset($newData['company_amount']) && $oldData['company_amount'] != $newData['company_amount']) {
            $changes[] = [
                'category' => 'large_medical_insurance',
                'action' => 'modified',
                'item' => '大额医疗保险公司金额',
                'old_value' => '¥' . number_format($oldData['company_amount'], 2),
                'new_value' => '¥' . number_format($newData['company_amount'], 2)
            ];
        }
        
        // 检测员工金额变更
        if (isset($oldData['employee_amount']) && isset($newData['employee_amount']) && $oldData['employee_amount'] != $newData['employee_amount']) {
            $changes[] = [
                'category' => 'large_medical_insurance',
                'action' => 'modified',
                'item' => '大额医疗保险员工金额',
                'old_value' => '¥' . number_format($oldData['employee_amount'], 2),
                'new_value' => '¥' . number_format($newData['employee_amount'], 2)
            ];
        }

        return $changes;
    }

    /**
     * 分析其他保险配置变更
     */
    private function analyzeOtherInsuranceChanges($oldData, $newData)
    {
        $changes = [];

        if (array_key_exists('policies', $oldData) || array_key_exists('policies', $newData)) {
            $removedPolicies = array_values(array_filter($oldData['policies'] ?? []));
            $addedPolicies = array_values(array_filter($newData['policies'] ?? []));

            if (!empty($addedPolicies)) {
                $changes[] = [
                    'category' => 'other_insurance',
                    'action' => 'added',
                    'item' => '项目其他保险保单(新增)',
                    'old_value' => '无',
                    'new_value' => '新增保单ID: ' . implode(',', $addedPolicies),
                ];
            }

            if (!empty($removedPolicies)) {
                $changes[] = [
                    'category' => 'other_insurance',
                    'action' => 'deleted',
                    'item' => '项目其他保险保单(移除)',
                    'old_value' => '移除保单ID: ' . implode(',', $removedPolicies),
                    'new_value' => '无',
                ];
            }

            return $changes;
        }
        
        // 检查是否是删除操作（oldData有数据，newData为空）
        if (!empty($oldData) && empty($newData)) {
            // 这是删除操作
            $itemName = isset($oldData['name']) ? $oldData['name'] : (isset($oldData['policy_name']) ? $oldData['policy_name'] : '未知项目');
            $changes[] = [
                'category' => 'other_insurance',
                'action' => 'deleted',
                'item' => $itemName,
                'old_value' => '存在',
                'new_value' => '已删除',
            ];
            return $changes;
        }
        
        // 检测保单名称变更
        if (isset($oldData['policy_name']) && isset($newData['policy_name']) && $oldData['policy_name'] !== $newData['policy_name']) {
            $changes[] = [
                'category' => 'other_insurance',
                'action' => 'modified',
                'item' => '其他保险名称',
                'old_value' => $oldData['policy_name'],
                'new_value' => $newData['policy_name']
            ];
        }
        
        // 检测员工人均费用变更
        if (isset($oldData['employee_per_capita_cost']) && isset($newData['employee_per_capita_cost']) && $oldData['employee_per_capita_cost'] != $newData['employee_per_capita_cost']) {
            $changes[] = [
                'category' => 'other_insurance',
                'action' => 'modified',
                'item' => '其他保险员工人均费用',
                'old_value' => '¥' . number_format($oldData['employee_per_capita_cost'], 2),
                'new_value' => '¥' . number_format($newData['employee_per_capita_cost'], 2)
            ];
        }
        
        // 检测名额变更
        if (isset($oldData['quota']) && isset($newData['quota']) && $oldData['quota'] != $newData['quota']) {
            $changes[] = [
                'category' => 'other_insurance',
                'action' => 'modified',
                'item' => '其他保险名额',
                'old_value' => $oldData['quota'] . '人',
                'new_value' => $newData['quota'] . '人'
            ];
        }
        
        // 检测保单结束时间变更
        if (isset($oldData['policy_end_date']) && isset($newData['policy_end_date']) && $oldData['policy_end_date'] !== $newData['policy_end_date']) {
            $changes[] = [
                'category' => 'other_insurance',
                'action' => 'modified',
                'item' => '其他保险保单结束时间',
                'old_value' => $oldData['policy_end_date'] ?: '未设置',
                'new_value' => $newData['policy_end_date'] ?: '未设置'
            ];
        }
        
        // 检测批单号变更
        if (isset($oldData['endorsement_number']) && isset($newData['endorsement_number']) && $oldData['endorsement_number'] !== $newData['endorsement_number']) {
            $changes[] = [
                'category' => 'other_insurance',
                'action' => 'modified',
                'item' => '其他保险批单号',
                'old_value' => $oldData['endorsement_number'] ?: '未设置',
                'new_value' => $newData['endorsement_number'] ?: '未设置'
            ];
        }
        
        // 检测保障内容变更
        if (isset($oldData['description']) && isset($newData['description']) && $oldData['description'] !== $newData['description']) {
            $changes[] = [
                'category' => 'other_insurance',
                'action' => 'modified',
                'item' => '其他保险保障内容',
                'old_value' => mb_substr($oldData['description'], 0, 50) . (mb_strlen($oldData['description']) > 50 ? '...' : ''),
                'new_value' => mb_substr($newData['description'], 0, 50) . (mb_strlen($newData['description']) > 50 ? '...' : '')
            ];
        }

        return $changes;
    }
}
