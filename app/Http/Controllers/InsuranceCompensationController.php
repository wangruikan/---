<?php

namespace App\Http\Controllers;

use App\Models\InsuranceCompensationRecord;
use App\Models\InsuranceCompensationAttachment;
use App\Models\Employee;
use App\Models\InsurancePersonnel;
use App\Models\OtherInsurancePolicy;
use App\Models\OtherInsuranceType;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class InsuranceCompensationController extends Controller
{
    /**
     * 获取理赔记录列表
     */
    public function index(Request $request)
    {
        $accountSetId = $request->input('account_set_id');
        $type = $request->input('type'); // work_injury 或 commercial
        $status = $request->input('status');
        $employeeName = $request->input('employee_name');
        $projectId = $request->input('project_id');

        $query = InsuranceCompensationRecord::where('account_set_id', $accountSetId)
            ->with(['employee', 'project', 'policy', 'creator']);

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($employeeName) {
            $query->where('employee_name', 'like', '%' . $employeeName . '%');
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $records = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'total' => $records->total(),
            'current_page' => $records->currentPage(),
            'per_page' => $records->perPage(),
        ]);
    }

    /**
     * 获取可申报工伤的员工列表（在职且绑定了社保的员工）
     */
    public function getWorkInjuryEmployees(Request $request)
    {
        $accountSetId = $request->input('account_set_id');

        // 查询在职且参保了社保的员工（有社保参保地区ID即表示参保了社保）
        $employees = Employee::where('account_set_id', $accountSetId)
            ->where('contract_status', 'active')
            ->whereNotNull('social_security_region_id')
            ->select('id', 'name', 'id_number', 'project_ids')
            ->get()
            ->map(function($employee) {
                // 获取项目名称
                $projectNames = [];
                $projectId = null;
                if ($employee->project_ids && is_array($employee->project_ids) && count($employee->project_ids) > 0) {
                    $projectId = $employee->project_ids[0]; // 取第一个项目ID
                    $projects = Project::whereIn('id', $employee->project_ids)->pluck('name', 'id');
                    $projectNames = $projects->toArray();
                }
                
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'project_id' => $projectId,
                    'project_name' => implode(', ', $projectNames)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    /**
     * 获取可申报商业险的员工列表（绑定了商业险保单的员工）
     */
    public function getCommercialInsuranceEmployees(Request $request)
    {
        $accountSetId = $request->input('account_set_id');

        // 查找保险种类名称为"商业险"的类型
        $commercialType = OtherInsuranceType::where('account_set_id', $accountSetId)
            ->where('name', '商业险')
            ->first();

        if (!$commercialType) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => '未找到商业险类型',
            ]);
        }

        // 查找该类型下的所有保单
        $policies = OtherInsurancePolicy::where('account_set_id', $accountSetId)
            ->where('type_id', $commercialType->id)
            ->where('status', 'active')
            ->get();

        if ($policies->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => '未找到商业险保单',
            ]);
        }

        $policyIds = $policies->pluck('id')->toArray();

        // 查找绑定了这些保单的项目，并记录项目-保单关系
        $projectPolicyMap = DB::table('project_other_insurance_policies')
            ->whereIn('policy_id', $policyIds)
            ->get()
            ->groupBy('project_id')
            ->map(function($items) {
                return $items->pluck('policy_id')->toArray();
            })
            ->toArray();

        if (empty($projectPolicyMap)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => '未找到绑定商业险的项目',
            ]);
        }

        $projectIds = array_keys($projectPolicyMap);

        // 查找这些项目下的在职员工
        $employees = Employee::where('account_set_id', $accountSetId)
            ->where('contract_status', 'active')
            ->get()
            ->filter(function($employee) use ($projectIds) {
                // 检查员工的项目ID是否在绑定了商业险的项目列表中
                $empProjectIds = $employee->project_ids ?? [];
                return !empty(array_intersect($empProjectIds, $projectIds));
            })
            ->map(function($employee) use ($projectIds, $projectPolicyMap, $policies) {
                // 获取员工的项目名称
                $empProjectIds = $employee->project_ids ?? [];
                $matchedProjectIds = array_intersect($empProjectIds, $projectIds);
                $projectNames = [];
                
                // 收集员工可用的保单
                $employeePolicyIds = [];
                foreach ($matchedProjectIds as $projectId) {
                    if (isset($projectPolicyMap[$projectId])) {
                        $employeePolicyIds = array_merge($employeePolicyIds, $projectPolicyMap[$projectId]);
                    }
                }
                $employeePolicyIds = array_unique($employeePolicyIds);
                
                // 获取保单详情
                $employeePolicies = $policies->whereIn('id', $employeePolicyIds)->map(function($policy) {
                    return [
                        'id' => $policy->id,
                        'name' => $policy->policy_name
                    ];
                })->values()->toArray();
                
                if (!empty($matchedProjectIds)) {
                    $projects = Project::whereIn('id', $matchedProjectIds)->pluck('name', 'id');
                    $projectNames = $projects->toArray();
                }
                
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'project_id' => !empty($matchedProjectIds) ? reset($matchedProjectIds) : null,
                    'project_name' => implode(', ', $projectNames),
                    'policies' => $employeePolicies
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $employees->values(),
        ]);
    }

    /**
     * 创建理赔记录（步骤1：登记）
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|integer',
            'type' => 'required|in:work_injury,commercial',
            'employee_id' => 'required|integer',
            'policy_id' => 'required_if:type,commercial|nullable|integer',
            'incident_date' => 'required|date',
            'incident_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::find($request->employee_id);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => '员工不存在',
                ], 404);
            }

            // 获取项目信息
            $projectId = null;
            $projectName = null;
            if (!empty($employee->project_ids)) {
                $project = \App\Models\Project::find($employee->project_ids[0]);
                if ($project) {
                    $projectId = $project->id;
                    $projectName = $project->name;
                }
            }

            // 获取保单信息（商业险）
            $policyName = null;
            if ($request->type === 'commercial' && $request->policy_id) {
                $policy = OtherInsurancePolicy::find($request->policy_id);
                if ($policy) {
                    $policyName = $policy->policy_name;
                }
            }

            $record = InsuranceCompensationRecord::create([
                'account_set_id' => $request->account_set_id,
                'type' => $request->type,
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'project_id' => $projectId,
                'project_name' => $projectName,
                'policy_id' => $request->policy_id,
                'policy_name' => $policyName,
                'incident_date' => $request->incident_date,
                'incident_description' => $request->incident_description,
                'status' => 'registered',
                'current_step' => 1,
                'registration_date' => now(),
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '登记成功',
                'data' => $record->load(['employee', 'project', 'policy', 'creator']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '登记失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取理赔记录详情
     */
    public function show($id)
    {
        $record = InsuranceCompensationRecord::with([
            'employee',
            'project',
            'policy',
            'creator',
            'attachments.uploader'
        ])->find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在',
            ], 404);
        }

        // 处理附件URL
        $host = request()->getSchemeAndHttpHost();
        $record->attachments->each(function ($attachment) use ($host) {
            $attachment->file_url = $host . '/storage/' . $attachment->file_path;
        });

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    /**
     * 更新理赔记录（步骤2：工伤认定结果 或 商业险提供材料）
     */
    public function updateStep2(Request $request, $id)
    {
        $record = InsuranceCompensationRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在',
            ], 404);
        }

        if ($record->current_step !== 1) {
            return response()->json([
                'success' => false,
                'message' => '当前步骤不正确',
            ], 400);
        }

        try {
            DB::beginTransaction();

            if ($record->type === 'work_injury') {
                // 工伤：提交认定结果
                $validator = Validator::make($request->all(), [
                    'recognition_result' => 'required|in:success,failed',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => $validator->errors()->first(),
                    ], 400);
                }

                $record->update([
                    'recognition_result' => $request->recognition_result,
                    'recognition_date' => now(),
                    'current_step' => 2,
                    'status' => $request->recognition_result === 'success' ? 'recognition_success' : 'recognition_failed',
                ]);

                // 如果认定失败，直接完成
                if ($request->recognition_result === 'failed') {
                    $record->update([
                        'completed_date' => now(),
                        'status' => 'completed',
                    ]);
                }
            } else {
                // 商业险：提供材料（步骤2）
                $record->update([
                    'material_submitted_date' => now(),
                    'current_step' => 2,
                    'status' => 'material_submitted',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '更新成功',
                'data' => $record->load(['employee', 'project', 'policy', 'creator']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '更新失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新理赔记录（步骤3：工伤提交材料 或 商业险理赔到账）
     */
    public function updateStep3(Request $request, $id)
    {
        $record = InsuranceCompensationRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在',
            ], 404);
        }

        if ($record->type === 'work_injury' && $record->current_step !== 2) {
            return response()->json([
                'success' => false,
                'message' => '当前步骤不正确',
            ], 400);
        }

        if ($record->type === 'commercial' && $record->current_step !== 2) {
            return response()->json([
                'success' => false,
                'message' => '当前步骤不正确',
            ], 400);
        }

        try {
            DB::beginTransaction();

            if ($record->type === 'work_injury') {
                // 工伤：提交材料（医药费和/或伤残认定）
                $validator = Validator::make($request->all(), [
                    'medical_expense_claimed' => 'required|boolean',
                    'disability_claimed' => 'required|boolean',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => $validator->errors()->first(),
                    ], 400);
                }

                // 至少选择一项
                if (!$request->medical_expense_claimed && !$request->disability_claimed) {
                    return response()->json([
                        'success' => false,
                        'message' => '请至少选择一项申报内容',
                    ], 400);
                }

                $record->update([
                    'medical_expense_claimed' => $request->medical_expense_claimed,
                    'disability_claimed' => $request->disability_claimed,
                    'material_submitted_date' => now(),
                    'current_step' => 3,
                    'status' => 'material_submitted',
                    'completed_date' => now(),
                ]);
            } else {
                // 商业险：理赔到账
                $record->update([
                    'claim_received_date' => now(),
                    'current_step' => 3,
                    'status' => 'completed',
                    'completed_date' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '操作成功',
                'data' => $record->load(['employee', 'project', 'policy', 'creator']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '操作失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'compensation_record_id' => 'required|integer',
            'step' => 'required|integer|min:1|max:3',
            'file' => 'required|file|max:10240', // 最大10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $record = InsuranceCompensationRecord::find($request->compensation_record_id);
        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '理赔记录不存在',
            ], 404);
        }

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            
            // 存储到 public/uploads/insurance_compensation 目录
            $path = $file->storeAs('insurance_compensation', $fileName, 'public');

            // 判断文件类型
            $fileType = 'other';
            $mimeType = $file->getMimeType();
            if (strpos($mimeType, 'image/') === 0) {
                $fileType = 'image';
            } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
                $fileType = 'document';
            }

            $attachment = InsuranceCompensationAttachment::create([
                'compensation_record_id' => $record->id,
                'step' => $request->step,
                'file_name' => $originalName,
                'file_path' => $path,
                'file_type' => $fileType,
                'file_size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);

            $host = request()->getSchemeAndHttpHost();
            $attachment->file_url = $host . '/storage/' . $path;

            return response()->json([
                'success' => true,
                'message' => '上传成功',
                'data' => $attachment->load('uploader'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 删除附件
     */
    public function deleteAttachment($id)
    {
        $attachment = InsuranceCompensationAttachment::find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => '附件不存在',
            ], 404);
        }

        try {
            // 删除文件
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 删除理赔记录
     */
    public function destroy($id)
    {
        $record = InsuranceCompensationRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => '记录不存在',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // 删除所有附件文件
            foreach ($record->attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment->file_path)) {
                    Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }

            $record->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '删除成功',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage(),
            ], 500);
        }
    }

}
