<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Traits\ChecksPermission;

class ProjectController extends Controller
{
    use ChecksPermission;
    /**
     * 检查项目访问权限
     */
    private function checkProjectAccess(Request $request, Project $project)
    {
        $user = $request->user();
        
        // 管理员可以访问所有项目
        if ($user->role === 'admin') {
            return;
        }
        
        // 检查用户是否有权限访问该项目的账套
        $hasAccess = $user->accountSets()
            ->where('account_set_id', $project->account_set_id)
            ->exists();
        
        if (!$hasAccess) {
            abort(403, '没有权限访问该项目');
        }
    }

    public function index(Request $request)
    {
        if ($response = $this->checkPermission('projects.view')) {
            return $response;
        }
        
        // 【账套过滤】根据当前账套过滤项目
        $currentAccountSetId = $request->input('current_account_set_id');
        
        $query = Project::withCount('employees')
            ->with(['medicalInsuranceRegions', 'otherInsurancePolicies.type', 'largeMedicalInsuranceConfigs']);
        
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            // 非管理员必须有账套ID，否则返回空
            $query->whereRaw('1 = 0');
        }
        
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        
        $projects = $query->orderBy('created_at', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->checkPermission('projects.create')) {
            return $response;
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|unique:projects,code',  // 改为可选，自动生成
            'description' => 'nullable|string',
            'social_security_location' => 'nullable|string',
            'insurance_types' => 'nullable|array',
            'salary_payment_date' => 'nullable|integer|min:1|max:31',
            'requires_attendance' => 'boolean',
            'delivery_frequency' => 'in:monthly,quarterly',
            'delivery_method' => 'in:express,electronic',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 【账套关联】自动关联到当前账套
        $projectData = $request->all();
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $projectData['account_set_id'] = $currentAccountSetId;
        }

        // 自动生成项目编号（如果没有提供）
        if (empty($projectData['code'])) {
            $projectData['code'] = $this->generateProjectCode($currentAccountSetId);
        }

        $project = Project::create($projectData);

        return response()->json([
            'success' => true,
            'message' => '项目创建成功',
            'data' => $project
        ]);
    }

    /**
     * 生成项目编号
     * 格式：AA, AB, AC, ..., AZ, BA, BB, ...
     * 
     * @param int $accountSetId 账套ID
     * @return string 生成的项目编号
     */
    protected function generateProjectCode($accountSetId)
    {
        // 获取当前账套下所有项目编号
        $existingCodes = Project::where('account_set_id', $accountSetId)
            ->whereNotNull('code')
            ->where('code', 'REGEXP', '^[A-Z]{2}$')  // 只匹配两个字母的编号
            ->pluck('code')
            ->toArray();
        
        // 将编号转换为数字，找出最大值
        $maxNum = -1;
        foreach ($existingCodes as $code) {
            if (strlen($code) === 2) {
                // 将两个字母转换为数字：AA=0, AB=1, AC=2, ..., AZ=25, BA=26, ...
                $firstLetter = ord($code[0]) - 65;  // A=0, B=1, ...
                $secondLetter = ord($code[1]) - 65;
                $num = $firstLetter * 26 + $secondLetter;
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        
        // 生成下一个编号
        $nextNum = $maxNum + 1;
        
        // 将数字转换为字母编号
        $firstLetter = chr(65 + floor($nextNum / 26));
        $secondLetter = chr(65 + ($nextNum % 26));
        $code = $firstLetter . $secondLetter;
        
        // 再次检查是否存在（双重保险）
        $exists = Project::where('code', $code)
            ->where('account_set_id', $accountSetId)
            ->exists();
        
        if ($exists) {
            // 如果还是冲突，继续递增
            return $this->generateProjectCode($accountSetId);
        }
        
        return $code;
    }

    public function show($id)
    {
        if ($response = $this->checkPermission('projects.view')) {
            return $response;
        }
        
        $project = Project::with(['employees', 'activeEmployees'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $project
        ]);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('projects.update')) {
            return $response;
        }
        
        $project = Project::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|unique:projects,code,' . $id,
            'description' => 'nullable|string',
            'social_security_location' => 'nullable|string',
            'insurance_types' => 'nullable|array',
            'salary_payment_date' => 'nullable|integer|min:1|max:31',
            'requires_attendance' => 'boolean',
            'require_attendance' => 'boolean',
            'delivery_frequency' => 'in:monthly,quarterly',
            'delivery_method' => 'in:express,electronic',
            'social_security_regions' => 'nullable|array',
            'medical_insurance_regions' => 'nullable|array',
            'housing_fund_regions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 同步 requires_attendance 和 require_attendance 字段
        $updateData = $request->all();
        if ($request->has('requires_attendance')) {
            $updateData['require_attendance'] = $request->input('requires_attendance');
        }
        if ($request->has('require_attendance')) {
            $updateData['requires_attendance'] = $request->input('require_attendance');
        }

        $project->update($updateData);

        return response()->json([
            'success' => true,
            'message' => '项目更新成功',
            'data' => $project
        ]);
    }

    public function destroy($id)
    {
        if ($response = $this->checkPermission('projects.delete')) {
            return $response;
        }
        
        $project = Project::findOrFail($id);
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => '项目删除成功'
        ]);
    }

    /**
     * 设置项目的劳动合同须知文件（支持多个）
     */
    public function setContractNotices(Request $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'notice_file_ids' => 'sometimes|array',
            'notice_file_ids.*' => 'integer|exists:shared_files,id',
            'notice_file_id' => 'nullable|integer|exists:shared_files,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::findOrFail($id);

        $noticeFileIds = [];
        if ($request->has('notice_file_ids')) {
            $noticeFileIds = $request->input('notice_file_ids', []);
        } elseif ($request->filled('notice_file_id')) {
            $noticeFileIds = [$request->input('notice_file_id')];
        }

        $noticeFileIds = array_values(array_unique(array_filter(array_map('intval', $noticeFileIds))));

        if (!empty($noticeFileIds)) {
            $validNoticeFiles = \App\Models\SharedFile::whereIn('id', $noticeFileIds)
                ->where('file_category', 'notice')
                ->where('account_set_id', $project->account_set_id)
                ->get();

            if ($validNoticeFiles->count() !== count($noticeFileIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '部分文件不是当前账套的须知文件'
                ], 422);
            }
        }

        \Log::info('💾 保存项目须知文件设置', [
            'project_id' => $id,
            'notice_file_ids' => $noticeFileIds
        ]);

        $firstNoticeFileId = $noticeFileIds[0] ?? null;
        $project->update([
            'contract_notice_file_id' => $firstNoticeFileId,
            'contract_notice_files' => empty($noticeFileIds) ? null : implode(',', $noticeFileIds)
        ]);

        $noticeFiles = collect();
        if (!empty($noticeFileIds)) {
            $filesMap = \App\Models\SharedFile::with('uploader')
                ->whereIn('id', $noticeFileIds)
                ->where('file_category', 'notice')
                ->where('account_set_id', $project->account_set_id)
                ->get()
                ->keyBy('id');

            $ordered = [];
            foreach ($noticeFileIds as $noticeFileId) {
                if (isset($filesMap[$noticeFileId])) {
                    $ordered[] = $filesMap[$noticeFileId];
                }
            }
            $noticeFiles = collect($ordered);
        }

        \Log::info('✅ 保存后读取项目数据', [
            'contract_notice_file_id' => $project->fresh()->contract_notice_file_id,
            'notice_file_ids' => $noticeFiles->pluck('id')->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => !empty($noticeFileIds) ? '须知文件设置成功' : '须知文件已清除',
            'data' => [
                'notice_file_ids' => $noticeFiles->pluck('id')->toArray(),
                'notice_files' => $noticeFiles,
                'notice_file' => $noticeFiles->first(),
            ]
        ]);
    }

    /**
     * 获取项目的须知文件（支持多个）
     */
    public function getContractNotices($id)
    {
        $project = Project::findOrFail($id);

        $noticeFiles = collect();
        if (!empty($project->contract_notice_files)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $project->contract_notice_files)))));
            if (!empty($ids)) {
                $filesMap = \App\Models\SharedFile::with('uploader')
                    ->whereIn('id', $ids)
                    ->where('file_category', 'notice')
                    ->where('account_set_id', $project->account_set_id)
                    ->get()
                    ->keyBy('id');

                $ordered = [];
                foreach ($ids as $idItem) {
                    if (isset($filesMap[$idItem])) {
                        $ordered[] = $filesMap[$idItem];
                    }
                }
                $noticeFiles = collect($ordered);
            }
        }

        if ($noticeFiles->isEmpty() && !empty($project->contract_notice_file_id)) {
            $legacyNoticeFile = \App\Models\SharedFile::with('uploader')
                ->where('id', $project->contract_notice_file_id)
                ->where('file_category', 'notice')
                ->where('account_set_id', $project->account_set_id)
                ->first();

            if ($legacyNoticeFile) {
                $noticeFiles = collect([$legacyNoticeFile]);
            }
        }

        \Log::info('📋 读取项目须知文件', [
            'project_id' => $id,
            'notice_file_ids' => $noticeFiles->pluck('id')->toArray(),
            'contract_notice_file_id' => $project->contract_notice_file_id,
            'contract_notice_files' => $project->contract_notice_files,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'notice_file_ids' => $noticeFiles->pluck('id')->toArray(),
                'notice_files' => $noticeFiles->values(),
                'notice_file' => $noticeFiles->first(),
            ]
        ]);
    }

    public function getStatistics($id)
    {
        $project = Project::findOrFail($id);
        
        $statistics = [
            'total_employees' => $project->employees()->count(),
            'active_employees' => $project->activeEmployees()->count(),
            'attendance_sheets' => $project->attendanceSheets()->count(),
            'pending_approvals' => $project->attendanceSheets()->where('status', 'submitted')->count(),
            'total_salaries' => $project->salaries()->sum('gross_salary'),
            'pending_payments' => $project->payments()->where('status', 'submitted')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * 获取项目的社保地区列表
     */
    public function getSocialSecurityRegions(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $regions = \App\Models\SocialSecurityRegion::whereIn('id', $project->social_security_regions ?? [])
            ->with(['socialSecurityTypes'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取项目的公积金地区列表
     */
    public function getHousingFundRegions(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $regions = \App\Models\HousingFundRegion::whereIn('id', $project->housing_fund_regions ?? [])->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取项目的医保地区
     */
    public function getMedicalInsuranceRegions(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $regions = $project->medicalInsuranceRegions()->with(['medicalInsuranceTypes'])->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 设置项目的社保地区
     */
    public function setSocialSecurityRegions(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'region_ids' => 'required|array',
            'region_ids.*' => 'exists:social_security_regions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证地区是否属于当前账套
        $validRegions = \App\Models\SocialSecurityRegion::where('account_set_id', $project->account_set_id)
            ->whereIn('id', $request->region_ids)
            ->pluck('id')
            ->toArray();

        if (count($validRegions) !== count($request->region_ids)) {
            return response()->json([
                'success' => false,
                'message' => '部分社保地区不属于当前账套'
            ], 422);
        }

        $project->update([
            'social_security_regions' => $validRegions
        ]);

        return response()->json([
            'success' => true,
            'message' => '社保地区设置成功',
            'data' => $project->social_security_regions
        ]);
    }

    /**
     * 设置项目的公积金地区
     */
    public function setHousingFundRegions(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'region_ids' => 'required|array',
            'region_ids.*' => 'exists:housing_fund_regions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证地区是否属于当前账套
        $validRegions = \App\Models\HousingFundRegion::where('account_set_id', $project->account_set_id)
            ->whereIn('id', $request->region_ids)
            ->pluck('id')
            ->toArray();

        if (count($validRegions) !== count($request->region_ids)) {
            return response()->json([
                'success' => false,
                'message' => '部分公积金地区不属于当前账套'
            ], 422);
        }

        $project->update([
            'housing_fund_regions' => $validRegions
        ]);

        return response()->json([
            'success' => true,
            'message' => '公积金地区设置成功',
            'data' => $project->housing_fund_regions
        ]);
    }

    /**
     * 设置项目的医保地区
     */
    public function setMedicalInsuranceRegions(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'region_ids' => 'required|array',
            'region_ids.*' => 'exists:medical_insurance_regions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证地区是否属于当前账套
        $validRegions = \App\Models\MedicalInsuranceRegion::where('account_set_id', $project->account_set_id)
            ->whereIn('id', $request->region_ids)
            ->pluck('id')
            ->toArray();

        if (count($validRegions) !== count($request->region_ids)) {
            return response()->json([
                'success' => false,
                'message' => '部分医保地区不属于当前账套'
            ], 422);
        }

        // 同步关联关系，并设置account_set_id
        $syncData = [];
        foreach ($validRegions as $regionId) {
            $syncData[$regionId] = ['account_set_id' => $project->account_set_id];
        }
        $project->medicalInsuranceRegions()->sync($syncData);

        return response()->json([
            'success' => true,
            'message' => '医保地区设置成功',
            'data' => $validRegions
        ]);
    }

    /**
     * 获取项目绑定的其他保险保单
     */
    public function getOtherInsurancePolicies(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $policies = $project->otherInsurancePolicies()->get();

        return response()->json([
            'success' => true,
            'data' => $policies
        ]);
    }

    /**
     * 设置项目绑定的其他保险保单
     */
    public function setOtherInsurancePolicies(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'policy_ids' => 'required|array',
            'policy_ids.*' => 'exists:other_insurance_policies,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证保单是否属于当前账套
        $validPolicies = \App\Models\OtherInsurancePolicy::where('account_set_id', $project->account_set_id)
            ->whereIn('id', $request->policy_ids)
            ->get();

        if ($validPolicies->count() !== count($request->policy_ids)) {
            return response()->json([
                'success' => false,
                'message' => '部分保单不属于当前账套'
            ], 422);
        }

        // 检查每种保险类型只能绑定一个保单
        $typeIds = $validPolicies->pluck('type_id')->toArray();
        if (count($typeIds) !== count(array_unique($typeIds))) {
            return response()->json([
                'success' => false,
                'message' => '每种保险类型只能绑定一个保单'
            ], 422);
        }

        // 获取旧的保单列表（变更前）
        $oldPolicyIds = DB::table('project_other_insurance_policies')
            ->where('project_id', $project->id)
            ->pluck('policy_id')
            ->toArray();
        
        // 同步关联关系，并设置account_set_id
        $syncData = [];
        foreach ($request->policy_ids as $policyId) {
            $syncData[$policyId] = ['account_set_id' => $project->account_set_id];
        }
        $project->otherInsurancePolicies()->sync($syncData);

        // ✅ 新增：检测保单变更，自动创建增减记录
        $newPolicyIds = $request->policy_ids;
        
        // 比较变更
        $addedPolicies = array_diff($newPolicyIds, $oldPolicyIds);    // 新增的保单
        $removedPolicies = array_diff($oldPolicyIds, $newPolicyIds);  // 删除的保单
        
        if (!empty($addedPolicies) || !empty($removedPolicies)) {
            // 有变更，触发自动导入
            $this->triggerOtherInsuranceChangeForProject($project, $addedPolicies, $removedPolicies);
            
            \Log::info('检测到项目其他保险保单变更', [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'old_policies' => $oldPolicyIds,
                'new_policies' => $newPolicyIds,
                'added' => $addedPolicies,
                'removed' => $removedPolicies
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '其他保险保单设置成功',
            'data' => $request->policy_ids
        ]);
    }

    /**
     * 获取项目绑定的大额医疗保险配置
     */
    public function getLargeMedicalInsuranceConfigs(Request $request, $id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        $configs = $project->largeMedicalInsuranceConfigs()->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * 设置项目绑定的大额医疗保险配置
     */
    public function setLargeMedicalInsuranceConfigs(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'config_ids' => 'present|array',
            'config_ids.*' => 'exists:large_medical_insurance_configs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::find($id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }
        
        $this->checkProjectAccess($request, $project);

        // 验证配置是否属于当前账套（如果有配置的话）
        $configIds = $request->config_ids ?? [];
        if (!empty($configIds)) {
            $validConfigs = \App\Models\LargeMedicalInsuranceConfig::where('account_set_id', $project->account_set_id)
                ->whereIn('id', $configIds)
                ->get();

            if ($validConfigs->count() !== count($configIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '部分配置不属于当前账套'
                ], 422);
            }
        }

        // 同步关联关系，并设置account_set_id
        $syncData = [];
        foreach ($configIds as $configId) {
            $syncData[$configId] = ['account_set_id' => $project->account_set_id];
        }
        $project->largeMedicalInsuranceConfigs()->sync($syncData);

        return response()->json([
            'success' => true,
            'message' => '大额医疗保险配置设置成功',
            'data' => $request->config_ids
        ]);
    }

    /**
     * 获取可用的社保地区列表（用于项目设置）
     */
    public function getAvailableSocialSecurityRegions(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        // 检查用户是否有权限访问此账套
        if ($user->role !== 'admin') {
            $hasAccess = \App\Models\AccountSet::whereHas('users', function($query) use ($user, $accountSetId) {
                $query->where('user_id', $user->id)
                      ->where('account_set_users.account_set_id', $accountSetId);
            })->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }
        
        // 管理员可以访问所有账套，无需额外检查

        $regions = \App\Models\SocialSecurityRegion::where('account_set_id', $accountSetId)
            ->with(['socialSecurityTypes'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取可用的公积金地区列表（用于项目设置）
     */
    public function getAvailableHousingFundRegions(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        // 检查用户是否有权限访问此账套
        if ($user->role !== 'admin') {
            $hasAccess = \App\Models\AccountSet::whereHas('users', function($query) use ($user, $accountSetId) {
                $query->where('user_id', $user->id)
                      ->where('account_set_users.account_set_id', $accountSetId);
            })->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }
        
        // 管理员可以访问所有账套，无需额外检查

        $regions = \App\Models\HousingFundRegion::where('account_set_id', $accountSetId)->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取可用的大额医疗保险地区列表（用于项目设置）
     */
    public function getAvailableLargeMedicalInsuranceRegions(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 422);
        }

        // 检查用户是否有权限访问此账套
        if ($user->role !== 'admin') {
            $hasAccess = \App\Models\AccountSet::whereHas('users', function($query) use ($user, $accountSetId) {
                $query->where('user_id', $user->id)
                      ->where('account_set_users.account_set_id', $accountSetId);
            })->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }

        $configs = \App\Models\LargeMedicalInsuranceConfig::where('account_set_id', $accountSetId)
            ->where('status', 1)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * 获取可用的医保地区列表（用于项目设置）
     */
    public function getAvailableMedicalInsuranceRegions(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        // 非管理员需要检查账套访问权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_sets.id', $accountSetId)->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }
        
        // 管理员可以访问所有账套，无需额外检查

        $regions = \App\Models\MedicalInsuranceRegion::where('account_set_id', $accountSetId)
            ->with(['medicalInsuranceTypes'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * 获取可用的其他保险保单列表（用于项目设置）
     */
    public function getAvailableOtherInsurancePolicies(Request $request)
    {
        // 兼容两种参数名
        $accountSetId = $request->input('account_set_id') ?? $request->input('current_account_set_id');
        $user = $request->user();
        
        // 非管理员需要检查账套访问权限
        if ($user->role !== 'admin') {
            $hasAccess = $user->accountSets()->where('account_sets.id', $accountSetId)->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问该账套'
                ], 403);
            }
        }
        
        // 管理员可以访问所有账套，无需额外检查

        $policies = \App\Models\OtherInsurancePolicy::where('account_set_id', $accountSetId)
            ->where('status', 'active')
            ->with(['type'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $policies
        ]);
    }

    /**
     * 项目其他保险保单变更时，为该项目的所有员工创建增减记录
     */
    private function triggerOtherInsuranceChangeForProject($project, $addedPolicies, $removedPolicies)
    {
        try {
            // 获取该项目下的所有在职员工
            $employees = $project->employees()
                ->where('contract_status', 'active')
                ->get();
            
            if ($employees->isEmpty()) {
                \Log::info('项目下没有在职员工，跳过创建增减记录', [
                    'project_id' => $project->id
                ]);
                return;
            }
            
            $detectionService = app(\App\Services\InsuranceChangeDetectionService::class);
            $currentYear = date('Y');
            $currentMonth = date('n');
            
            foreach ($employees as $employee) {
                // 检查该员工本月是否已有待处理的增减记录
                $existingChange = \App\Models\InsuranceChange::where('employee_id', $employee->id)
                    ->where('project_id', $project->id)
                    ->where('account_set_id', $project->account_set_id)
                    ->where('status', 'pending')
                    ->whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $currentMonth)
                    ->first();
                
                if ($existingChange) {
                    \Log::info('员工本月已有待处理记录，更新其他保险配置', [
                        'employee_id' => $employee->id,
                        'insurance_change_id' => $existingChange->id
                    ]);
                    
                    // 更新现有记录的其他保险配置
                    $detectionService->createOrUpdateInsuranceChange(
                        $employee,
                        'other_insurance',
                        $currentYear,
                        $currentMonth,
                        ['policies' => $removedPolicies],
                        ['policies' => $addedPolicies]
                    );
                } else {
                    \Log::info('为员工创建新的增减记录（项目保单变更）', [
                        'employee_id' => $employee->id,
                        'project_id' => $project->id
                    ]);
                    
                    // 创建新的增减记录
                    $detectionService->createOrUpdateInsuranceChange(
                        $employee,
                        'other_insurance',
                        $currentYear,
                        $currentMonth,
                        ['policies' => $removedPolicies],
                        ['policies' => $addedPolicies]
                    );
                }
            }
            
            \Log::info('项目其他保险保单变更处理完成', [
                'project_id' => $project->id,
                'affected_employees' => $employees->count(),
                'added_policies' => $addedPolicies,
                'removed_policies' => $removedPolicies
            ]);
            
        } catch (\Exception $e) {
            \Log::error('触发项目保单变更失败', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 获取可用的占位符字段列表
     */
    public function getAvailablePlaceholderFields()
    {
        return response()->json([
            'success' => true,
            'data' => Project::getAvailablePlaceholderFields()
        ]);
    }

    /**
     * 获取项目的占位符字段配置
     */
    public function getPlaceholderFields(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->checkProjectAccess($request, $project);

        return response()->json([
            'success' => true,
            'data' => $project->placeholder_fields ?? []
        ]);
    }

    /**
     * 保存项目的占位符字段配置
     */
    public function savePlaceholderFields(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->checkProjectAccess($request, $project);

        $validator = Validator::make($request->all(), [
            'placeholder_fields' => 'present|array',
            'placeholder_fields.*.key' => 'required|string',
            'placeholder_fields.*.label' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project->placeholder_fields = $request->placeholder_fields;
        $project->save();

        return response()->json([
            'success' => true,
            'message' => '占位符字段配置保存成功',
            'data' => $project->placeholder_fields
        ]);
    }
}
