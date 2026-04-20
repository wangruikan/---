<?php

namespace App\Http\Controllers;

use App\Models\InsuranceSurrenderRequest;
use App\Models\InsuranceSurrenderAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Traits\ChecksPermission;

class InsuranceSurrenderController extends Controller
{
    use ChecksPermission;

    /**
     * 退保流程列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('insurance_surrender.view')) {
            return $response;
        }

        $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
        if (!$accountSetId) {
            return response()->json(['success' => false, 'message' => '请选择账套'], 422);
        }

        $status = $request->input('status');
        $policyId = $request->input('policy_id');

        $query = InsuranceSurrenderRequest::where('account_set_id', $accountSetId)
            ->with(['policy.type', 'employee', 'project', 'initiator', 'attachments']);

        if ($status) {
            $query->where('status', $status);
        }
        if ($policyId) {
            $query->where('policy_id', $policyId);
        }

        $list = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * 退保详情
     */
    public function show(Request $request, $id)
    {
        if ($response = $this->checkPermission('insurance_surrender.view')) {
            return $response;
        }

        $data = InsuranceSurrenderRequest::with(['policy.type', 'employee', 'project', 'initiator', 'attachments.uploader'])
            ->findOrFail($id);

        // 附件 URL 补全
        $host = $request->getSchemeAndHttpHost();
        $data->attachments->transform(function ($att) use ($host) {
            if ($att->file_path && strpos($att->file_path, 'http') !== 0) {
                $att->file_url = $host . '/storage/' . ltrim($att->file_path, '/');
            }
            return $att;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 创建退保记录（手动创建，仅管理员可用）
     */
    public function store(Request $request)
    {
        // 权限检查：只允许管理员和超级管理员创建
        if ($response = $this->checkPermission('insurance_surrender.create')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|integer|exists:account_sets,id',
            'policy_id' => 'required|integer|exists:other_insurance_policies,id',
            'project_id' => 'required|integer|exists:projects,id',
        ], [
            'account_set_id.required' => '请选择账套',
            'policy_id.required' => '请选择保单',
            'project_id.required' => '请选择项目',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $accountSetId = $request->input('account_set_id');
        $policyId = $request->input('policy_id');
        $projectId = $request->input('project_id');

        // 验证保单是否为商业险类型
        $policy = \App\Models\OtherInsurancePolicy::with('type')->find($policyId);
        if (!$policy) {
            return response()->json(['success' => false, 'message' => '保单不存在'], 404);
        }

        $insuranceTypeName = $policy->type ? $policy->type->name : null;
        if ($insuranceTypeName !== '商业险') {
            return response()->json([
                'success' => false,
                'message' => '只能为商业险类型的保单创建退保记录'
            ], 422);
        }

        // 检查是否已存在未完成的退保记录（同一账套+同一保单+同一项目）
        $exists = InsuranceSurrenderRequest::where('account_set_id', $accountSetId)
            ->where('policy_id', $policyId)
            ->where('project_id', $projectId)
            ->whereIn('status', ['pending_business', 'business_done'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '该保单在此项目下已存在未完成的退保记录，请勿重复创建'
            ], 422);
        }

        // 创建退保记录
        $user = $request->user();
        $surrender = InsuranceSurrenderRequest::create([
            'account_set_id' => $accountSetId,
            'policy_id' => $policyId,
            'project_id' => $projectId,
            'employee_id' => null,  // 手动创建不关联员工
            'insurance_change_id' => null,  // 手动创建不关联变更记录
            'status' => 'pending_business',
            'initiated_by' => $user && $user->id ? $user->id : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => '退保记录创建成功',
            'data' => $surrender->fresh(['policy.type', 'project', 'initiator'])
        ]);
    }

    /**
     * 上传附件（业务：保单页 / 财务：回单）
     */
    public function uploadAttachment(Request $request, $id)
    {
        if ($response = $this->checkPermission('insurance_surrender.upload')) {
            return $response;
        }

        $surrender = InsuranceSurrenderRequest::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:policy_page,payment_receipt',
            'file' => 'required|file|max:51200',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => '验证失败', 'errors' => $validator->errors()], 422);
        }

        // 状态约束：pending_business 只能传保单页；business_done 只能传回单
        if ($surrender->status === 'pending_business' && $request->type !== 'policy_page') {
            return response()->json(['success' => false, 'message' => '当前阶段仅允许上传退保保单页'], 422);
        }
        if ($surrender->status === 'business_done' && $request->type !== 'payment_receipt') {
            return response()->json(['success' => false, 'message' => '当前阶段仅允许上传收款回单'], 422);
        }
        if ($surrender->status === 'finance_done') {
            return response()->json(['success' => false, 'message' => '该退保流程已完成，无法再上传附件'], 422);
        }

        $file = $request->file('file');
        $path = $file->store('insurance_surrenders', 'public');

        $att = InsuranceSurrenderAttachment::create([
            'surrender_request_id' => $surrender->id,
            'type' => $request->type,
            'file_path' => $path,
            'filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        $host = $request->getSchemeAndHttpHost();
        $att->file_url = $host . '/storage/' . ltrim($att->file_path, '/');

        return response()->json(['success' => true, 'message' => '上传成功', 'data' => $att]);
    }

    /**
     * 业务提交：填写退保金额 + 校验已上传保单页
     */
    public function submitBusiness(Request $request, $id)
    {
        if ($response = $this->checkPermission('insurance_surrender.submit_business')) {
            return $response;
        }

        $surrender = InsuranceSurrenderRequest::with('attachments')->findOrFail($id);
        if ($surrender->status !== 'pending_business') {
            return response()->json(['success' => false, 'message' => '当前状态不允许业务提交'], 422);
        }

        $validator = Validator::make($request->all(), [
            'surrender_amount' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => '验证失败', 'errors' => $validator->errors()], 422);
        }

        $hasPolicyPage = $surrender->attachments->where('type', 'policy_page')->count() > 0;
        if (!$hasPolicyPage) {
            return response()->json(['success' => false, 'message' => '请先上传退保保单页'], 422);
        }

        $surrender->update([
            'surrender_amount' => $request->surrender_amount,
            'remarks' => $request->remarks,
            'status' => 'business_done',
            'business_submitted_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => '业务提交成功，等待财务上传回单', 'data' => $surrender->fresh()]);
    }

    /**
     * 财务提交：校验已上传回单，标记完成
     */
    public function submitFinance(Request $request, $id)
    {
        if ($response = $this->checkPermission('insurance_surrender.submit_finance')) {
            return $response;
        }

        $surrender = InsuranceSurrenderRequest::with('attachments')->findOrFail($id);
        if ($surrender->status !== 'business_done') {
            return response()->json(['success' => false, 'message' => '当前状态不允许财务提交'], 422);
        }

        $hasReceipt = $surrender->attachments->where('type', 'payment_receipt')->count() > 0;
        if (!$hasReceipt) {
            return response()->json(['success' => false, 'message' => '请先上传收款回单'], 422);
        }

        $surrender->update([
            'status' => 'finance_done',
            'finance_submitted_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => '退保流程已完成', 'data' => $surrender->fresh()]);
    }

    /**
     * 获取商业险保单统计数据
     * 统计每个保单在每个月份的参保人数和总金额
     */
    public function getPolicyStatistics(Request $request)
    {
        if ($response = $this->checkPermission('insurance_surrender.view')) {
            return $response;
        }

        $accountSetId = $request->input('account_set_id') ?: $request->input('current_account_set_id');
        if (!$accountSetId) {
            return response()->json(['success' => false, 'message' => '请选择账套'], 422);
        }

        $policyId = $request->input('policy_id'); // 可选：筛选特定保单
        $startMonth = $request->input('start_month'); // 可选：开始月份 YYYY-MM
        $endMonth = $request->input('end_month'); // 可选：结束月份 YYYY-MM

        // 查询参保明细记录
        $query = \App\Models\InsurancePersonnel::where('account_set_id', $accountSetId)
            ->where('status', 'active')
            ->with(['employee', 'project']);

        $personnelRecords = $query->get();

        // 统计数据结构：{ policy_id: { policy_name, months: { 'YYYY-MM': { count, total_amount } } } }
        $statistics = [];

        foreach ($personnelRecords as $personnel) {
            // 解析商业险配置
            $otherInsurancePolicies = [];
            if ($personnel->other_insurance_policies) {
                try {
                    $otherInsurancePolicies = json_decode($personnel->other_insurance_policies, true) ?: [];
                } catch (\Exception $e) {
                    \Log::error('解析other_insurance_policies失败', [
                        'personnel_id' => $personnel->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (!is_array($otherInsurancePolicies) || empty($otherInsurancePolicies)) {
                continue;
            }

            // 获取当前记录的月份（使用last_updated_at或created_at）
            $recordMonth = $personnel->last_updated_at 
                ? $personnel->last_updated_at->format('Y-m')
                : $personnel->created_at->format('Y-m');

            // 月份筛选
            if ($startMonth && $recordMonth < $startMonth) {
                continue;
            }
            if ($endMonth && $recordMonth > $endMonth) {
                continue;
            }

            // 遍历该员工的所有商业险保单
            foreach ($otherInsurancePolicies as $policy) {
                $currentPolicyId = $policy['id'] ?? null;
                if (!$currentPolicyId) {
                    continue;
                }

                // 保单筛选
                if ($policyId && $currentPolicyId != $policyId) {
                    continue;
                }

                // 获取保单详细信息（用于判断是否为商业险）
                $policyModel = \App\Models\OtherInsurancePolicy::with('type')->find($currentPolicyId);
                if (!$policyModel) {
                    continue;
                }

                // 获取保险类型名称（通过关联关系）
                $insuranceTypeName = $policyModel->type ? $policyModel->type->name : null;

                // ✅ 只统计商业险类型的保单
                if ($insuranceTypeName !== '商业险') {
                    continue;
                }

                $policyName = $policy['name'] ?? $policy['policy_name'] ?? '未知保单';
                $insuranceType = $policy['type'] ?? '其他保险';
                $perCapitaCost = floatval($policy['employee_per_capita_cost'] ?? 0);

                // 初始化保单统计数据
                if (!isset($statistics[$currentPolicyId])) {
                    $statistics[$currentPolicyId] = [
                        'policy_id' => $currentPolicyId,
                        'policy_name' => $policyName,
                        'insurance_type' => $insuranceType,
                        'months' => []
                    ];
                }

                // 初始化月份统计数据
                if (!isset($statistics[$currentPolicyId]['months'][$recordMonth])) {
                    $statistics[$currentPolicyId]['months'][$recordMonth] = [
                        'month' => $recordMonth,
                        'count' => 0,
                        'total_amount' => 0,
                        'employees' => []
                    ];
                }

                // 累加统计
                $statistics[$currentPolicyId]['months'][$recordMonth]['count']++;
                $statistics[$currentPolicyId]['months'][$recordMonth]['total_amount'] += $perCapitaCost;
                $statistics[$currentPolicyId]['months'][$recordMonth]['employees'][] = [
                    'employee_id' => $personnel->employee_id,
                    'employee_name' => $personnel->employee_name,
                    'amount' => $perCapitaCost
                ];
            }
        }

        // 转换为数组并排序
        $result = [];
        foreach ($statistics as $policyId => $policyData) {
            // 将月份数据转换为数组并按月份排序
            $months = array_values($policyData['months']);
            usort($months, function($a, $b) {
                return strcmp($a['month'], $b['month']);
            });

            $result[] = [
                'policy_id' => $policyData['policy_id'],
                'policy_name' => $policyData['policy_name'],
                'insurance_type' => $policyData['insurance_type'],
                'months' => $months
            ];
        }

        // 按保单名称排序
        usort($result, function($a, $b) {
            return strcmp($a['policy_name'], $b['policy_name']);
        });

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}

