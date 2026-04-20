<?php

namespace App\Http\Controllers;

use App\Models\Recruitment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

class RecruitmentController extends Controller
{
    use ChecksPermission;
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('recruitment.view')) {
            return $response;
        }
        
        $query = Recruitment::with(['project', 'assignedTo']);
        
        // 【账套过滤】
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }
        
        // 只有当参数有值时才进行过滤
        if ($request->filled('position')) {
            $query->where('position', 'like', '%' . $request->position . '%');
        }
        
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $perPage = $request->get('per_page', 20);
        $recruitments = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        // 格式化返回数据，添加前端需要的字段
        $recruitments->getCollection()->transform(function ($recruitment) {
            $recruitment->project_name = $recruitment->project ? $recruitment->project->name : '-';
            $recruitment->assigned_to_name = $recruitment->assignedTo ? $recruitment->assignedTo->name : '-';
            
            // 添加前端需要的字段（如果数据库中没有这些字段，使用默认值）
            $recruitment->department = $recruitment->department ?? '技术部';
            $recruitment->recruitment_count = $recruitment->required_count ?? 1;
            $recruitment->applied_count = 0;
            $recruitment->interviewed_count = 0;
            $recruitment->salary_range = $recruitment->salary_min && $recruitment->salary_max 
                ? $recruitment->salary_min . '-' . $recruitment->salary_max . '元'
                : '面议';
            
            return $recruitment;
        });
        
        return response()->json([
            'success' => true,
            'data' => $recruitments->items(),
            'total' => $recruitments->total(),
            'current_page' => $recruitments->currentPage(),
            'per_page' => $recruitments->perPage(),
            'last_page' => $recruitments->lastPage()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'position' => 'required|string',
            'required_count' => 'required|integer|min:1',
            'requirements' => 'required|string',
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0|gte:salary_min',
            'deadline' => 'nullable|date|after:today',
            // 添加前端表单中的其他字段验证
            'department' => 'nullable|string',
            'salary_range' => 'nullable|string',
            'work_location' => 'nullable|string',
            'education' => 'nullable|string',
            'experience' => 'nullable|string',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 【账套关联】
        $currentAccountSetId = $request->input('current_account_set_id');
        
        $recruitment = Recruitment::create([
            'project_id' => $request->project_id,
            'position' => $request->position,
            'required_count' => $request->required_count,
            'account_set_id' => $currentAccountSetId,
            'requirements' => $request->requirements,
            'salary_min' => $request->salary_min,
            'salary_max' => $request->salary_max,
            'deadline' => $request->deadline,
            'status' => 'active',
            'assigned_to' => null,
            'progress_notes' => null,
            'candidates' => [],
            'hired_count' => 0,
            // 添加前端表单中的其他字段
            'department' => $request->department,
            'salary_range' => $request->salary_range,
            'work_location' => $request->work_location,
            'education' => $request->education,
            'experience' => $request->experience,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json([
            'success' => true,
            'message' => '招聘需求创建成功',
            'data' => $recruitment
        ]);
    }

    public function show($id)
    {
        $recruitment = Recruitment::with(['project', 'assignedTo'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $recruitment
        ]);
    }

    public function update(Request $request, $id)
    {
        $recruitment = Recruitment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'project_id' => 'sometimes|required|exists:projects,id',
            'position' => 'sometimes|required|string',
            'required_count' => 'sometimes|required|integer|min:1',
            'requirements' => 'sometimes|required|string',
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0|gte:salary_min',
            'deadline' => 'nullable|date|after:today',
            // 添加前端表单中的其他字段验证
            'department' => 'nullable|string',
            'salary_range' => 'nullable|string',
            'work_location' => 'nullable|string',
            'education' => 'nullable|string',
            'experience' => 'nullable|string',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $recruitment->update($request->only([
            'project_id',
            'position',
            'required_count',
            'requirements',
            'salary_min',
            'salary_max',
            'deadline',
            // 添加前端表单中的其他字段
            'department',
            'salary_range',
            'work_location',
            'education',
            'experience',
            'description',
            'start_date',
            'end_date',
        ]));

        return response()->json([
            'success' => true,
            'message' => '招聘需求更新成功',
            'data' => $recruitment
        ]);
    }

    public function destroy($id)
    {
        $recruitment = Recruitment::findOrFail($id);
        $recruitment->delete();

        return response()->json([
            'success' => true,
            'message' => '招聘需求删除成功'
        ]);
    }

    public function assign(Request $request, $id)
    {
        $recruitment = Recruitment::findOrFail($id);

        // 自动获取账套的经办人员
        $handlerUserId = $this->getAccountSetHandler($recruitment->account_set_id);
        
        if (!$handlerUserId) {
            return response()->json([
                'success' => false,
                'message' => '当前账套未配置经办人员，请先在系统设置中配置'
            ], 400);
        }

        // 获取经办人信息
        $handler = \App\Models\User::find($handlerUserId);
        if (!$handler) {
            return response()->json([
                'success' => false,
                'message' => '经办人员不存在'
            ], 400);
        }

        // 更新招聘记录
        $recruitment->update([
            'assigned_to' => $handler->id,
            'assigned_user_name' => $handler->name,
            'assigned_at' => now(),
            'status' => 'active' // 分配后状态变为进行中
        ]);

        // 如果有分配说明，保存到notes
        if ($request->filled('notes')) {
            $recruitment->notes = $request->notes;
            $recruitment->save();
        }

        return response()->json([
            'success' => true,
            'message' => "已分配给经办人员：{$handler->name}",
            'data' => $recruitment->fresh()
        ]);
    }

    // 获取账套的经办人ID
    private function getAccountSetHandler($accountSetId)
    {
        $setting = \App\Models\SystemSetting::where('account_set_id', $accountSetId)
            ->where('key', 'handler_user_id')
            ->first();

        return $setting ? $setting->value : null;
    }

    /**
     * 获取当前用户的招聘模块权限信息
     */
    public function getPermissions()
    {
        $user = auth()->user();
        $accountSetId = request()->input('current_account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '缺少账套信息'
            ], 400);
        }

        $isHandler = $user->isHandlerForAccountSet($accountSetId);
        $isApprover = $user->isApproverForAccountSet($accountSetId);

        return response()->json([
            'success' => true,
            'data' => [
                'is_handler' => $isHandler,      // 是否是经办人员
                'is_approver' => $isApprover,    // 是否是审批人员
                'can_create' => $isApprover,     // 可以创建招聘需求
                'can_edit' => $isApprover,       // 可以编辑招聘需求
                'can_manage_candidates' => $isHandler,  // 可以管理候选人
                'can_update_progress' => $isHandler,    // 可以更新进度
                'can_complete' => $isHandler,           // 可以完成招聘
                'can_view_candidates' => true,          // 所有人都可以查看候选人
            ]
        ]);
    }

    public function updateProgress(Request $request, $id)
    {
        $recruitment = Recruitment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'applied_count' => 'nullable|integer|min:0|max:999',
            'interviewed_count' => 'nullable|integer|min:0|max:999',
            'hired_count' => 'nullable|integer|min:0|max:999',
            'progress_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $recruitment->updateProgress(
            $request->progress_notes,
            $request->applied_count,
            $request->interviewed_count,
            $request->hired_count
        );

        return response()->json([
            'success' => true,
            'message' => '进度更新成功',
            'data' => $recruitment->fresh()
        ]);
    }

    public function complete(Request $request, $id)
    {
        $recruitment = Recruitment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'hired_count' => 'required|integer|min:0|max:' . $recruitment->required_count,
            'candidates' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $recruitment->complete(
            $request->hired_count,
            $request->candidates ?? []
        );

        return response()->json([
            'success' => true,
            'message' => '招聘完成',
            'data' => $recruitment->fresh()
        ]);
    }

    // 获取候选人列表
    public function getCandidates(Request $request, $recruitmentId)
    {
        $recruitment = Recruitment::findOrFail($recruitmentId);
        
        $candidates = \App\Models\RecruitmentCandidate::where('recruitment_id', $recruitmentId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $candidates
        ]);
    }

    // 添加候选人
    public function storeCandidate(Request $request)
    {
        // 打印调试信息
        \Log::info('添加候选人请求数据', $request->all());
        
        $validator = Validator::make($request->all(), [
            'recruitment_id' => 'required|integer',
            'name' => 'required|string|max:50',
            'gender' => 'required|in:male,female',
            'phone' => 'required|string|max:20',
            'age' => 'nullable|integer|min:18|max:65',
            'email' => 'nullable|string',  // 简化邮箱验证，允许为空或任意字符串
            'education' => 'nullable|string',
            'experience' => 'nullable|string',
            'status' => 'nullable|in:pending,interviewing,to_be_hired,hired,rejected',
            'resume_url' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 获取账套ID
        $currentAccountSetId = $request->input('current_account_set_id');
        
        // 验证招聘记录是否存在
        $recruitment = Recruitment::find($request->recruitment_id);
        if (!$recruitment) {
            return response()->json([
                'success' => false,
                'message' => '招聘记录不存在，ID: ' . $request->recruitment_id
            ], 404);
        }
        
        try {
            $candidate = \App\Models\RecruitmentCandidate::create([
                'recruitment_id' => $request->recruitment_id,
                'account_set_id' => $currentAccountSetId,
                'name' => $request->name,
                'gender' => $request->gender,
                'age' => $request->age,
                'phone' => $request->phone,
                'email' => $request->email ?: null,  // 空字符串转为null
                'education' => $request->education,
                'experience' => $request->experience,
                'status' => $request->status ?? 'pending',
                'resume_url' => $request->resume_url,
                'notes' => $request->notes,
                'created_by' => auth()->id()
            ]);

            \Log::info('候选人添加成功', ['candidate_id' => $candidate->id]);

            return response()->json([
                'success' => true,
                'message' => '候选人添加成功',
                'data' => $candidate
            ]);
        } catch (\Exception $e) {
            \Log::error('候选人添加失败', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '添加失败：' . $e->getMessage()
            ], 500);
        }
    }

    // 更新候选人
    public function updateCandidate(Request $request, $id)
    {
        $candidate = \App\Models\RecruitmentCandidate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:50',
            'gender' => 'sometimes|required|in:male,female',
            'phone' => 'sometimes|required|string|max:20',
            'age' => 'nullable|integer|min:18|max:65',
            'email' => 'nullable|string',  // 简化邮箱验证
            'education' => 'nullable|string',
            'experience' => 'nullable|string',
            'status' => 'nullable|in:pending,interviewing,to_be_hired,hired,rejected',
            'resume_url' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $candidate->update($request->only([
            'name', 'gender', 'age', 'phone', 'email',
            'education', 'experience', 'status', 'resume_url', 'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => '候选人更新成功',
            'data' => $candidate->fresh()
        ]);
    }

    // 删除候选人
    public function destroyCandidate($id)
    {
        $candidate = \App\Models\RecruitmentCandidate::findOrFail($id);
        $candidate->delete();

        return response()->json([
            'success' => true,
            'message' => '候选人删除成功'
        ]);
    }
}
