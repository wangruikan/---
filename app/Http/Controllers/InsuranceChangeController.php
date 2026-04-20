<?php

namespace App\Http\Controllers;

use App\Models\InsuranceChange;
use App\Models\InsuranceChangeDetail;
use App\Models\InsuranceChangeSummary;
use App\Models\InsurancePersonnel;
use App\Models\InsuranceDetailRecord;
use App\Models\Employee;
use App\Models\Project;
use App\Models\SocialSecurityRegion;
use App\Models\MedicalInsuranceRegion;
use App\Models\HousingFund;
use App\Models\OtherInsurancePolicy;
use App\Models\InsuranceSurrenderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Traits\ChecksPermission;

class InsuranceChangeController extends ApiController
{
    use ChecksPermission;
    /**
     * 获取参保增减列表
     */
    public function index(Request $request)
    {
        // 参保增减查看权限
        if ($response = $this->checkPermission('insurance_change.view')) {
            return $response;
        }

        $user = $request->user();
        $accountSetId = $request->input('account_set_id');
        $status = $request->input('status');
        $regionName = $request->input('region_name');
        $month = $request->input('month'); // 新增月份筛选参数

        $query = InsuranceChange::where('account_set_id', $accountSetId)
            ->with([
                'employee.socialSecurityRegion.socialSecurityTypes',
                'employee.medicalInsuranceRegion.medicalInsuranceTypes',
                'employee.housingFundRegion',
                'employee.housingFundConfig',
                'employee.projects.otherInsurancePolicies.type',
                'project',
                'creator',
                'attachments'  // 加载附件列表
            ]);

        // 月份筛选 - 根据创建时间筛选
        if ($month) {
            // 解析月份参数 (格式: YYYY-MM)
            $year = substr($month, 0, 4);
            $monthNum = substr($month, 5, 2);
            
            // 构建日期范围
            $startDate = $year . '-' . $monthNum . '-01 00:00:00';
            $endDate = date('Y-m-t 23:59:59', strtotime($startDate));
            
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // 状态筛选
        if ($status) {
            $query->where('status', $status);
        }

        // 地区筛选 - 通过员工关联的地区进行筛选
        if ($regionName) {
            $query->whereHas('employee.socialSecurityRegion', function($q) use ($regionName) {
                $q->where('name', $regionName);
            })->orWhereHas('employee.medicalInsuranceRegion', function($q) use ($regionName) {
                $q->where('name', $regionName);
            })->orWhereHas('employee.housingFundRegion', function($q) use ($regionName) {
                $q->where('region_name', $regionName);
            });
        }

        $changes = $query->orderBy('created_at', 'desc')->get();

        // 使用快照数据而不是实时数据
        $changes->each(function ($change) {
            // $change->social_security_types = $change->getCurrentSocialSecurityConfig();
            // $change->medical_insurance_types = $change->getCurrentMedicalInsuranceConfig();
            // $change->housing_fund_params = $change->getCurrentHousingFundConfig();
            // $change->other_insurance_policies = $change->getCurrentOtherInsuranceConfig();
            
            // 禁用保险配置变更检测
            // $detectedChanges = $change->checkAndRecordChanges();
            $change->detected_changes = [];
            
            // 大额医疗保险作为额外属性返回（不保存到数据库）
            // $change->large_medical_insurance = $change->getCurrentLargeMedicalInsuranceConfig();
            
            // 解析变更详情，传递给前端
            $change->parsed_change_details = $change->parseChangeDetails();
        });

        return response()->json([
            'success' => true,
            'data' => $changes
        ]);
    }

    /**
     * 获取单个参保记录详情
     */
    public function show($id)
    {
        // 参保增减查看权限（详情）
        if ($response = $this->checkPermission('insurance_change.view')) {
            return $response;
        }

        $change = InsuranceChange::with([
            'employee.socialSecurityRegion.socialSecurityTypes',
            'employee.medicalInsuranceRegion.medicalInsuranceTypes',
            'employee.housingFundRegion',
            'employee.housingFundConfig',
            'employee.largeMedicalInsuranceConfigRelation', // 添加大额医疗保险配置关联
            'employee.projects.otherInsurancePolicies.type',
            'project',
            'creator',
            'attachments'
        ])->findOrFail($id);

        // 使用快照数据而不是实时数据
        // $change->social_security_types = $change->getCurrentSocialSecurityConfig();
        // $change->medical_insurance_types = $change->getCurrentMedicalInsuranceConfig();
        // $change->housing_fund_params = $change->getCurrentHousingFundConfig();
        // $change->other_insurance_policies = $change->getCurrentOtherInsuranceConfig();
        
        // 检查并记录保险配置变更
        // 禁用保险配置变更检测
        // $detectedChanges = $change->checkAndRecordChanges();
        $change->detected_changes = [];
        
        // 大额医疗保险作为额外属性返回（不保存到数据库）
        $change->large_medical_insurance_config = $change->getCurrentLargeMedicalInsuranceConfig();
        
        // 解析变更详情，传递给前端
        $change->parsed_change_details = $change->parseChangeDetails();

        // 确保 other_insurance_policies 是数组格式
        if ($change->other_insurance_policies && is_string($change->other_insurance_policies)) {
            $change->other_insurance_policies = json_decode($change->other_insurance_policies, true) ?: [];
        }
        
        // 确保 social_security_types 是数组格式
        if ($change->social_security_types && is_string($change->social_security_types)) {
            $change->social_security_types = json_decode($change->social_security_types, true) ?: [];
        }
        
        // 确保 medical_insurance_types 是数组格式
        if ($change->medical_insurance_types && is_string($change->medical_insurance_types)) {
            $change->medical_insurance_types = json_decode($change->medical_insurance_types, true) ?: [];
        }
        
        // 确保 housing_fund_params 是数组格式
        if ($change->housing_fund_params && is_string($change->housing_fund_params)) {
            $change->housing_fund_params = json_decode($change->housing_fund_params, true) ?: [];
        }

        return response()->json([
            'success' => true,
            'data' => $change
        ]);
    }

    /**
     * 获取参保明细
     */
    public function getDetails(Request $request)
    {
        // 参保增减查看权限（明细）
        if ($response = $this->checkPermission('insurance_change.view')) {
            return $response;
        }

        $user = $request->user();
        $accountSetId = $request->input('account_set_id');
        $regionName = $request->input('region_name');
        $month = $request->input('month'); // 月份筛选参数

        // 完全移除权限检查 - 允许所有访问

        // 判断查询的是当月还是历史月份
        $currentYearMonth = date('Y-m'); // 当前年月，格式：2025-10
        $isCurrentMonth = !$month || $month === $currentYearMonth;

        if ($isCurrentMonth) {
            // 当月数据：从 insurance_personnel 表读取实时数据
            return $this->getCurrentMonthDetails($request, $accountSetId, $regionName);
        } else {
            // 历史月份：从 insurance_detail_records 表读取归档数据
            return $this->getHistoricalMonthDetails($request, $accountSetId, $regionName, $month);
        }
    }

    /**
     * 获取当月实时数据
     */
    private function getCurrentMonthDetails($request, $accountSetId, $regionName)
    {
        // 从 insurance_personnel 表读取确认后的参保信息
        $query = \App\Models\InsurancePersonnel::where('account_set_id', $accountSetId)
            ->where('status', 'active') // 只显示活跃的参保记录
            ->with([
                'employee.socialSecurityRegion.socialSecurityTypes',
                'employee.medicalInsuranceRegion.medicalInsuranceTypes',
                'employee.housingFundRegion',
                'employee.housingFundConfig',
                'employee.largeMedicalInsuranceConfigRelation',
                'employee.projects.otherInsurancePolicies.type',
                'project'
            ]);

        // 地区筛选 - 通过员工关联的地区进行筛选
        if ($regionName && $regionName !== '全部') {
            $query->whereHas('employee.socialSecurityRegion', function($q) use ($regionName) {
                $q->where('name', $regionName);
            })->orWhereHas('employee.medicalInsuranceRegion', function($q) use ($regionName) {
                $q->where('name', $regionName);
            })->orWhereHas('employee.housingFundRegion', function($q) use ($regionName) {
                $q->where('region_name', $regionName);
            });
        }

        $personnelRecords = $query->orderBy('last_updated_at', 'desc')->get();

        // 为每个参保人员记录生成明细数据（包括补交数据）
        $details = [];
        foreach ($personnelRecords as $personnel) {
            // 生成正常数据
            $normalDetail = $this->generatePersonnelDetail($personnel);
            if ($normalDetail) {
                $details[] = $normalDetail;
            }
            
            // 生成补交数据（如果有的话）
            $supplementaryDetails = $this->generateSupplementaryDetails($personnel);
            $details = array_merge($details, $supplementaryDetails);
        }

        return response()->json([
            'success' => true,
            'data' => $details,
            'source' => 'current_month' // 标识数据来源
        ]);
    }

    /**
     * 获取历史月份归档数据
     */
    private function getHistoricalMonthDetails($request, $accountSetId, $regionName, $month)
    {
        // 解析月份参数 (格式: YYYY-MM)
        $year = substr($month, 0, 4);
        $monthNum = (int)substr($month, 5, 2);

        // 从 insurance_detail_records 表读取归档数据
        $query = InsuranceDetailRecord::where('account_set_id', $accountSetId)
            ->where('record_year', $year)
            ->where('record_month', $monthNum)
            ->with(['employee', 'project']);

        // 地区筛选 - 通过员工关联的地区进行筛选
        if ($regionName && $regionName !== '全部') {
            $query->whereHas('employee.socialSecurityRegion', function($q) use ($regionName) {
                $q->where('name', $regionName);
            })->orWhereHas('employee.medicalInsuranceRegion', function($q) use ($regionName) {
                $q->where('name', $regionName);
            })->orWhereHas('employee.housingFundRegion', function($q) use ($regionName) {
                $q->where('region_name', $regionName);
            });
        }

        $records = $query->orderBy('generated_at', 'desc')->get();

        // 将归档记录转换为明细格式
        $details = [];
        foreach ($records as $record) {
            $detail = $this->convertRecordToDetail($record);
            if ($detail) {
                $details[] = $detail;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $details,
            'source' => 'historical_archive', // 标识数据来源
            'archive_month' => $month
        ]);
    }

    /**
     * 将归档记录转换为明细格式
     */
    private function convertRecordToDetail($record)
    {
        if (!$record->employee) {
            return null;
        }

        // 解析快照数据
        $socialSecurityTypes = json_decode($record->social_security_types, true) ?: [];
        $medicalInsuranceTypes = json_decode($record->medical_insurance_types, true) ?: [];
        $housingFundParams = json_decode($record->housing_fund_params, true) ?: [];
        $otherInsurancePolicies = json_decode($record->other_insurance_policies, true) ?: [];
        $largeMedicalConfig = json_decode($record->large_medical_insurance_config, true) ?: [];

        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee_name' => $record->employee_name,
            'employee_id_number' => $record->employee_id_number,
            'employee_gender' => $record->employee_gender,
            'employee_birth_date' => $record->employee_birth_date,
            'employee_phone' => $record->employee_phone,
            'project_id' => $record->project_id,
            'project_name' => $record->project_name,
            'employee_social_security_base' => $record->employee_social_security_base,
            'employee_medical_insurance_base' => $record->employee_medical_insurance_base,
            'employee_housing_fund_base' => $record->employee_housing_fund_base,
            'employee_large_medical_base' => $record->employee_large_medical_base,
            
            // ⚠️ 重要：保持与实时数据相同的结构
            // 前端需要从 insurance_personnel 对象中读取这些字段
            'insurance_personnel' => [
                'social_security_types' => $record->social_security_types, // JSON字符串
                'medical_insurance_types' => $record->medical_insurance_types, // JSON字符串
                'housing_fund_params' => $record->housing_fund_params, // JSON字符串
                'large_medical_insurance_config' => $record->large_medical_insurance_config, // JSON字符串
            ],
            
            // 其他保险明细（已解析的数组）
            'other_insurance_policies' => $record->other_insurance_policies,
            
            // 金额字段
            'social_security_company_amount' => $record->social_security_company_amount,
            'social_security_employee_amount' => $record->social_security_employee_amount,
            'medical_insurance_company_amount' => $record->medical_insurance_company_amount,
            'medical_insurance_employee_amount' => $record->medical_insurance_employee_amount,
            'housing_fund_company_amount' => $record->housing_fund_company_amount,
            'housing_fund_employee_amount' => $record->housing_fund_employee_amount,
            'large_medical_company_amount' => $record->large_medical_company_amount,
            'large_medical_employee_amount' => $record->large_medical_employee_amount,
            'other_insurance_total_amount' => $record->other_insurance_total_amount,
            
            // 补充字段，保持与实时数据一致
            'created_at' => $record->generated_at, // 使用生成时间作为创建时间
            'last_updated_at' => $record->generated_at,
            'employee_type' => '正常', // 归档数据默认为正常类型
            'payment_period' => null, // 可以根据 record_year 和 record_month 计算
            
            // 关联的 employee 和 project 对象
            'employee' => $record->employee,
            'project' => $record->project,
            
            // 标识数据来源
            'source' => 'archive',
            'record_year' => $record->record_year,
            'record_month' => $record->record_month,
        ];
    }

    /**
     * 生成参保人员明细数据
     */
    private function generatePersonnelDetail($personnel)
    {
        if (!$personnel->employee) {
            return null;
        }

        // 从快照数据中解析保险配置
        $socialSecurityTypes = [];
        $medicalInsuranceTypes = [];
        $housingFundConfig = [];
        $largeMedicalConfig = [];

        if ($personnel->social_security_types) {
            try {
                $socialSecurityTypes = json_decode($personnel->social_security_types, true) ?: [];
            } catch (e) {
                $socialSecurityTypes = [];
            }
        }

        if ($personnel->medical_insurance_types) {
            try {
                $medicalInsuranceTypes = json_decode($personnel->medical_insurance_types, true) ?: [];
            } catch (e) {
                $medicalInsuranceTypes = [];
            }
        }

        if ($personnel->housing_fund_params) {
            try {
                $housingFundConfig = json_decode($personnel->housing_fund_params, true) ?: [];
            } catch (e) {
                $housingFundConfig = [];
            }
        }

        // 加载公积金配置模型（用于读取上下限）
        $housingFundConfigModel = null;
        if ($personnel->housing_fund_config_id) {
            $housingFundConfigModel = \App\Models\HousingFundConfig::find($personnel->housing_fund_config_id);
        }

        if ($personnel->large_medical_insurance_config) {
            try {
                $largeMedicalConfig = json_decode($personnel->large_medical_insurance_config, true) ?: [];
            } catch (e) {
                $largeMedicalConfig = [];
            }
        }

        // 使用参保人员记录中的基数值（原始基数）
        $originalMedicalBase = $personnel->employee_medical_insurance_base ?? 0;
        $originalPensionBase = $personnel->employee_social_security_base ?? 0;
        $originalHousingFundBase = $personnel->employee_housing_fund_base ?? 0;
        $originalLargeMedicalBase = $personnel->employee_large_medical_base ?? 0;

        // 获取上下限并应用约束
        // 1. 社保基数上下限约束
        $socialRegion = null;
        if ($personnel->social_security_region_id) {
            $socialRegion = \App\Models\SocialSecurityRegion::find($personnel->social_security_region_id);
        }
        
        \Log::info('社保基数约束', [
            '原始基数' => $originalPensionBase,
            '地区ID' => $personnel->social_security_region_id,
            '下限' => $socialRegion ? $socialRegion->min_base_amount : null,
            '上限' => $socialRegion ? $socialRegion->max_base_amount : null,
        ]);
        
        $pensionBase = $this->applyBaseLimits(
            $originalPensionBase,
            $socialRegion ? $socialRegion->min_base_amount : null,
            $socialRegion ? $socialRegion->max_base_amount : null
        );
        
        \Log::info('社保基数约束后', [
            '约束后基数' => $pensionBase,
        ]);

        // 2. 医保基数上下限约束
        $medicalRegion = null;
        if ($personnel->medical_insurance_region_id) {
            $medicalRegion = \App\Models\MedicalInsuranceRegion::find($personnel->medical_insurance_region_id);
        }
        $medicalBase = $this->applyBaseLimits(
            $originalMedicalBase,
            $medicalRegion ? $medicalRegion->min_base_amount : null,
            $medicalRegion ? $medicalRegion->max_base_amount : null
        );

        // 3. 公积金基数上下限约束（从公积金配置表读取）
        $housingFundBase = $originalHousingFundBase;
        if ($housingFundConfigModel) {
            $housingFundBase = $this->applyBaseLimits(
                $originalHousingFundBase,
                $housingFundConfigModel->min_base_amount,
                $housingFundConfigModel->max_base_amount
            );
        }

        // 4. 大额医疗基数：直接使用原始基数，不需要上下限约束
        $largeMedicalBase = $originalLargeMedicalBase;

        // 计算各项金额
        $medicalCompanyAmount = 0;
        $medicalEmployeeAmount = 0;
        $socialCompanyAmount = 0;
        $socialEmployeeAmount = 0;
        $housingFundCompanyAmount = 0;
        $housingFundEmployeeAmount = 0;
        $largeMedicalCompanyAmount = 0;
        $largeMedicalEmployeeAmount = 0;

        // 计算医保金额
        if (is_array($medicalInsuranceTypes)) {
            foreach ($medicalInsuranceTypes as $type) {
                $baseAmount = $medicalBase * (floatval($type['company_ratio'] ?? 0));
                $medicalCompanyAmount += $baseAmount;
                
                $baseAmount = $medicalBase * (floatval($type['employee_ratio'] ?? 0));
                $medicalEmployeeAmount += $baseAmount;
            }
        }

        // 计算社保金额
        if (is_array($socialSecurityTypes)) {
            foreach ($socialSecurityTypes as $type) {
                $baseAmount = $pensionBase * (floatval($type['company_ratio'] ?? 0));
                $socialCompanyAmount += $baseAmount;
                
                $baseAmount = $pensionBase * (floatval($type['employee_ratio'] ?? 0));
                $socialEmployeeAmount += $baseAmount;
            }
        }

        // 计算公积金金额
        if (is_array($housingFundConfig) && !empty($housingFundConfig)) {
            $housingFundCompanyAmount = $housingFundBase * (floatval($housingFundConfig['company_ratio'] ?? 0));
            $housingFundEmployeeAmount = $housingFundBase * (floatval($housingFundConfig['employee_ratio'] ?? 0));
        }

        // 计算大额医疗金额
        $largeMedicalCompanyBase = $personnel->employee_large_medical_company_base ?? $largeMedicalBase;
        if ($personnel->large_medical_insurance_enabled && is_array($largeMedicalConfig) && !empty($largeMedicalConfig)) {
            // 检查大额医疗保险的付款周期
            $paymentCycle = $largeMedicalConfig['payment_cycle'] ?? 'month';
            $calculationType = $largeMedicalConfig['calculation_type'] ?? 'base';
            
            // 判断是否为支付月份
            $isPaymentMonth = true;
            if ($paymentCycle === 'year' || $paymentCycle === 'yearly') {
                // 按年付款：需要检查是否为支付月份
                $currentMonth = (int) date('n');
                $currentYear = (int) date('Y');
                
                if ($personnel->large_medical_payment_start_month && $personnel->large_medical_payment_start_year) {
                    // 检查月份是否匹配
                    if ($currentMonth != $personnel->large_medical_payment_start_month) {
                        $isPaymentMonth = false;
                    }
                    // 检查年份是否有效
                    if ($currentYear < $personnel->large_medical_payment_start_year) {
                        $isPaymentMonth = false;
                    }
                }
                // 如果没有设置支付起始月份，默认当前月份为支付月份（首次入职）
            }
            
            if ($isPaymentMonth) {
                // 是支付月份，计算金额
                if ($calculationType === 'base') {
                    // 按基数模式：公司用公司基数，个人用个人基数
                    $largeMedicalCompanyAmount = $largeMedicalCompanyBase * (floatval($largeMedicalConfig['company_ratio'] ?? 0));
                    $largeMedicalEmployeeAmount = $largeMedicalBase * (floatval($largeMedicalConfig['employee_ratio'] ?? 0));
                } else {
                    // 固定金额模式
                    $largeMedicalCompanyAmount = floatval($largeMedicalConfig['company_amount'] ?? 0);
                    $largeMedicalEmployeeAmount = floatval($largeMedicalConfig['employee_amount'] ?? 0);
                }
            } else {
                // 不是支付月份，金额为0
                $largeMedicalCompanyAmount = 0;
                $largeMedicalEmployeeAmount = 0;
            }
        }

        // 正常数据：费款所属期 = 当前月份
        $employeeType = '正常';
        $paymentPeriod = date('Y-m'); // 当前月份，格式：YYYY-MM
        
        // ✅ 过滤其他保险：只保留在当前月份有效期内的保单
        $filteredOtherInsurancePolicies = $this->filterOtherInsurancePoliciesByDate(
            $personnel->other_insurance_policies,
            date('Y'),
            date('n')
        );

        // 构建明细数据
        $employee = $personnel->employee;
        $detail = [
            'id' => $personnel->id,
            'employee_id' => $personnel->employee_id,
            'project_id' => $personnel->project_id,
            'account_set_id' => $personnel->account_set_id,
            'employee_name' => $personnel->employee_name,
            'employee_id_number' => $personnel->employee_id_number,
            'employee_gender' => $personnel->employee_gender,
            'employee_birth_date' => $personnel->employee_birth_date,
            'employee_phone' => $personnel->employee_phone,
            'employee_status' => $personnel->employee_status,
            'employee_type' => $employeeType, // 添加员工类型
            'project_id' => $personnel->project_id,
            'project_name' => $personnel->project ? $personnel->project->name : '',
            'other_insurance_policies' => $filteredOtherInsurancePolicies,
            'payment_period' => $paymentPeriod, // 费款所属期
            'social_security_code' => $personnel->social_security_code, // 社保编号
            'medical_insurance_code' => $personnel->medical_insurance_code, // 医保编号
            'housing_fund_account_number' => $personnel->housing_fund_account_number, // 公积金账号
            // 各项参保日期（从员工档案中获取）
            'social_insurance_enrollment_date' => $employee ? $employee->social_insurance_enrollment_date : null,
            'medical_insurance_enrollment_date' => $employee ? $employee->medical_insurance_enrollment_date : null,
            'provident_fund_enrollment_date' => $employee ? $employee->provident_fund_enrollment_date : null,
            'large_medical_enrollment_date' => $employee ? $employee->large_medical_enrollment_date : null,
            'employee_medical_insurance_base' => $medicalBase,
            'employee_social_security_base' => $pensionBase,
            'employee_housing_fund_base' => $housingFundBase,
            'employee_large_medical_base' => $largeMedicalBase,
            'employee_large_medical_company_base' => $largeMedicalCompanyBase,
            'medical_insurance_company_amount' => $medicalCompanyAmount,
            'medical_insurance_employee_amount' => $medicalEmployeeAmount,
            'social_security_company_amount' => $socialCompanyAmount,
            'social_security_employee_amount' => $socialEmployeeAmount,
            'housing_fund_company_amount' => $housingFundCompanyAmount,
            'housing_fund_employee_amount' => $housingFundEmployeeAmount,
            'large_medical_company_amount' => $largeMedicalCompanyAmount,
            'large_medical_employee_amount' => $largeMedicalEmployeeAmount,
            'company_total' => $medicalCompanyAmount + $socialCompanyAmount + $housingFundCompanyAmount + $largeMedicalCompanyAmount,
            'employee_total' => $medicalEmployeeAmount + $socialEmployeeAmount + $housingFundEmployeeAmount + $largeMedicalEmployeeAmount,
            'social_security_total' => $socialCompanyAmount + $socialEmployeeAmount,
            'insurance_personnel' => [
                'social_security_types' => $personnel->social_security_types,
                'medical_insurance_types' => $personnel->medical_insurance_types,
                'housing_fund_params' => $personnel->housing_fund_params,
                'large_medical_insurance_config' => $personnel->large_medical_insurance_config
            ],
            'created_at' => $personnel->created_at,
            'last_updated_at' => $personnel->last_updated_at,
            'employee' => $personnel->employee,
            'project' => $personnel->project
        ];

        return $detail;
    }

    /**
     * 生成参保人员明细数据（可指定费款所属期和员工类型）
     */
    private function generatePersonnelDetailWithPaymentPeriod($personnel, $paymentPeriod, $employeeType)
    {
        if (!$personnel->employee) {
            return null;
        }

        // 从快照数据中解析保险配置
        $socialSecurityTypes = [];
        $medicalInsuranceTypes = [];
        $housingFundConfig = [];
        $largeMedicalConfig = [];

        if ($personnel->social_security_types) {
            try {
                $socialSecurityTypes = json_decode($personnel->social_security_types, true) ?: [];
            } catch (e) {
                $socialSecurityTypes = [];
            }
        }

        if ($personnel->medical_insurance_types) {
            try {
                $medicalInsuranceTypes = json_decode($personnel->medical_insurance_types, true) ?: [];
            } catch (e) {
                $medicalInsuranceTypes = [];
            }
        }

        if ($personnel->housing_fund_params) {
            try {
                $housingFundConfig = json_decode($personnel->housing_fund_params, true) ?: [];
            } catch (e) {
                $housingFundConfig = [];
            }
        }

        if ($personnel->large_medical_insurance_config) {
            try {
                $largeMedicalConfig = json_decode($personnel->large_medical_insurance_config, true) ?: [];
            } catch (e) {
                $largeMedicalConfig = [];
            }
        }

        // 使用参保人员记录中的基数值
        $medicalBase = $personnel->employee_medical_insurance_base ?? 0;
        $pensionBase = $personnel->employee_social_security_base ?? 0;
        $housingFundBase = $personnel->employee_housing_fund_base ?? 0;
        $largeMedicalBase = $personnel->employee_large_medical_base ?? 0;
        $largeMedicalCompanyBase = $personnel->employee_large_medical_company_base ?? $largeMedicalBase;

        // 计算各项金额
        $medicalCompanyAmount = 0;
        $medicalEmployeeAmount = 0;
        $socialCompanyAmount = 0;
        $socialEmployeeAmount = 0;
        $housingFundCompanyAmount = 0;
        $housingFundEmployeeAmount = 0;
        $largeMedicalCompanyAmount = 0;
        $largeMedicalEmployeeAmount = 0;

        // 医保费用计算
        foreach ($medicalInsuranceTypes as $type) {
            $baseAmount = $medicalBase;
            $medicalCompanyAmount += $baseAmount * (floatval($type['company_ratio'] ?? 0));
            $medicalEmployeeAmount += $baseAmount * (floatval($type['employee_ratio'] ?? 0));
        }

        // 社保费用计算
        foreach ($socialSecurityTypes as $type) {
            $baseAmount = $pensionBase;
            $socialCompanyAmount += $baseAmount * (floatval($type['company_ratio'] ?? 0));
            $socialEmployeeAmount += $baseAmount * (floatval($type['employee_ratio'] ?? 0));
        }

        // 计算公积金金额
        if (is_array($housingFundConfig) && !empty($housingFundConfig)) {
            $housingFundCompanyAmount = $housingFundBase * (floatval($housingFundConfig['company_ratio'] ?? 0));
            $housingFundEmployeeAmount = $housingFundBase * (floatval($housingFundConfig['employee_ratio'] ?? 0));
        }

        // 计算大额医疗金额
        if ($personnel->large_medical_insurance_enabled && is_array($largeMedicalConfig) && !empty($largeMedicalConfig)) {
            // 检查大额医疗保险的付款周期
            $paymentCycle = $largeMedicalConfig['payment_cycle'] ?? 'month';
            
            \Log::info('大额医疗计算调试', [
                'employee_name' => $personnel->employee_name,
                'employee_type' => $employeeType,
                'payment_period' => $paymentPeriod,
                'payment_cycle' => $paymentCycle,
                'large_medical_base' => $largeMedicalBase,
                'large_medical_config' => $largeMedicalConfig,
                'large_medical_insurance_enabled' => $personnel->large_medical_insurance_enabled,
                'is_array_config' => is_array($largeMedicalConfig),
                'config_not_empty' => !empty($largeMedicalConfig)
            ]);
            
            if ($paymentCycle === 'year') {
                // 按年付款：如果这个月有补交情况，只有补交的第一条数据有金额
                if ($employeeType === '补交') {
                    // 补交数据：检查是否是补交的第一条数据（入职月份）
                    $hireMonth = date('m', strtotime($personnel->created_at)); // 入职月份
                    $currentMonth = date('m', strtotime($paymentPeriod . '01')); // 费款所属期月份
                    
                    if ($hireMonth == $currentMonth) {
                        // 入职月份：正常计算大额医疗金额
                        if ($largeMedicalConfig['calculation_type'] === 'base') {
                            // 公司用公司基数，个人用个人基数
                            $largeMedicalCompanyAmount = $largeMedicalCompanyBase * (floatval($largeMedicalConfig['company_ratio'] ?? 0));
                            $largeMedicalEmployeeAmount = $largeMedicalBase * (floatval($largeMedicalConfig['employee_ratio'] ?? 0));
                        } else {
                            $largeMedicalCompanyAmount = floatval($largeMedicalConfig['company_amount'] ?? 0);
                            $largeMedicalEmployeeAmount = floatval($largeMedicalConfig['employee_amount'] ?? 0);
                        }
                    } else {
                        // 非入职月份：大额医疗金额为0
                        $largeMedicalCompanyAmount = 0;
                        $largeMedicalEmployeeAmount = 0;
                    }
                } else {
                    // 正常数据：按年付款时正常数据的大额为0
                    $largeMedicalCompanyAmount = 0;
                    $largeMedicalEmployeeAmount = 0;
                }
            } else {
                // 按月付款：所有月份（正常和补交）都有金额
                if ($largeMedicalConfig['calculation_type'] === 'base') {
                    // 公司用公司基数，个人用个人基数
                    $largeMedicalCompanyAmount = $largeMedicalCompanyBase * (floatval($largeMedicalConfig['company_ratio'] ?? 0));
                    $largeMedicalEmployeeAmount = $largeMedicalBase * (floatval($largeMedicalConfig['employee_ratio'] ?? 0));
                } else {
                    $largeMedicalCompanyAmount = floatval($largeMedicalConfig['company_amount'] ?? 0);
                    $largeMedicalEmployeeAmount = floatval($largeMedicalConfig['employee_amount'] ?? 0);
                }
            }
        }
        
        // ✅ 过滤其他保险：根据费款所属期过滤保单
        // 解析 YYYY-MM 格式的 paymentPeriod
        $periodParts = explode('-', $paymentPeriod);
        $year = $periodParts[0];
        $month = (int)$periodParts[1];
        $filteredOtherInsurancePolicies = $this->filterOtherInsurancePoliciesByDate(
            $personnel->other_insurance_policies,
            $year,
            $month
        );

        // 构建明细数据
        $detail = [
            'id' => $personnel->id,
            'employee_id' => $personnel->employee_id,
            'project_id' => $personnel->project_id,
            'account_set_id' => $personnel->account_set_id,
            'employee_name' => $personnel->employee_name,
            'employee_id_number' => $personnel->employee_id_number,
            'employee_gender' => $personnel->employee_gender,
            'employee_birth_date' => $personnel->employee_birth_date,
            'employee_phone' => $personnel->employee_phone,
            'employee_status' => $personnel->employee_status,
            'employee_type' => $employeeType,
            'project_name' => $personnel->project ? $personnel->project->name : '',
            'other_insurance_policies' => $filteredOtherInsurancePolicies,
            'payment_period' => $paymentPeriod,
            'social_security_code' => $personnel->social_security_code, // 社保编号
            'medical_insurance_code' => $personnel->medical_insurance_code, // 医保编号
            'housing_fund_account_number' => $personnel->housing_fund_account_number, // 公积金账号
            'employee_medical_insurance_base' => $medicalBase,
            'employee_social_security_base' => $pensionBase,
            'employee_housing_fund_base' => $housingFundBase,
            'employee_large_medical_base' => $largeMedicalBase,
            'employee_large_medical_company_base' => $largeMedicalCompanyBase,
            'medical_insurance_company_amount' => $medicalCompanyAmount,
            'medical_insurance_employee_amount' => $medicalEmployeeAmount,
            'social_security_company_amount' => $socialCompanyAmount,
            'social_security_employee_amount' => $socialEmployeeAmount,
            'housing_fund_company_amount' => $housingFundCompanyAmount,
            'housing_fund_employee_amount' => $housingFundEmployeeAmount,
            'large_medical_company_amount' => $largeMedicalCompanyAmount,
            'large_medical_employee_amount' => $largeMedicalEmployeeAmount,
            'company_total' => $medicalCompanyAmount + $socialCompanyAmount + $housingFundCompanyAmount + $largeMedicalCompanyAmount,
            'employee_total' => $medicalEmployeeAmount + $socialEmployeeAmount + $housingFundEmployeeAmount + $largeMedicalEmployeeAmount,
            'social_security_total' => $socialCompanyAmount + $socialEmployeeAmount,
            'insurance_personnel' => [
                'social_security_types' => $personnel->social_security_types,
                'medical_insurance_types' => $personnel->medical_insurance_types,
                'housing_fund_params' => $personnel->housing_fund_params,
                'large_medical_insurance_config' => $personnel->large_medical_insurance_config
            ],
            'created_at' => $personnel->created_at,
            'last_updated_at' => $personnel->last_updated_at,
            'employee' => $personnel->employee,
            'project' => $personnel->project
        ];

        return $detail;
    }

    /**
     * 生成补交明细数据
     * 
     * 补交逻辑：
     * 1. 只有当"当前月份 == 首次确认月份"时才显示补交数据
     * 2. 这样可以避免每次查询都重复显示补交数据
     * 3. 补交数据只在员工首次入职确认的那个月显示一次
     */
    private function generateSupplementaryDetails($personnel)
    {
        $supplementaryDetails = [];
        
        $employee = $personnel->employee;
        if (!$employee) {
            return $supplementaryDetails;
        }
        
        // 使用参保人员记录的创建时间作为首次确认日期
        $firstConfirmationDate = $personnel->created_at;
        if (!$firstConfirmationDate) {
            return $supplementaryDetails;
        }
        
        $confirmationMonth = date('Y-m', strtotime($firstConfirmationDate));
        $currentMonth = date('Y-m'); // 当前月份
        
        // 关键检查：只有当"当前月份 == 首次确认月份"时才显示补交数据
        // 这样可以避免每次查询都重复显示补交数据
        if ($currentMonth !== $confirmationMonth) {
            // 不是首次确认的月份，不显示补交数据
            return $supplementaryDetails;
        }
        
        // 获取各项保险的参保日期（如果没有填写，则不需要补交该项）
        $socialInsuranceEnrollmentDate = $employee->social_insurance_enrollment_date;
        $medicalInsuranceEnrollmentDate = $employee->medical_insurance_enrollment_date;
        $providentFundEnrollmentDate = $employee->provident_fund_enrollment_date;
        $largeMedicalEnrollmentDate = $employee->large_medical_enrollment_date;
        
        // 收集需要补交的参保日期（只有填写了参保日期且参保日期 < 确认月份的才需要补交）
        $enrollmentDates = [];
        
        if ($socialInsuranceEnrollmentDate && date('Y-m', strtotime($socialInsuranceEnrollmentDate)) < $confirmationMonth) {
            $enrollmentDates[] = $socialInsuranceEnrollmentDate;
        }
        if ($medicalInsuranceEnrollmentDate && date('Y-m', strtotime($medicalInsuranceEnrollmentDate)) < $confirmationMonth) {
            $enrollmentDates[] = $medicalInsuranceEnrollmentDate;
        }
        if ($providentFundEnrollmentDate && date('Y-m', strtotime($providentFundEnrollmentDate)) < $confirmationMonth) {
            $enrollmentDates[] = $providentFundEnrollmentDate;
        }
        // 大额医疗只有在启用时才考虑
        if ($personnel->large_medical_insurance_enabled && $largeMedicalEnrollmentDate && date('Y-m', strtotime($largeMedicalEnrollmentDate)) < $confirmationMonth) {
            $enrollmentDates[] = $largeMedicalEnrollmentDate;
        }
        
        // 如果没有任何需要补交的项目，直接返回
        if (empty($enrollmentDates)) {
            return $supplementaryDetails;
        }
        
        // 找出最早的参保日期作为补交的起始日期
        $earliestEnrollmentDate = min($enrollmentDates);
        $earliestEnrollmentMonth = date('Y-m', strtotime($earliestEnrollmentDate));
        
        // 生成补交数据（从最早参保月份到确认月份的前一个月）
        $current = $earliestEnrollmentDate;
        $endDate = date('Y-m-01', strtotime($confirmationMonth . '-01 -1 month')); // 确认月份的前一个月
        
        while (date('Y-m', strtotime($current)) <= date('Y-m', strtotime($endDate))) {
            $currentPaymentPeriod = date('Y-m', strtotime($current));
            
            // 为补交数据生成明细，传入正确的费款所属期和各项参保日期
            $supplementaryDetail = $this->generateSupplementaryDetailWithEnrollmentDates(
                $personnel, 
                $currentPaymentPeriod, 
                '补交',
                $socialInsuranceEnrollmentDate,
                $medicalInsuranceEnrollmentDate,
                $providentFundEnrollmentDate,
                $largeMedicalEnrollmentDate
            );
            if ($supplementaryDetail) {
                $supplementaryDetails[] = $supplementaryDetail;
            }
            
            // 移动到下一个月
            $current = date('Y-m-01', strtotime($current . ' +1 month'));
        }
        
        return $supplementaryDetails;
    }
    
    /**
     * 生成补交明细数据（根据各项参保日期判断是否需要补交）
     */
    private function generateSupplementaryDetailWithEnrollmentDates($personnel, $paymentPeriod, $employeeType, $socialEnrollmentDate, $medicalEnrollmentDate, $providentFundEnrollmentDate, $largeMedicalEnrollmentDate)
    {
        if (!$personnel->employee) {
            return null;
        }

        // 判断当前费款所属期是否需要补交各项保险
        $socialEnrollmentMonth = $socialEnrollmentDate ? date('Y-m', strtotime($socialEnrollmentDate)) : null;
        $medicalEnrollmentMonth = $medicalEnrollmentDate ? date('Y-m', strtotime($medicalEnrollmentDate)) : null;
        $providentFundEnrollmentMonth = $providentFundEnrollmentDate ? date('Y-m', strtotime($providentFundEnrollmentDate)) : null;
        $largeMedicalEnrollmentMonth = $largeMedicalEnrollmentDate ? date('Y-m', strtotime($largeMedicalEnrollmentDate)) : null;
        
        // 判断各项保险是否需要在当前费款所属期补交
        $needSocialSecurity = $socialEnrollmentMonth && $paymentPeriod >= $socialEnrollmentMonth;
        $needMedicalInsurance = $medicalEnrollmentMonth && $paymentPeriod >= $medicalEnrollmentMonth;
        $needProvidentFund = $providentFundEnrollmentMonth && $paymentPeriod >= $providentFundEnrollmentMonth;
        $needLargeMedical = $personnel->large_medical_insurance_enabled && $largeMedicalEnrollmentMonth && $paymentPeriod >= $largeMedicalEnrollmentMonth;
        
        // 如果所有保险都不需要补交，返回null
        if (!$needSocialSecurity && !$needMedicalInsurance && !$needProvidentFund && !$needLargeMedical) {
            return null;
        }

        // 从快照数据中解析保险配置
        $socialSecurityTypes = [];
        $medicalInsuranceTypes = [];
        $housingFundConfig = [];
        $largeMedicalConfig = [];

        if ($personnel->social_security_types) {
            try {
                $socialSecurityTypes = json_decode($personnel->social_security_types, true) ?: [];
            } catch (\Exception $e) {
                $socialSecurityTypes = [];
            }
        }

        if ($personnel->medical_insurance_types) {
            try {
                $medicalInsuranceTypes = json_decode($personnel->medical_insurance_types, true) ?: [];
            } catch (\Exception $e) {
                $medicalInsuranceTypes = [];
            }
        }

        if ($personnel->housing_fund_params) {
            try {
                $housingFundConfig = json_decode($personnel->housing_fund_params, true) ?: [];
            } catch (\Exception $e) {
                $housingFundConfig = [];
            }
        }

        if ($personnel->large_medical_insurance_config) {
            try {
                $largeMedicalConfig = json_decode($personnel->large_medical_insurance_config, true) ?: [];
            } catch (\Exception $e) {
                $largeMedicalConfig = [];
            }
        }

        // 使用参保人员记录中的基数值
        $medicalBase = $personnel->employee_medical_insurance_base ?? 0;
        $pensionBase = $personnel->employee_social_security_base ?? 0;
        $housingFundBase = $personnel->employee_housing_fund_base ?? 0;
        $largeMedicalBase = $personnel->employee_large_medical_base ?? 0;
        $largeMedicalCompanyBase = $personnel->employee_large_medical_company_base ?? $largeMedicalBase;

        // 计算各项金额（根据是否需要补交来决定是否计算）
        $medicalCompanyAmount = 0;
        $medicalEmployeeAmount = 0;
        $socialCompanyAmount = 0;
        $socialEmployeeAmount = 0;
        $housingFundCompanyAmount = 0;
        $housingFundEmployeeAmount = 0;
        $largeMedicalCompanyAmount = 0;
        $largeMedicalEmployeeAmount = 0;

        // 医保费用计算（只有需要补交时才计算）
        if ($needMedicalInsurance) {
            foreach ($medicalInsuranceTypes as $type) {
                $baseAmount = $medicalBase;
                $medicalCompanyAmount += $baseAmount * (floatval($type['company_ratio'] ?? 0));
                $medicalEmployeeAmount += $baseAmount * (floatval($type['employee_ratio'] ?? 0));
            }
        }

        // 社保费用计算（只有需要补交时才计算）
        if ($needSocialSecurity) {
            foreach ($socialSecurityTypes as $type) {
                $baseAmount = $pensionBase;
                $socialCompanyAmount += $baseAmount * (floatval($type['company_ratio'] ?? 0));
                $socialEmployeeAmount += $baseAmount * (floatval($type['employee_ratio'] ?? 0));
            }
        }

        // 计算公积金金额（只有需要补交时才计算）
        if ($needProvidentFund && is_array($housingFundConfig) && !empty($housingFundConfig)) {
            $housingFundCompanyAmount = $housingFundBase * (floatval($housingFundConfig['company_ratio'] ?? 0));
            $housingFundEmployeeAmount = $housingFundBase * (floatval($housingFundConfig['employee_ratio'] ?? 0));
        }

        // 计算大额医疗金额（只有需要补交时才计算）
        if ($needLargeMedical && is_array($largeMedicalConfig) && !empty($largeMedicalConfig)) {
            $paymentCycle = $largeMedicalConfig['payment_cycle'] ?? 'month';
            
            if ($paymentCycle === 'year') {
                // 按年付款：只有参保月份才计算金额
                if ($paymentPeriod === $largeMedicalEnrollmentMonth) {
                    if (($largeMedicalConfig['calculation_type'] ?? '') === 'base') {
                        $largeMedicalCompanyAmount = $largeMedicalCompanyBase * (floatval($largeMedicalConfig['company_ratio'] ?? 0));
                        $largeMedicalEmployeeAmount = $largeMedicalBase * (floatval($largeMedicalConfig['employee_ratio'] ?? 0));
                    } else {
                        $largeMedicalCompanyAmount = floatval($largeMedicalConfig['company_amount'] ?? 0);
                        $largeMedicalEmployeeAmount = floatval($largeMedicalConfig['employee_amount'] ?? 0);
                    }
                }
            } else {
                // 按月付款
                if (($largeMedicalConfig['calculation_type'] ?? '') === 'base') {
                    $largeMedicalCompanyAmount = $largeMedicalCompanyBase * (floatval($largeMedicalConfig['company_ratio'] ?? 0));
                    $largeMedicalEmployeeAmount = $largeMedicalBase * (floatval($largeMedicalConfig['employee_ratio'] ?? 0));
                } else {
                    $largeMedicalCompanyAmount = floatval($largeMedicalConfig['company_amount'] ?? 0);
                    $largeMedicalEmployeeAmount = floatval($largeMedicalConfig['employee_amount'] ?? 0);
                }
            }
        }

        // 构建明细数据
        $detail = [
            'id' => $personnel->id . '_' . str_replace('-', '', $paymentPeriod), // 生成唯一ID
            'employee_id' => $personnel->employee_id,
            'project_id' => $personnel->project_id,
            'account_set_id' => $personnel->account_set_id,
            'employee_name' => $personnel->employee_name,
            'employee_id_number' => $personnel->employee_id_number,
            'employee_gender' => $personnel->employee_gender,
            'employee_birth_date' => $personnel->employee_birth_date,
            'employee_phone' => $personnel->employee_phone,
            'employee_status' => $personnel->employee_status,
            'employee_type' => $employeeType,
            'project_name' => $personnel->project ? $personnel->project->name : '',
            'other_insurance_policies' => $personnel->other_insurance_policies,
            'payment_period' => $paymentPeriod,
            'social_security_code' => $personnel->social_security_code,
            'medical_insurance_code' => $personnel->medical_insurance_code,
            'housing_fund_account_number' => $personnel->housing_fund_account_number,
            'employee_medical_insurance_base' => $needMedicalInsurance ? $medicalBase : 0,
            'employee_social_security_base' => $needSocialSecurity ? $pensionBase : 0,
            'employee_housing_fund_base' => $needProvidentFund ? $housingFundBase : 0,
            'employee_large_medical_base' => $needLargeMedical ? $largeMedicalBase : 0,
            'employee_large_medical_company_base' => $needLargeMedical ? $largeMedicalCompanyBase : 0,
            'medical_insurance_company_amount' => $medicalCompanyAmount,
            'medical_insurance_employee_amount' => $medicalEmployeeAmount,
            'social_security_company_amount' => $socialCompanyAmount,
            'social_security_employee_amount' => $socialEmployeeAmount,
            'housing_fund_company_amount' => $housingFundCompanyAmount,
            'housing_fund_employee_amount' => $housingFundEmployeeAmount,
            'large_medical_company_amount' => $largeMedicalCompanyAmount,
            'large_medical_employee_amount' => $largeMedicalEmployeeAmount,
            'company_total' => $medicalCompanyAmount + $socialCompanyAmount + $housingFundCompanyAmount + $largeMedicalCompanyAmount,
            'employee_total' => $medicalEmployeeAmount + $socialEmployeeAmount + $housingFundEmployeeAmount + $largeMedicalEmployeeAmount,
            'social_security_total' => $socialCompanyAmount + $socialEmployeeAmount,
            'insurance_personnel' => [
                'social_security_types' => $personnel->social_security_types,
                'medical_insurance_types' => $personnel->medical_insurance_types,
                'housing_fund_params' => $personnel->housing_fund_params,
                'large_medical_insurance_config' => $personnel->large_medical_insurance_config
            ],
            'created_at' => $personnel->created_at,
            'last_updated_at' => $personnel->last_updated_at,
            'employee' => $personnel->employee,
            'project' => $personnel->project,
            // 补交标记：标识各项保险是否需要补交
            'need_social_security' => $needSocialSecurity,
            'need_medical_insurance' => $needMedicalInsurance,
            'need_provident_fund' => $needProvidentFund,
            'need_large_medical' => $needLargeMedical
        ];

        return $detail;
    }

    /**
     * 生成实时明细数据
     */
    private function generateRealTimeDetail($change)
    {
        if (!$change->employee) {
            return null;
        }

        // 获取实时保险配置
        $socialSecurityTypes = $change->getCurrentSocialSecurityConfig();
        $medicalInsuranceTypes = $change->getCurrentMedicalInsuranceConfig();
        $housingFundConfig = $change->getCurrentHousingFundConfig();
        $largeMedicalConfig = $change->getCurrentLargeMedicalInsuranceConfig();

        // 加载公积金配置模型（用于读取上下限）
        $housingFundConfigModel = null;
        $housingFundConfigId = $change->housing_fund_config_id ?: ($change->employee ? $change->employee->housing_fund_config_id : null);
        if ($housingFundConfigId) {
            $housingFundConfigModel = \App\Models\HousingFundConfig::find($housingFundConfigId);
        }

        // 使用员工档案中的基数值（原始基数）
        $originalMedicalBase = $change->employee_medical_insurance_base ?? 0;
        $originalPensionBase = $change->employee_social_security_base ?? 0;
        $originalHousingFundBase = $change->employee_housing_fund_base ?? 0;
        $originalLargeMedicalBase = $change->employee_large_medical_base ?? 0;
        $originalLargeMedicalCompanyBase = $change->employee_large_medical_company_base ?? $originalLargeMedicalBase;

        // 获取上下限并应用约束
        // 1. 社保基数上下限约束
        $socialRegion = null;
        if ($change->social_security_region_id) {
            $socialRegion = \App\Models\SocialSecurityRegion::find($change->social_security_region_id);
        }
        $pensionBase = $this->applyBaseLimits(
            $originalPensionBase,
            $socialRegion ? $socialRegion->min_base_amount : null,
            $socialRegion ? $socialRegion->max_base_amount : null
        );

        // 2. 医保基数上下限约束
        $medicalRegion = null;
        if ($change->medical_insurance_region_id) {
            $medicalRegion = \App\Models\MedicalInsuranceRegion::find($change->medical_insurance_region_id);
        }
        $medicalBase = $this->applyBaseLimits(
            $originalMedicalBase,
            $medicalRegion ? $medicalRegion->min_base_amount : null,
            $medicalRegion ? $medicalRegion->max_base_amount : null
        );

        // 3. 公积金基数上下限约束（从公积金配置表读取）
        $housingFundBase = $originalHousingFundBase;
        if ($housingFundConfigModel) {
            $housingFundBase = $this->applyBaseLimits(
                $originalHousingFundBase,
                $housingFundConfigModel->min_base_amount,
                $housingFundConfigModel->max_base_amount
            );
        }

        // 4. 大额医疗基数：如果按基数核算，复用社保上下限
        $largeMedicalBase = $originalLargeMedicalBase;
        $largeMedicalCompanyBase = $originalLargeMedicalCompanyBase;
        if ($change->large_medical_insurance_enabled && is_array($largeMedicalConfig) && !empty($largeMedicalConfig)) {
            if (($largeMedicalConfig['calculation_type'] ?? '') === 'base') {
                // 大额医疗按基数核算时，使用社保的上下限（大额没有上下限，直接使用原始值）
                $largeMedicalBase = $originalLargeMedicalBase;
                $largeMedicalCompanyBase = $originalLargeMedicalCompanyBase;
            }
        }

        // 构建保险人员快照数据
        $insurancePersonnel = [
            'social_security_types' => json_encode($socialSecurityTypes),
            'medical_insurance_types' => json_encode($medicalInsuranceTypes),
            'housing_fund_config' => json_encode($housingFundConfig),
            'large_medical_insurance_config' => json_encode($largeMedicalConfig)
        ];

        // 计算各项金额
        $medicalCompanyAmount = 0;
        $medicalEmployeeAmount = 0;
        $socialCompanyAmount = 0;
        $socialEmployeeAmount = 0;
        $largeMedicalCompanyAmount = 0;
        $largeMedicalEmployeeAmount = 0;

        // 社保费用计算
        foreach ($socialSecurityTypes as $type) {
            $baseAmount = $pensionBase;
            $socialCompanyAmount += $baseAmount * $type['company_ratio'];
            $socialEmployeeAmount += $baseAmount * $type['employee_ratio'];
        }

        // 医保费用计算
        foreach ($medicalInsuranceTypes as $type) {
            $baseAmount = $medicalBase;
            $medicalCompanyAmount += $baseAmount * $type['company_ratio'];
            $medicalEmployeeAmount += $baseAmount * $type['employee_ratio'];
        }

        // 大额医疗保险费用计算
        if ($largeMedicalConfig && $change->large_medical_insurance_enabled) {
            if ($largeMedicalConfig['calculation_type'] === 'base') {
                // 公司用公司基数，个人用个人基数
                $largeMedicalCompanyAmount = $largeMedicalCompanyBase * ($largeMedicalConfig['company_ratio'] ?? 0);
                $largeMedicalEmployeeAmount = $largeMedicalBase * ($largeMedicalConfig['employee_ratio'] ?? 0);
            } else {
                $largeMedicalCompanyAmount = $largeMedicalConfig['company_amount'] ?? 0;
                $largeMedicalEmployeeAmount = $largeMedicalConfig['employee_amount'] ?? 0;
            }
        }

        // 判断员工类型：根据入职日期和确认完成日期
        $employeeType = $this->determineEmployeeType($change);

        // 构建明细数据
        $detail = [
            'id' => $change->id,
            'employee_id' => $change->employee_id,
            'project_id' => $change->project_id,
            'account_set_id' => $change->account_set_id,
            'employee_name' => $change->employee_name,
            'employee_id_number' => $change->employee_id_number,
            'employee_gender' => $change->employee_gender,
            'employee_birth_date' => $change->employee_birth_date,
            'employee_phone' => $change->employee_phone,
            'employee_status' => $change->employee_status,
            'employee_type' => $employeeType, // 添加员工类型
            'project_name' => $change->project ? $change->project->name : '',
            'employee_medical_insurance_base' => $medicalBase,
            'employee_social_security_base' => $pensionBase,
            'employee_housing_fund_base' => $housingFundBase,
            'employee_large_medical_base' => $largeMedicalBase,
            'employee_large_medical_company_base' => $largeMedicalCompanyBase,
            'medical_insurance_company_amount' => $medicalCompanyAmount,
            'medical_insurance_employee_amount' => $medicalEmployeeAmount,
            'social_security_company_amount' => $socialCompanyAmount,
            'social_security_employee_amount' => $socialEmployeeAmount,
            'large_medical_company_amount' => $largeMedicalCompanyAmount,
            'large_medical_employee_amount' => $largeMedicalEmployeeAmount,
            'insurance_personnel' => $insurancePersonnel,
            'created_at' => $change->created_at,
            'employee' => $change->employee,
            'project' => $change->project
        ];

        return $detail;
    }

    /**
     * 判断参保人员员工类型：根据创建时间和最后更新时间
     */
    private function determinePersonnelEmployeeType($personnel)
    {
        // 获取创建时间（入职时间）
        $createdAt = $personnel->created_at;
        
        // 获取最后更新时间（确认完成时间）
        $lastUpdatedAt = $personnel->last_updated_at;
        
        if (!$createdAt || !$lastUpdatedAt) {
            // 如果没有最后更新时间，默认为正常
            return '正常';
        }
        
        // 解析创建月份
        $createdMonth = date('n', strtotime($createdAt)); // 获取月份（1-12）
        
        // 解析最后更新月份
        $updatedMonth = date('n', strtotime($lastUpdatedAt));
        
        // 如果最后更新时间在创建时间之后，则为补交
        if ($updatedMonth > $createdMonth) {
            return '补交';
        }
        
        return '正常';
    }

    /**
     * 判断员工类型：根据入职日期和确认完成日期
     */
    private function determineEmployeeType($change)
    {
        // 获取入职日期（创建时间）
        $createdAt = $change->created_at;
        
        // 获取确认完成日期
        $completedAt = $change->completed_at;
        
        if (!$createdAt || !$completedAt) {
            // 如果没有完成时间，默认为正常
            return '正常';
        }
        
        // 解析入职月份
        $createdMonth = date('n', strtotime($createdAt)); // 获取月份（1-12）
        
        // 解析完成确认处理月份
        $completedMonth = date('n', strtotime($completedAt));
        
        // 如果完成确认处理时间在入职时间之后，则为补交
        if ($completedMonth > $createdMonth) {
            return '补交';
        }
        
        return '正常';
    }

    /**
     * 获取汇总数据
     */
    public function getSummaries(Request $request)
    {
        $user = $request->user();
        $accountSetId = $request->input('account_set_id');
        $regionName = $request->input('region_name');

        // 检查账套权限
        if ($user && $user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $accountSetId)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        $query = InsuranceChangeSummary::where('account_set_id', $accountSetId);

        // 地区筛选
        if ($regionName && $regionName !== '全部') {
            $query->where('region_name', $regionName);
        }

        $summaries = $query->orderBy('region_name')->orderBy('insurance_type')->get();

        return response()->json([
            'success' => true,
            'data' => $summaries
        ]);
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request, $id)
    {
        // 调试日志
        \Log::info('上传附件请求（多文件）', [
            'id' => $id,
            'has_files' => $request->hasFile('attachments'),
            'files_count' => $request->hasFile('attachments') ? count($request->file('attachments')) : 0,
        ]);
        
        // 验证多文件上传
        $validator = Validator::make($request->all(), [
            'attachments' => 'required|array|max:10',
            'attachments.*' => [
                'required',
                'file',
                'max:10240', // 10MB
                function ($attribute, $value, $fail) {
                    // 允许的扩展名
                    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
                    $extension = strtolower($value->getClientOriginalExtension());
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('附件必须是以下格式之一：PDF、Word、Excel、图片');
                    }
                },
            ]
        ]);

        if ($validator->fails()) {
            \Log::error('附件验证失败', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $change = InsuranceChange::findOrFail($id);
        
        // 简化认证检查，只要能访问这个路由就可以上传
        // 因为路由已经有auth:sanctum中间件保护了

        // 上传所有文件到 public/insurance-attachments 目录
        $files = $request->file('attachments');
        $uploadedAttachments = [];
        $user = $request->user();
        
        foreach ($files as $file) {
            $path = $file->store('insurance-attachments', 'public');
            
            // 保存到附件表
            $attachment = \App\Models\InsuranceChangeAttachment::create([
                'insurance_change_id' => $id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getClientOriginalExtension(),
                'uploaded_by' => $user ? $user->id : null,
            ]);
            
            $uploadedAttachments[] = $attachment;
            
            \Log::info('文件上传成功', [
                'insurance_change_id' => $id,
                'attachment_id' => $attachment->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName()
            ]);
        }

        // 更新增减记录状态
        $change->update([
            'status' => 'submitted',
            'attachment_uploaded_at' => now(),
        ]);
        
        // 不再清除变更标记，保留变更记录以便后续查看
        // $change->clearChangeFlag();

        return response()->json([
            'success' => true,
            'message' => '成功上传 ' . count($uploadedAttachments) . ' 个文件',
            'data' => [
                'uploaded_files' => $uploadedAttachments,
                'change' => $change->fresh()->load('attachments')
            ]
        ]);
    }

    /**
     * 删除附件
     */
    public function deleteAttachment(Request $request, $attachmentId)
    {
        try {
            $attachment = \App\Models\InsuranceChangeAttachment::findOrFail($attachmentId);
            
            // 删除物理文件
            $filePath = storage_path('app/public/' . $attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // 删除数据库记录
            $attachment->delete();
            
            \Log::info('附件删除成功', [
                'attachment_id' => $attachmentId,
                'file_path' => $attachment->file_path
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '附件删除成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('删除附件失败', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '删除附件失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 处理参保信息
     */
    public function process(Request $request, $id)
    {
        $change = InsuranceChange::findOrFail($id);
        $user = $request->user();

        // 检查用户是否已认证
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        // 检查权限
        if ($user && $user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_set_id', $change->account_set_id)->exists();
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限操作'
                ], 403);
            }
        }

        DB::transaction(function () use ($change, $user) {
            // 更新状态
            $change->update([
                'status' => 'completed',
                'processed_by' => $user->id,
                'processed_at' => now(),
                'completed_at' => now()
            ]);

            // 生成参保明细
            $this->generateDetails($change);
        });

        return response()->json([
            'success' => true,
            'message' => '处理完成',
            'data' => $change->fresh()
        ]);
    }

    /**
     * 确认处理（从待处理状态更新为已处理并导入明细）
     */
    public function confirmProcess(Request $request, $id)
    {
        try {
            $change = InsuranceChange::findOrFail($id);
            $user = $request->user();

            // 完全移除权限检查 - 允许所有操作

            // 检查状态 - 允许待处理状态和已提交状态
            if (!in_array($change->status, ['pending', 'submitted'])) {
                return response()->json([
                    'success' => false,
                    'message' => '只有待处理或已提交状态的记录才能确认处理'
                ], 400);
            }

            // 检查是否有附件
            if (!$change->attachments || $change->attachments->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '请先上传附件'
                ], 400);
            }

            DB::transaction(function () use ($change, $user) {
                // 更新状态为已处理，并清空变更记录
                $updateData = [
                    'status' => 'completed',
                    'fully_confirmed' => 1,  // 标记为已完整确认处理
                    'processed_at' => now(),
                    'completed_at' => now(),
                    // 保留 change_details 和 change_summary，不清空，以便在列表和详情中继续显示变更标记
                ];
                
                // 只有当用户存在时才设置 processed_by
                if ($user && $user->id) {
                    $updateData['processed_by'] = $user->id;
                }
                
                $change->update($updateData);

                // 确认处理时更新快照，重置变化记录
                // 禁用保险配置变更检测
                // $change->checkAndRecordChanges(true);

                // 生成参保明细（基于新表生成明细记录）
                // 注意：必须先创建参保人员记录，再设置支付起始时间
                $this->generateOrUpdateDetails($change);
                
                // 设置大额医疗保险支付起始时间（如果是第一次开启）
                $this->setLargeMedicalPaymentStartTime($change);

                // ❌ 已移除：自动发起退保流程（现改为手动创建）
                // $this->autoCreateSurrenderRequestsFromChange($change, $user);
            });

            return response()->json([
                'success' => true,
                'message' => '处理完成，数据已导入参保明细',
                'data' => $change->fresh()
            ]);

        } catch (\Exception $e) {
            \Log::error('确认处理失败', [
                'change_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '处理失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * “减少参保”确认处理后，若包含其他保险保单，则自动创建退保流程单（待业务上传）
     *
     * 规则：
     * - 仅 change_type = decrease
     * - other_insurance_policies 中提取保单ID（字段可能为JSON字符串或数组）
     * - 每个保单生成一条退保单（如已存在未完成退保单，则跳过）
     */
    private function autoCreateSurrenderRequestsFromChange(InsuranceChange $change, $user = null): void
    {
        \Log::info('=== 开始检查是否需要创建退保记录 ===', [
            'change_id' => $change->id,
            'change_type' => $change->change_type,
            'employee_id' => $change->employee_id,
            'employee_name' => $change->employee_name
        ]);

        if ($change->change_type !== 'decrease') {
            \Log::info('不是减少类型，跳过', ['change_type' => $change->change_type]);
            return;
        }

        $raw = $change->other_insurance_policies;
        \Log::info('检查商业险数据', [
            'raw_type' => gettype($raw),
            'raw_value' => $raw
        ]);

        if (!$raw || $raw === '[]') {
            \Log::info('没有商业险数据，跳过');
            return;
        }

        $policies = $raw;
        if (is_string($policies)) {
            $policies = json_decode($policies, true) ?: [];
        }
        if (!is_array($policies) || empty($policies)) {
            \Log::info('商业险数据为空或格式错误', [
                'is_array' => is_array($policies),
                'empty' => empty($policies)
            ]);
            return;
        }

        \Log::info('解析到的商业险保单', ['policies' => $policies]);

        $policyIds = collect($policies)
            ->map(function ($p) {
                if (is_array($p)) return $p['id'] ?? null;
                if (is_object($p)) return $p->id ?? null;
                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        \Log::info('提取的保单ID列表', ['policy_ids' => $policyIds]);

        if (empty($policyIds)) {
            \Log::info('没有有效的保单ID，跳过');
            return;
        }

        foreach ($policyIds as $policyId) {
            \Log::info('处理保单', ['policy_id' => $policyId]);

            // 防御：保单不存在则跳过
            $policy = OtherInsurancePolicy::with('type')->find($policyId);
            if (!$policy) {
                \Log::warning('保单不存在，跳过', ['policy_id' => $policyId]);
                continue;
            }

            // 获取保险类型名称（通过关联关系）
            $insuranceTypeName = $policy->type ? $policy->type->name : null;

            \Log::info('保单信息', [
                'policy_id' => $policy->id,
                'policy_name' => $policy->policy_name,
                'type_id' => $policy->type_id,
                'insurance_type_name' => $insuranceTypeName
            ]);

            // ✅ 只为"商业险"类型的保单创建退保记录
            // 社保、公积金、大额医疗等其他险种不需要退保流程
            if ($insuranceTypeName !== '商业险') {
                \Log::info('不是商业险类型，跳过', [
                    'policy_id' => $policyId,
                    'insurance_type_name' => $insuranceTypeName
                ]);
                continue;
            }

            // 已存在未完成的退保单则不重复创建
            // ✅ 去重维度：同一账套 + 同一保单 + 同一员工（不同员工即使同一保单也要创建）
            $exists = InsuranceSurrenderRequest::where('account_set_id', $change->account_set_id)
                ->where('policy_id', $policyId)
                ->where('employee_id', $change->employee_id)
                ->whereIn('status', ['pending_business', 'business_done'])
                ->exists();
            if ($exists) {
                \Log::info('已存在未完成的退保记录，跳过', [
                    'policy_id' => $policyId,
                    'employee_id' => $change->employee_id
                ]);
                continue;
            }

            \Log::info('创建退保记录', [
                'account_set_id' => $change->account_set_id,
                'policy_id' => $policyId,
                'employee_id' => $change->employee_id,
                'project_id' => $change->project_id
            ]);

            InsuranceSurrenderRequest::create([
                'account_set_id' => $change->account_set_id,
                'policy_id' => $policyId,
                'employee_id' => $change->employee_id,
                'project_id' => $change->project_id,
                'insurance_change_id' => $change->id,
                'status' => 'pending_business',
                'initiated_by' => $user && $user->id ? $user->id : null,
            ]);

            \Log::info('✅ 退保记录创建成功', ['policy_id' => $policyId]);
        }

        \Log::info('=== 退保记录检查完成 ===');
    }

    /**
     * 其他保险确认处理（只处理其他保险明细）
     */
    public function confirmOtherInsuranceOnly(Request $request, $id)
    {
        try {
            $change = InsuranceChange::findOrFail($id);
            $user = $request->user();

            // 检查是否已完整确认处理
            if ($change->fully_confirmed) {
                return response()->json([
                    'success' => false,
                    'message' => '该记录已完整确认处理，无法再单独处理其他保险'
                ], 400);
            }

            // 检查状态
            if (!in_array($change->status, ['pending', 'submitted', 'completed'])) {
                return response()->json([
                    'success' => false,
                    'message' => '当前状态不允许处理其他保险'
                ], 400);
            }

            DB::transaction(function () use ($change, $user) {
                // 只同步其他保险数据到参保人员表
                $personnel = $this->syncOtherInsuranceOnly($change);
                
                // 基于参保人员信息生成当前月份的明细记录（只生成其他保险）
                $currentYear = date('Y');
                $currentMonth = date('n');
                
                InsuranceDetailRecord::generateFromPersonnel($personnel, $currentYear, $currentMonth);
                
                // 标记其他保险已处理
                $change->update(['other_insurance_processed' => 1]);
                
                \Log::info('其他保险明细处理成功', [
                    'insurance_change_id' => $change->id,
                    'employee_name' => $change->employee_name,
                    'processed_by' => $user ? $user->id : null
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => '其他保险已处理完成',
                'data' => $change->fresh()
            ]);

        } catch (\Exception $e) {
            \Log::error('其他保险确认处理失败', [
                'change_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '处理失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 只同步其他保险到参保人员表
     */
    private function syncOtherInsuranceOnly($change)
    {
        // 获取或创建参保人员记录
        $personnel = InsurancePersonnel::getOrCreateFromInsuranceChange($change);
        
        // 如果是减少记录，personnel 为 null，直接返回
        if (!$personnel) {
            \Log::info('减少记录已处理，无需同步其他保险', [
                'insurance_change_id' => $change->id,
                'employee_name' => $change->employee_name,
                'change_type' => $change->change_type
            ]);
            return;
        }
        
        // 只更新其他保险相关字段
        $otherInsurancePolicies = $change->other_insurance_policies;
        
        if ($otherInsurancePolicies && !empty($otherInsurancePolicies) && $otherInsurancePolicies !== '[]') {
            $personnel->other_insurance_policies = $otherInsurancePolicies;
            $personnel->save();
            
            \Log::info('其他保险数据已同步到参保人员表', [
                'insurance_personnel_id' => $personnel->id,
                'employee_name' => $change->employee_name,
                'other_insurance_policies' => $otherInsurancePolicies
            ]);
        }
        
        return $personnel;
    }

    /**
     * 更新其他保险费用
     */
    public function updateOtherInsuranceCost(Request $request, $id)
    {
        try {
            $change = InsuranceChange::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'insurance_id' => 'required|integer',
                'employee_per_capita_cost' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            \Log::info('更新其他保险费用', [
                'change_id' => $change->id,
                'insurance_id' => $request->insurance_id,
                'employee_per_capita_cost' => $request->employee_per_capita_cost,
                'other_insurance_policies' => $change->other_insurance_policies
            ]);

            // 禁用更新源数据功能
            // 直接更新保单表的费用（这是源数据）
            // $policy = \App\Models\OtherInsurancePolicy::find($request->insurance_id);
            
            // if (!$policy) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => '未找到指定的保险保单'
            //     ], 404);
            // }

            // // 更新保单的员工人均费用
            // $policy->update([
            //     'employee_per_capita_cost' => $request->employee_per_capita_cost
            // ]);

            // \Log::info('保单费用更新成功', [
            //     'policy_id' => $policy->id,
            //     'new_cost' => $request->employee_per_capita_cost
            // ]);

            return response()->json([
                'success' => true,
                'message' => '功能已禁用，数据不可修改',
                'data' => [
                    'change' => $change->fresh()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('更新其他保险费用失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新批单号
     */
    public function updateEndorsementNumber(Request $request, $id)
    {
        try {
            $change = InsuranceChange::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'insurance_id' => 'required|integer',
                'endorsement_number' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            \Log::info('更新批单号', [
                'change_id' => $change->id,
                'insurance_id' => $request->insurance_id,
                'endorsement_number' => $request->endorsement_number
            ]);

            // 直接更新保单表的批单号
            $policy = \App\Models\OtherInsurancePolicy::find($request->insurance_id);
            
            if (!$policy) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到指定的保险保单'
                ], 404);
            }

            // 更新保单的批单号
            $policy->update([
                'endorsement_number' => $request->endorsement_number
            ]);

            // 重新保存保险配置快照，以更新批单号
            $change->saveCompleteInsuranceConfig();

            \Log::info('保单批单号更新成功', [
                'policy_id' => $policy->id,
                'new_endorsement_number' => $request->endorsement_number
            ]);

            return response()->json([
                'success' => true,
                'message' => '批单号更新成功',
                'data' => [
                    'change' => $change->fresh()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('更新批单号失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新员工人均参保费用
     */
    public function updatePerCapitaCost(Request $request, $id)
    {
        try {
            $change = InsuranceChange::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'insurance_id' => 'required|integer',
                'employee_per_capita_cost' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            \Log::info('更新员工人均参保费用', [
                'change_id' => $change->id,
                'insurance_id' => $request->insurance_id,
                'employee_per_capita_cost' => $request->employee_per_capita_cost
            ]);

            // 更新增减记录中的其他保险配置
            $otherInsuranceConfig = $change->other_insurance_config;
            if (is_array($otherInsuranceConfig)) {
                foreach ($otherInsuranceConfig as &$insurance) {
                    if (isset($insurance['id']) && $insurance['id'] == $request->insurance_id) {
                        $insurance['employee_per_capita_cost'] = $request->employee_per_capita_cost;
                        break;
                    }
                }
                $change->other_insurance_config = $otherInsuranceConfig;
                $change->save();
            }

            \Log::info('员工人均参保费用更新成功', [
                'change_id' => $change->id,
                'insurance_id' => $request->insurance_id,
                'new_cost' => $request->employee_per_capita_cost
            ]);

            return response()->json([
                'success' => true,
                'message' => '费用更新成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('更新员工人均参保费用失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 使用名额
     */
    public function useQuota(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            
            $change = InsuranceChange::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'insurance_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 获取保单信息
            $policy = \App\Models\OtherInsurancePolicy::findOrFail($request->insurance_id);
            
            // 检查名额是否充足
            if (!$policy->quota || $policy->quota <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => '该保单已无可用名额'
                ], 422);
            }

            // 检查是否已经使用过该保单的名额
            $usedQuotas = $change->used_quotas ? json_decode($change->used_quotas, true) : [];
            if (in_array($request->insurance_id, $usedQuotas)) {
                return response()->json([
                    'success' => false,
                    'message' => '已使用过该保单的名额'
                ], 422);
            }

            // 减少保单名额
            $policy->decrement('quota', 1);
            
            // 记录已使用名额
            $usedQuotas[] = $request->insurance_id;
            $change->update([
                'used_quotas' => json_encode($usedQuotas)
            ]);

            DB::commit();

            \Log::info('使用名额成功', [
                'insurance_change_id' => $change->id,
                'insurance_id' => $request->insurance_id,
                'remaining_quota' => $policy->quota
            ]);

            return response()->json([
                'success' => true,
                'message' => '名额使用成功',
                'data' => [
                    'change' => $change->fresh(),
                    'remaining_quota' => $policy->quota
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('使用名额失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '使用名额失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 切换大额医疗保险开关
     */
    public function toggleLargeMedical(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $change = InsuranceChange::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'is_enabled' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 更新保险变更记录中的大额医疗保险开关状态
            $change->large_medical_insurance_enabled = $request->is_enabled;
            $change->save();

            DB::commit();

            \Log::info('切换大额医疗保险状态成功', [
                'insurance_change_id' => $change->id,
                'employee_id' => $change->employee_id,
                'is_enabled' => $request->is_enabled
            ]);

            return response()->json([
                'success' => true,
                'message' => $request->is_enabled ? '启用成功' : '停用成功',
                'data' => [
                    'change' => $change->fresh()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('切换大额医疗保险状态失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '操作失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 生成汇总表
     */
    public function generateSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|exists:account_sets,id',
            'region_name' => 'nullable|string',
            'summary_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $accountSetId = $request->input('account_set_id');
        $regionName = $request->input('region_name');
        $summaryDate = $request->input('summary_date');

        // 简化权限检查 - 只要用户存在就允许操作

        // 删除旧的汇总数据
        $query = InsuranceChangeSummary::where('account_set_id', $accountSetId);
        if ($regionName) {
            $query->where('region_name', $regionName);
        }
        $query->delete();

        // 生成新的汇总数据
        $summaries = InsuranceChangeSummary::generateSummary($accountSetId, $regionName, $summaryDate);

        return response()->json([
            'success' => true,
            'message' => '汇总表生成成功',
            'data' => $summaries
        ]);
    }

    /**
     * 导出汇总表
     */
    public function exportSummary(Request $request)
    {
        // 参保增减导出权限
        if ($response = $this->checkPermission('insurance_change.export')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|exists:account_sets,id',
            'region_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $accountSetId = $request->input('account_set_id');
        $regionName = $request->input('region_name');

        // 获取汇总数据
        $query = InsuranceChangeSummary::where('account_set_id', $accountSetId);
        if ($regionName && $regionName !== '全部') {
            $query->where('region_name', $regionName);
        }
        $summaries = $query->orderBy('region_name')->orderBy('insurance_type')->get();

        // 生成Excel文件
        $filename = '参保汇总表_' . ($regionName ?: '全部') . '_' . now()->format('Y-m-d') . '.xlsx';
        $filepath = storage_path('app/public/exports/' . $filename);

        // 确保目录存在
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        // 这里应该使用Excel导出库，暂时返回数据
        return response()->json([
            'success' => true,
            'message' => '导出成功',
            'data' => $summaries,
            'filename' => $filename
        ]);
    }

    /**
     * 自动导入员工保险信息
     */
    public function autoImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'project_id' => 'required|exists:projects,id',
            'account_set_id' => 'required|exists:account_sets,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = Employee::findOrFail($request->employee_id);
        $project = Project::findOrFail($request->project_id);

        // 检查是否已存在
        $existing = InsuranceChange::where('employee_id', $employee->id)
            ->where('project_id', $project->id)
            ->where('account_set_id', $request->account_set_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => '该员工已存在参保记录'
            ], 422);
        }

        // 创建参保记录
        $change = InsuranceChange::create([
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'account_set_id' => $request->account_set_id,
            'social_security_region_id' => $employee->social_security_region_id,
            'medical_insurance_region_id' => $employee->medical_insurance_region_id,
            'housing_fund_id' => $employee->housing_fund_region_id,
            'change_type' => 'increase',  // 手动导入默认为新增记录
            'status' => 'pending',
            'created_by' => $request->user()->id
        ]);

        // 根据项目绑定生成保险参数
        $this->generateInsuranceParams($change, $project);

        return response()->json([
            'success' => true,
            'message' => '自动导入成功',
            'data' => $change->load(['employee', 'project'])
        ]);
    }

    /**
     * 生成保险参数
     */
    private function generateInsuranceParams($change, $project)
    {
        $params = [];

        // 社保参数
        if ($change->social_security_region_id) {
            $region = SocialSecurityRegion::with('socialSecurityTypes')->find($change->social_security_region_id);
            if ($region) {
                $params['social_security_types'] = $region->socialSecurityTypes->map(function($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'base_amount' => $type->base_amount,
                        'employee_ratio' => (float)$type->employee_ratio,  // 确保转换为float
                        'company_ratio' => (float)$type->company_ratio      // 确保转换为float
                    ];
                })->toArray();
            }
        }

        // 医保参数
        if ($change->medical_insurance_region_id) {
            $region = MedicalInsuranceRegion::with('medicalInsuranceTypes')->find($change->medical_insurance_region_id);
            if ($region) {
                $params['medical_insurance_types'] = $region->medicalInsuranceTypes->map(function($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'base_amount' => $type->base_amount,
                        'employee_ratio' => (float)$type->employee_ratio,  // 确保转换为float
                        'company_ratio' => (float)$type->company_ratio      // 确保转换为float
                    ];
                })->toArray();
            }
        }

        // 公积金参数
        if ($change->housing_fund_id) {
            $fund = HousingFund::find($change->housing_fund_id);
            if ($fund) {
                $params['housing_fund_params'] = [
                    'id' => $fund->id,
                    'region_name' => $fund->region_name,
                    'base_amount' => $fund->base_amount,
                    'employee_ratio' => $fund->employee_ratio,
                    'company_ratio' => $fund->company_ratio
                ];
            }
        }

        // 其他保险参数
        $otherPolicies = $project->otherInsurancePolicies()->with('type')->get();
        if ($otherPolicies->isNotEmpty()) {
            $params['other_insurance_policies'] = $otherPolicies->map(function($policy) {
                return [
                    'id' => $policy->id,
                    'type_name' => $policy->type->name,
                    'policy_name' => $policy->name,
                    'coverage_amount' => $policy->coverage_amount,
                    'premium_amount' => $policy->premium_amount
                ];
            })->toArray();
        }

        $change->update($params);
    }

    /**
     * 生成参保明细（一人一条汇总记录）
     */
    private function generateDetails($change)
    {
        // 获取所有保险配置
        $socialSecurityTypes = $change->getCurrentSocialSecurityConfig();
        $medicalInsuranceTypes = $change->getCurrentMedicalInsuranceConfig();
        $housingFundConfig = $change->getCurrentHousingFundConfig();
        $otherInsurancePolicies = $change->getCurrentOtherInsuranceConfig();
        $largeMedicalConfig = $change->getCurrentLargeMedicalInsuranceConfig();

        // 使用员工档案中的基数值
        $medicalBase = $change->employee_medical_insurance_base ?? 0;
        $pensionBase = $change->employee_social_security_base ?? 0;

        // 构建动态保险细分详情
        $dynamicDetails = $this->buildDynamicInsuranceDetails(
            $socialSecurityTypes,
            $medicalInsuranceTypes,
            $largeMedicalConfig,
            $change->large_medical_insurance_enabled,
            $change,
            $medicalBase,
            $pensionBase
        );

        // 计算汇总金额 - 使用员工档案中的基数值
        $totalCompanyAmount = 0;
        $totalEmployeeAmount = 0;

        // 社保费用 - 使用员工社保基数
        foreach ($socialSecurityTypes as $type) {
            $baseAmount = $pensionBase; // 使用员工社保基数
            $totalCompanyAmount += $baseAmount * $type['company_ratio'];
            $totalEmployeeAmount += $baseAmount * $type['employee_ratio'];
        }

        // 医保费用 - 使用员工医保基数
        foreach ($medicalInsuranceTypes as $type) {
            $baseAmount = $medicalBase; // 使用员工医保基数
            $totalCompanyAmount += $baseAmount * $type['company_ratio'];
            $totalEmployeeAmount += $baseAmount * $type['employee_ratio'];
        }

        // 大额医疗保险费用 - 使用员工档案中的基数值
        if ($largeMedicalConfig && $change->large_medical_insurance_enabled) {
            $largeMedicalBase = $change->employee_large_medical_base ?? 0;
            $largeMedicalCompanyBase = $change->employee_large_medical_company_base ?? $largeMedicalBase;
            
            // 根据计算方式计算金额
            if ($largeMedicalConfig['calculation_type'] === 'base') {
                // 按基数计算：公司用公司基数，个人用个人基数
                $companyCost = $largeMedicalCompanyBase * ($largeMedicalConfig['company_ratio'] ?? 0);
                $employeeCost = $largeMedicalBase * ($largeMedicalConfig['employee_ratio'] ?? 0);
            } else {
                // 按固定金额：使用配置中的固定金额
                $companyCost = $largeMedicalConfig['company_amount'] ?? 0;
                $employeeCost = $largeMedicalConfig['employee_amount'] ?? 0;
            }
            
            // 根据付款周期和月份决定是否计入汇总金额
            if ($largeMedicalConfig['payment_cycle'] === 'month') {
                // 按月付款：每个月都计入实际金额
                $totalCompanyAmount += $companyCost;
                $totalEmployeeAmount += $employeeCost;
            } else {
                // 按年付款：需要检查是否是付款月份
                $currentMonth = now()->month;
                $isFirstTime = $this->isFirstTimeLargeMedicalEnrollment($change->employee_id, $largeMedicalConfig['id']);
                
                if ($isFirstTime) {
                    // 首次参保：计入实际金额
                    $totalCompanyAmount += $companyCost;
                    $totalEmployeeAmount += $employeeCost;
                } else {
                    // 非首次参保：检查是否是年度缴费月份
                    $firstEnrollmentMonth = $this->getFirstEnrollmentMonth($change->employee_id, $largeMedicalConfig['id']);
                    
                    if ($firstEnrollmentMonth && $currentMonth == $firstEnrollmentMonth) {
                        // 年度缴费月份：计入实际金额
                        $totalCompanyAmount += $companyCost;
                        $totalEmployeeAmount += $employeeCost;
                    }
                    // 非年度缴费月份：不计入金额（保持0）
                }
            }
        }

        // 创建汇总明细记录
        $summaryDetail = new InsuranceChangeDetail([
            'insurance_change_id' => $change->id,
            'employee_id' => $change->employee_id,
            'project_id' => $change->project_id,
            'account_set_id' => $change->account_set_id,
            'insurance_type' => '社保汇总',
            'insurance_name' => '社保汇总',
            'region_name' => $change->employee->socialSecurityRegion->name ?? '',
            'base_amount' => $pensionBase, // 使用养老基数作为主要基数
            'employee_ratio' => 0,
            'company_ratio' => 0,
            'employee_amount' => $totalEmployeeAmount,
            'company_amount' => $totalCompanyAmount,
            'total_amount' => $totalCompanyAmount + $totalEmployeeAmount,
            'status' => 'active',
            'effective_date' => now(),
            'medical_base' => $medicalBase,
            'pension_base' => $pensionBase,
            'employee_medical_insurance_base' => $change->employee_medical_insurance_base,
            'employee_social_security_base' => $change->employee_social_security_base,
            'dynamic_insurance_details' => $dynamicDetails,
            'detail_type' => 'summary'
        ]);

        $summaryDetail->save();

        // 如果有公积金配置，创建公积金汇总记录 - 使用员工档案中的公积金基数
        if ($housingFundConfig) {
            $housingFundBase = $change->employee_housing_fund_base ?? 0;
            $housingFundEmployeeAmount = $housingFundBase * $housingFundConfig['employee_ratio'];
            $housingFundCompanyAmount = $housingFundBase * $housingFundConfig['company_ratio'];
            
            $housingFundDetail = new InsuranceChangeDetail([
                'insurance_change_id' => $change->id,
                'employee_id' => $change->employee_id,
                'project_id' => $change->project_id,
                'account_set_id' => $change->account_set_id,
                'insurance_type' => '公积金',
                'insurance_name' => '住房公积金',
                'region_name' => $housingFundConfig['region_name'] ?? '',
                'base_amount' => $housingFundBase, // 使用员工档案中的公积金基数
                'employee_ratio' => $housingFundConfig['employee_ratio'],
                'company_ratio' => $housingFundConfig['company_ratio'],
                'employee_amount' => $housingFundEmployeeAmount,
                'company_amount' => $housingFundCompanyAmount,
                'total_amount' => $housingFundEmployeeAmount + $housingFundCompanyAmount,
                'status' => 'active',
                'effective_date' => now(),
                'employee_housing_fund_base' => $change->employee_housing_fund_base, // 保存员工公积金基数
                'detail_type' => 'summary'
            ]);

            $housingFundDetail->save();
        }

        // 如果有其他保险，创建其他保险汇总记录
        if (!empty($otherInsurancePolicies)) {
            $totalOtherCompanyAmount = 0;
            $totalOtherEmployeeAmount = 0;

            foreach ($otherInsurancePolicies as $policy) {
                $totalOtherCompanyAmount += $policy['employee_per_capita_cost'] ?? 0;
            }

            $otherInsuranceDetail = new InsuranceChangeDetail([
                'insurance_change_id' => $change->id,
                'employee_id' => $change->employee_id,
                'project_id' => $change->project_id,
                'account_set_id' => $change->account_set_id,
                'insurance_type' => '其他保险',
                'insurance_name' => '其他保险汇总',
                'region_name' => '全国',
                'base_amount' => null,
                'employee_ratio' => 0,
                'company_ratio' => 1,
                'employee_amount' => $totalOtherEmployeeAmount,
                'company_amount' => $totalOtherCompanyAmount,
                'total_amount' => $totalOtherCompanyAmount + $totalOtherEmployeeAmount,
                'status' => 'active',
                'effective_date' => now(),
                'detail_type' => 'summary'
            ]);

            $otherInsuranceDetail->save();
        }
    }

    /**
     * 构建动态保险细分详情
     */
    private function buildDynamicInsuranceDetails($socialSecurityTypes, $medicalInsuranceTypes, $largeMedicalConfig, $largeMedicalEnabled, $change, $medicalBase, $pensionBase)
    {
        $dynamicDetails = [
            'company_contributions' => [],
            'employee_contributions' => [],
            'column_config' => [] // 新增：列配置信息
        ];

        // 医保细分 - 使用员工医保基数
        foreach ($medicalInsuranceTypes as $type) {
            $companyAmount = $medicalBase * $type['company_ratio'];
            $employeeAmount = $medicalBase * $type['employee_ratio'];
            
            $dynamicDetails['company_contributions'][] = [
                'name' => $type['name'],
                'ratio' => $type['company_ratio'],
                'amount' => $companyAmount,
                'type' => 'medical'
            ];
            
            $dynamicDetails['employee_contributions'][] = [
                'name' => $type['name'],
                'ratio' => $type['employee_ratio'],
                'amount' => $employeeAmount,
                'type' => 'medical'
            ];

            // 添加到列配置
            $dynamicDetails['column_config'][] = [
                'name' => $type['name'],
                'company_ratio' => $type['company_ratio'],
                'employee_ratio' => $type['employee_ratio'],
                'type' => 'medical'
            ];
        }

        // 社保细分 - 使用员工社保基数
        foreach ($socialSecurityTypes as $type) {
            $companyAmount = $pensionBase * $type['company_ratio'];
            $employeeAmount = $pensionBase * $type['employee_ratio'];
            
            $dynamicDetails['company_contributions'][] = [
                'name' => $type['name'],
                'ratio' => $type['company_ratio'],
                'amount' => $companyAmount,
                'type' => 'social'
            ];
            
            $dynamicDetails['employee_contributions'][] = [
                'name' => $type['name'],
                'ratio' => $type['employee_ratio'],
                'amount' => $employeeAmount,
                'type' => 'social'
            ];

            // 添加到列配置
            $dynamicDetails['column_config'][] = [
                'name' => $type['name'],
                'company_ratio' => $type['company_ratio'],
                'employee_ratio' => $type['employee_ratio'],
                'type' => 'social'
            ];
        }

        // 大额医疗保险 - 使用员工档案中的基数值
        if ($largeMedicalConfig && $largeMedicalEnabled) {
            $largeMedicalBase = $change->employee_large_medical_base ?? 0;
            $largeMedicalCompanyBase = $change->employee_large_medical_company_base ?? $largeMedicalBase;
            
            // 根据计算方式计算金额
            if ($largeMedicalConfig['calculation_type'] === 'base') {
                // 按基数计算：公司用公司基数，个人用个人基数
                $baseCompanyAmount = $largeMedicalCompanyBase * ($largeMedicalConfig['company_ratio'] ?? 0);
                $baseEmployeeAmount = $largeMedicalBase * ($largeMedicalConfig['employee_ratio'] ?? 0);
            } else {
                // 按固定金额：使用配置中的固定金额
                $baseCompanyAmount = $largeMedicalConfig['company_amount'] ?? 0;
                $baseEmployeeAmount = $largeMedicalConfig['employee_amount'] ?? 0;
            }
            
            // 根据付款周期和月份决定是否显示金额
            $companyAmount = 0;
            $employeeAmount = 0;
            
            if ($largeMedicalConfig['payment_cycle'] === 'month') {
                // 按月付款：每个月都显示实际金额
                $companyAmount = $baseCompanyAmount;
                $employeeAmount = $baseEmployeeAmount;
            } else {
                // 按年付款：需要检查是否是付款月份
                $currentMonth = now()->month;
                $isFirstTime = $this->isFirstTimeLargeMedicalEnrollment($change->employee_id, $largeMedicalConfig['id']);
                
                if ($isFirstTime) {
                    // 首次参保：显示实际金额
                    $companyAmount = $baseCompanyAmount;
                    $employeeAmount = $baseEmployeeAmount;
                } else {
                    // 非首次参保：检查是否是年度缴费月份
                    $firstEnrollmentMonth = $this->getFirstEnrollmentMonth($change->employee_id, $largeMedicalConfig['id']);
                    
                    if ($firstEnrollmentMonth && $currentMonth == $firstEnrollmentMonth) {
                        // 年度缴费月份：显示实际金额
                        $companyAmount = $baseCompanyAmount;
                        $employeeAmount = $baseEmployeeAmount;
                    } else {
                        // 非年度缴费月份：显示0
                        $companyAmount = 0;
                        $employeeAmount = 0;
                    }
                }
            }
            
            $dynamicDetails['company_contributions'][] = [
                'name' => '大额医疗',
                'ratio' => null,
                'amount' => $companyAmount,
                'type' => 'large_medical'
            ];
            
            $dynamicDetails['employee_contributions'][] = [
                'name' => '大额医疗',
                'ratio' => null,
                'amount' => $employeeAmount,
                'type' => 'large_medical'
            ];

            // 添加到列配置
            $dynamicDetails['column_config'][] = [
                'name' => '大额医疗',
                'company_ratio' => null,
                'employee_ratio' => null,
                'type' => 'large_medical'
            ];
        }

        // 其他保险保单信息
        $otherInsurancePolicies = $change->getCurrentOtherInsuranceConfig();
        if (!empty($otherInsurancePolicies)) {
            $dynamicDetails['other_insurance_policies'] = $otherInsurancePolicies;
        }

        return $dynamicDetails;
    }

    /**
     * 生成大额医疗保险明细
     * 根据付款周期（按月/按年）决定生成规则
     */
    private function generateLargeMedicalDetails($change, $config)
    {
        $details = [];
        $currentMonth = now()->month;
        
        if ($config['payment_cycle'] === 'month') {
            // 按月付款：每个月都生成明细
            $detail = new InsuranceChangeDetail([
                'insurance_change_id' => $change->id,
                'employee_id' => $change->employee_id,
                'project_id' => $change->project_id,
                'account_set_id' => $change->account_set_id,
                'insurance_type' => '大额医疗保险',
                'insurance_name' => '大额医疗保险',
                'region_name' => $config['region_name'],
                'base_amount' => $config['base_amount'],
                'employee_ratio' => $config['employee_ratio'],
                'company_ratio' => $config['company_ratio'],
                'employee_amount' => $config['employee_cost'],
                'company_amount' => $config['company_cost'],
                'total_amount' => $config['total_cost'],
                'status' => 'active',
                'effective_date' => now(),
                'payment_cycle' => 'month',
                'payment_month' => $currentMonth
            ]);
            $details[] = $detail;
            
        } else {
            // 按年付款：需要检查是否是首次参保或年度缴费月份
            $isFirstTime = $this->isFirstTimeLargeMedicalEnrollment($change->employee_id, $config['id']);
            
            if ($isFirstTime) {
                // 首次参保：记录参保月份，生成明细
                $detail = new InsuranceChangeDetail([
                    'insurance_change_id' => $change->id,
                    'employee_id' => $change->employee_id,
                    'project_id' => $change->project_id,
                    'account_set_id' => $change->account_set_id,
                    'insurance_type' => '大额医疗保险',
                    'insurance_name' => '大额医疗保险',
                    'region_name' => $config['region_name'],
                    'base_amount' => $config['base_amount'],
                    'employee_ratio' => $config['employee_ratio'],
                    'company_ratio' => $config['company_ratio'],
                    'employee_amount' => $config['employee_cost'],
                    'company_amount' => $config['company_cost'],
                    'total_amount' => $config['total_cost'],
                    'status' => 'active',
                    'effective_date' => now(),
                    'payment_cycle' => 'year',
                    'payment_month' => $currentMonth
                ]);
                $details[] = $detail;
                
                // 记录首次参保月份
                $this->recordFirstEnrollmentMonth($change->employee_id, $config['id'], $currentMonth);
                
            } else {
                // 非首次参保：检查是否是年度缴费月份
                $firstEnrollmentMonth = $this->getFirstEnrollmentMonth($change->employee_id, $config['id']);
                
                if ($firstEnrollmentMonth && $currentMonth == $firstEnrollmentMonth) {
                    // 年度缴费月份：生成实际金额明细
                    $detail = new InsuranceChangeDetail([
                        'insurance_change_id' => $change->id,
                        'employee_id' => $change->employee_id,
                        'project_id' => $change->project_id,
                        'account_set_id' => $change->account_set_id,
                        'insurance_type' => '大额医疗保险',
                        'insurance_name' => '大额医疗保险',
                        'region_name' => $config['region_name'],
                        'base_amount' => $config['base_amount'],
                        'employee_ratio' => $config['employee_ratio'],
                        'company_ratio' => $config['company_ratio'],
                        'employee_amount' => $config['employee_cost'],
                        'company_amount' => $config['company_cost'],
                        'total_amount' => $config['total_cost'],
                        'status' => 'active',
                        'effective_date' => now(),
                        'payment_cycle' => 'year',
                        'payment_month' => $currentMonth
                    ]);
                    $details[] = $detail;
                } else {
                    // 非年度缴费月份：生成金额为0的明细
                    $detail = new InsuranceChangeDetail([
                        'insurance_change_id' => $change->id,
                        'employee_id' => $change->employee_id,
                        'project_id' => $change->project_id,
                        'account_set_id' => $change->account_set_id,
                        'insurance_type' => '大额医疗保险',
                        'insurance_name' => '大额医疗保险',
                        'region_name' => $config['region_name'],
                        'base_amount' => $config['base_amount'],
                        'employee_ratio' => $config['employee_ratio'],
                        'company_ratio' => $config['company_ratio'],
                        'employee_amount' => 0,
                        'company_amount' => 0,
                        'total_amount' => 0,
                        'status' => 'active',
                        'effective_date' => now(),
                        'payment_cycle' => 'year',
                        'payment_month' => $currentMonth
                    ]);
                    $details[] = $detail;
                }
            }
        }
        
        return $details;
    }

    /**
     * 检查是否是首次参保大额医疗保险
     */
    private function isFirstTimeLargeMedicalEnrollment($employeeId, $configId)
    {
        // 检查是否已有该员工该配置的明细记录
        $existingDetail = \App\Models\InsuranceChangeDetail::where('employee_id', $employeeId)
            ->where('insurance_type', '大额医疗保险')
            ->where('insurance_name', '大额医疗保险')
            ->where('payment_cycle', 'year')
            ->where('total_amount', '>', 0) // 只检查有实际金额的记录
            ->first();
            
        return !$existingDetail;
    }

    /**
     * 记录首次参保月份
     */
    private function recordFirstEnrollmentMonth($employeeId, $configId, $month)
    {
        // 在员工表中记录首次参保月份（可以扩展员工表或创建专门的表）
        // 这里暂时使用日志记录，实际项目中可以创建专门的表
        \Log::info('记录员工首次参保大额医疗保险月份', [
            'employee_id' => $employeeId,
            'config_id' => $configId,
            'first_enrollment_month' => $month
        ]);
    }

    /**
     * 获取首次参保月份
     */
    private function getFirstEnrollmentMonth($employeeId, $configId)
    {
        // 从明细记录中查找首次参保月份
        $firstDetail = \App\Models\InsuranceChangeDetail::where('employee_id', $employeeId)
            ->where('insurance_type', '大额医疗保险')
            ->where('insurance_name', '大额医疗保险')
            ->where('payment_cycle', 'year')
            ->where('total_amount', '>', 0) // 只检查有实际金额的记录
            ->orderBy('created_at', 'asc')
            ->first();
            
        return $firstDetail ? $firstDetail->payment_month : null;
    }

    /**
     * 生成或更新参保明细（基于新表生成明细记录）
     */
    private function generateOrUpdateDetails($change)
    {
        try {
            // 同步数据到参保人员信息表
            $personnel = $this->syncToInsurancePersonnel($change);
            
            // 如果是减少记录，personnel 为 null，无需生成明细记录
            if (!$personnel) {
                \Log::info('减少记录已处理，无需生成明细记录', [
                    'insurance_change_id' => $change->id,
                    'employee_name' => $change->employee_name,
                    'change_type' => $change->change_type
                ]);
                return null;
            }
            
            // 基于参保人员信息生成当前月份的明细记录
            $currentYear = date('Y');
            $currentMonth = date('n');
            
            $detailRecord = InsuranceDetailRecord::generateFromPersonnel($personnel, $currentYear, $currentMonth);
            
            \Log::info('参保明细记录生成成功', [
                'insurance_change_id' => $change->id,
                'insurance_personnel_id' => $personnel->id,
                'detail_record_id' => $detailRecord->id,
                'year' => $currentYear,
                'month' => $currentMonth,
                'employee_name' => $change->employee_name
            ]);
            
            return $detailRecord;
        } catch (\Exception $e) {
            \Log::error('参保明细记录生成失败', [
                'insurance_change_id' => $change->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * 同步数据到参保人员信息表
     */
    private function syncToInsurancePersonnel($change)
    {
        try {
            // 使用模型的静态方法同步数据
            $personnel = InsurancePersonnel::getOrCreateFromInsuranceChange($change);
            
            // 如果是减少记录，personnel 为 null
            if (!$personnel) {
                \Log::info('减少记录已处理，参保人员已删除', [
                    'insurance_change_id' => $change->id,
                    'employee_name' => $change->employee_name,
                    'change_type' => $change->change_type
                ]);
                return null;
            }
            
            \Log::info('参保人员信息同步成功', [
                'insurance_change_id' => $change->id,
                'insurance_personnel_id' => $personnel->id,
                'employee_name' => $change->employee_name,
                'project_id' => $change->project_id
            ]);
            
            return $personnel;
        } catch (\Exception $e) {
            \Log::error('参保人员信息同步失败', [
                'insurance_change_id' => $change->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * 设置大额医疗保险支付起始时间
     * 
     * @param InsuranceChange $change
     * @return void
     */
    private function setLargeMedicalPaymentStartTime($change)
    {
        try {
            // 检查是否开启了大额医疗保险
            if (!$change->large_medical_insurance_enabled) {
                return;
            }

            // 获取参保人员记录
            $personnel = \App\Models\InsurancePersonnel::where('employee_id', $change->employee_id)->first();
            
            if (!$personnel) {
                return;
            }

            // 检查是否已经设置过支付起始时间
            // 只有在首次启用大额医疗保险时才设置支付起始时间
            if ($personnel->large_medical_payment_start_month && $personnel->large_medical_payment_start_year) {
                // 已经设置过，不需要重新设置
                \Log::info('大额医疗保险支付起始时间已存在，跳过设置', [
                    'employee_id' => $change->employee_id,
                    'existing_start_month' => $personnel->large_medical_payment_start_month,
                    'existing_start_year' => $personnel->large_medical_payment_start_year
                ]);
                return;
            }

            // 使用付款周期服务设置支付起始时间
            $paymentCycleService = app(\App\Services\LargeMedicalPaymentCycleService::class);
            $currentYear = date('Y');
            $currentMonth = date('n');
            
            $paymentCycleService->setPaymentStartTime($change->employee_id, $currentYear, $currentMonth);
            
            \Log::info('设置大额医疗保险支付起始时间', [
                'employee_id' => $change->employee_id,
                'employee_name' => $change->employee_name,
                'year' => $currentYear,
                'month' => $currentMonth
            ]);
            
        } catch (\Exception $e) {
            \Log::error('设置大额医疗保险支付起始时间失败', [
                'change_id' => $change->id,
                'employee_id' => $change->employee_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取社保补差明细
     */
    public function getSocialSecurityCompensation(Request $request)
    {
        try {
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 查询社保补差记录
            $compensations = InsurancePersonnel::where('account_set_id', $accountSetId)
                ->where('is_compensation', 1)
                ->whereNotNull('social_security_types')
                ->with(['employee', 'project'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $compensations
            ]);

        } catch (\Exception $e) {
            \Log::error('获取社保补差明细失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取社保补差明细失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取医保补差明细
     */
    public function getMedicalInsuranceCompensation(Request $request)
    {
        try {
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 查询医保补差记录
            $compensations = InsurancePersonnel::where('account_set_id', $accountSetId)
                ->where('is_compensation', 1)
                ->whereNotNull('medical_insurance_types')
                ->with(['employee', 'project'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $compensations
            ]);

        } catch (\Exception $e) {
            \Log::error('获取医保补差明细失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取医保补差明细失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取公积金补差明细
     */
    public function getHousingFundCompensation(Request $request)
    {
        try {
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 查询公积金补差记录
            $compensations = InsurancePersonnel::where('account_set_id', $accountSetId)
                ->where('is_compensation', 1)
                ->whereNotNull('housing_fund_params')
                ->with(['employee', 'project'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $compensations
            ]);

        } catch (\Exception $e) {
            \Log::error('获取公积金补差明细失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取公积金补差明细失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 手动触发补差计算
     */
    public function triggerCompensation(Request $request)
    {
        try {
            $accountSetId = $request->input('account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 调用 Artisan 命令处理补差
            \Artisan::call('base:process-compensation');
            
            return response()->json([
                'success' => true,
                'message' => '补差计算已触发，请稍后查看结果'
            ]);

        } catch (\Exception $e) {
            \Log::error('触发补差计算失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '触发补差计算失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 应用基数上下限约束
     * 
     * @param float $baseAmount 原始基数
     * @param float|null $minLimit 最低基数限制
     * @param float|null $maxLimit 最高基数限制
     * @return float 约束后的基数
     */
    private function applyBaseLimits($baseAmount, $minLimit, $maxLimit)
    {
        // 如果基数为0或负数，直接返回
        if ($baseAmount <= 0) {
            return $baseAmount;
        }

        // 应用下限约束
        if ($minLimit !== null && $baseAmount < $minLimit) {
            return $minLimit;
        }

        // 应用上限约束
        if ($maxLimit !== null && $baseAmount > $maxLimit) {
            return $maxLimit;
        }

        // 在上下限范围内，返回原值
        return $baseAmount;
    }

    /**
     * 过滤其他保险保单：只保留在指定年月有效期内的保单
     * 
     * @param string $otherInsurancePoliciesJson JSON字符串
     * @param int $year 年份
     * @param int $month 月份
     * @return string 过滤后的JSON字符串
     */
    private function filterOtherInsurancePoliciesByDate($otherInsurancePoliciesJson, $year, $month)
    {
        if (empty($otherInsurancePoliciesJson) || $otherInsurancePoliciesJson === '[]') {
            return '';
        }

        try {
            $policies = json_decode($otherInsurancePoliciesJson, true);
            if (!is_array($policies)) {
                return '';
            }

            $filteredPolicies = [];
            $currentDate = \Carbon\Carbon::create($year, $month, 1);

            foreach ($policies as $policy) {
                $policyId = $policy['id'] ?? null;
                if (!$policyId) {
                    continue;
                }

                // 从数据库获取保单的最新有效期
                $latestPolicy = \App\Models\OtherInsurancePolicy::find($policyId);
                if (!$latestPolicy) {
                    continue;
                }

                // 使用数据库中的最新有效期
                $startDate = \Carbon\Carbon::parse($latestPolicy->start_date);
                $endDate = \Carbon\Carbon::parse($latestPolicy->end_date);

                // 判断当前月份是否在保单有效期内
                if ($currentDate->gte($startDate) && $currentDate->lte($endDate)) {
                    // 在有效期内，保留该保单
                    $filteredPolicies[] = $policy;
                    
                    \Log::info('其他保险保单在有效期内', [
                        'policy_id' => $policyId,
                        'policy_name' => $policy['name'] ?? '未知',
                        'current_date' => $currentDate->format('Y-m'),
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                    ]);
                } else {
                    \Log::info('其他保险保单不在有效期内，已过滤', [
                        'policy_id' => $policyId,
                        'policy_name' => $policy['name'] ?? '未知',
                        'current_date' => $currentDate->format('Y-m'),
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                    ]);
                }
            }

            return empty($filteredPolicies) ? '' : json_encode($filteredPolicies);
        } catch (\Exception $e) {
            \Log::error('过滤其他保险保单失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return '';
        }
    }

    /**
     * 导出参保数据
     */
    public function export(Request $request)
    {
        // 参保增减导出权限
        if ($response = $this->checkPermission('insurance_change.export')) {
            return $response;
        }

        $request->validate([
            'template_id' => 'required|integer',
            'data' => 'required|array',
            'filename' => 'required|string'
        ]);
        
        try {
            // 获取模板
            $template = \App\Models\ReportTemplate::findOrFail($request->template_id);
            
            // 使用 PhpSpreadsheet 生成 Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // 设置表头
            $col = 'A';
            foreach ($template->fields as $field) {
                $sheet->setCellValue($col . '1', $field['label']);
                // 设置表头样式
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getStyle($col . '1')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
                // 设置列宽
                $sheet->getColumnDimension($col)->setWidth($field['width'] / 7);
                $col++;
            }
            
            // 填充数据
            $row = 2;
            foreach ($request->data as $dataRow) {
                $col = 'A';
                foreach ($template->fields as $field) {
                    $value = $dataRow[$field['label']] ?? '';
                    $sheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // 设置边框
            $styleArray = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('A1:' . chr(64 + count($template->fields)) . ($row - 1))->applyFromArray($styleArray);
            
            // 生成文件
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = $request->filename . '_' . date('YmdHis') . '.xlsx';
            $tempFile = storage_path('app/temp/' . $filename);
            
            // 确保目录存在
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
            
            $writer->save($tempFile);
            
            // 返回文件
            return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            \Log::error('导出失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '导出失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 生成参保登记表
     */
    public function generateRegistrationReports(Request $request)
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'task_ids' => 'required|array',
                'task_ids.*' => 'integer',
                'account_set_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $taskIds = $request->input('task_ids');
            $accountSetId = $request->input('account_set_id');
            $month = $request->input('month'); // 可选的月份参数

            // 调用服务生成报表，返回文件数组
            $reportService = new \App\Services\RegistrationReportService();
            $files = $reportService->generateReportsForDownload($taskIds, $accountSetId, $month);

            // 返回 JSON 数据，包含文件内容（base64编码）
            return response()->json([
                'success' => true,
                'data' => $files
            ]);

        } catch (\Exception $e) {
            \Log::error('生成参保登记表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_ids' => $request->input('task_ids')
            ]);

            // 针对特定业务错误返回 200 状态码，避免前端显示服务器错误
            $businessErrors = ['未找到可用的报表模板', '没有可生成的报表'];
            $isBusinessError = false;
            foreach ($businessErrors as $error) {
                if (strpos($e->getMessage(), $error) !== false) {
                    $isBusinessError = true;
                    break;
                }
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $isBusinessError ? 200 : 500);
        }
    }
}
