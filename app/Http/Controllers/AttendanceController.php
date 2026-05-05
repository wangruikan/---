<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSheet;
use App\Models\AttendanceRecord;
use App\Models\AttendanceStatistics;
use App\Models\Project;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

class AttendanceController extends Controller
{
    use ChecksPermission;
    
    /**
     * 获取账套ID（优先从请求头/参数获取，其次从用户获取）
     */
    private function getAccountSetId(Request $request)
    {
        return $request->header('X-Account-Set-Id') 
            ?: $request->input('current_account_set_id') 
            ?: Auth::user()->account_set_id;
    }
    
    /**
     * 获取考勤表列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('attendance.view')) {
            return $response;
        }
        
        try {
            // 【账套过滤】从请求参数获取当前账套ID
            $accountSetId = $this->getAccountSetId($request);

            $query = AttendanceSheet::byAccountSet($accountSetId)
                ->with(['project', 'creator', 'submitter', 'approver']);

            // 搜索条件
            if ($request->filled('project_id')) {
                $query->byProject($request->project_id);
            }

            if ($request->filled('month')) {
                $query->byMonth($request->month);
            }

            // 状态筛选：默认不显示已驳回的记录
            if ($request->filled('status')) {
                $query->byStatus($request->status);
            } else {
                // 如果没有指定状态，默认排除已驳回的记录
                $query->where('status', '!=', 'rejected');
            }

            // 分页
            $perPage = $request->get('per_page', 20);
            $sheets = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $sheets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取考勤表列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建考勤表
     */
    public function store(Request $request)
    {
        if ($response = $this->checkPermission('attendance.create')) {
            return $response;
        }
        
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'required|exists:projects,id',
                'month' => 'required|date_format:Y-m',
                'work_days' => 'required|integer|min:1|max:31',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $accountSetId = $this->getAccountSetId($request);
            $existingSheet = AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('project_id', $request->project_id)
                ->where('month', $request->month)
                ->where('status', '!=', 'rejected')  // 排除驳回状态
                ->first();

            if ($existingSheet) {
                return response()->json([
                    'success' => false,
                    'message' => '该项目的该月份考勤表已存在'
                ], 422);
            }

            // 获取项目信息
            $project = Project::find($request->project_id);
            
            // 检查是否需要上传考勤依据
            if ($project && $project->requires_attendance_basis) {
                $basisExists = \App\Models\BasisRecord::where('project_id', $request->project_id)
                    ->where('month', $request->month)
                    ->where('type', 'attendance')
                    ->exists();
                
                if (!$basisExists) {
                    return response()->json([
                        'success' => false,
                        'message' => '该项目设置了需要上传考勤依据，请先在【依据管理-考勤依据】中上传本月的考勤依据后再创建考勤表'
                    ], 422);
                }
            }

            // 获取项目员工数量（只统计合同状态为"在职"的员工）
            $totalEmployees = $project ? $project->employees()
                ->where('employees.account_set_id', $accountSetId)
                ->where('employee_projects.status', 'active')
                // 过滤：只选择合同状态为"在职"的员工
                ->whereIn('employees.contract_status', ['active', 'approved'])
                ->count() : 0;

            $sheet = AttendanceSheet::create([
                'account_set_id' => $accountSetId,
                'project_id' => $request->project_id,
                'month' => $request->month,
                'work_days' => $request->work_days,
                'total_employees' => $totalEmployees,
                'notes' => $request->notes,
                'created_by' => $user->id,
                'status' => AttendanceSheet::STATUS_DRAFT
            ]);

            return response()->json([
                'success' => true,
                'message' => '创建成功',
                'data' => $sheet->load(['project', 'creator'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新考勤表
     */
    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('attendance.edit')) {
            return $response;
        }
        
        try {
            $user = Auth::user();
            $accountSetId = $this->getAccountSetId($request);

            $sheet = AttendanceSheet::byAccountSet($accountSetId)->findOrFail($id);

            if (!$sheet->canEdit()) {
                return response()->json([
                    'success' => false,
                    'message' => '当前状态不允许编辑'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'work_days' => 'required|integer|min:1|max:31',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $sheet->update([
                'work_days' => $request->work_days,
                'notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'message' => '更新成功',
                'data' => $sheet->load(['project', 'creator'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取考勤表详情
     */
    public function show(Request $request, $id)
    {
        if ($response = $this->checkPermission('attendance.view')) {
            return $response;
        }
        
        try {
            $accountSetId = $this->getAccountSetId($request);

            $sheet = AttendanceSheet::byAccountSet($accountSetId)
                ->with(['project', 'creator', 'submitter', 'approver'])
                ->findOrFail($id);

            // 获取考勤数据
            $attendanceData = $this->getAttendanceData($id);
            
            // 获取考勤统计
            $attendanceStats = AttendanceStatistics::byAttendanceSheet($id)
                ->with(['employee'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'sheet' => $sheet,
                    'attendance_data' => $attendanceData,
                    'attendance_stats' => $attendanceStats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取考勤表详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交考勤表
     */
    public function submit($id, Request $request)
    {
        try {
            $user = Auth::user();
            $accountSetId = $this->getAccountSetId($request);

            $sheet = AttendanceSheet::byAccountSet($accountSetId)->findOrFail($id);

            if (!$sheet->canSubmit()) {
                return response()->json([
                    'success' => false,
                    'message' => '当前状态不允许提交'
                ], 422);
            }

            DB::beginTransaction();

            // 处理提交数据，包括附件
            $submitData = $request->only(['notes', 'attachments']);
            
            // 获取盖章方式，默认为线上
            $stampMethod = $request->input('stamp_method', 'online');
            
            // 调试日志
            Log::info('考勤提交 - 接收到的盖章方式', [
                'stamp_method' => $stampMethod,
                'all_input' => $request->all()
            ]);
            
            // 确保附件数据正确保存到考勤表
            if (isset($submitData['attachments'])) {
                $sheet->attachments = json_encode($submitData['attachments']);
            }
            
            // 提交考勤表
            $sheet->submit($user->id, $submitData);

            // 发起审批流程（传递盖章方式）
            $this->initiateApprovalProcess($sheet, $user, $accountSetId, $stampMethod);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '提交成功，审批流程已发起'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('提交考勤表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => '提交失败: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * 拒绝考勤表
     */
    public function reject(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $accountSetId = $this->getAccountSetId($request);

            $sheet = AttendanceSheet::byAccountSet($accountSetId)->findOrFail($id);

            if (!$sheet->canApprove()) {
                return response()->json([
                    'success' => false,
                    'message' => '当前状态不允许拒绝'
                ], 422);
            }

            $sheet->reject($user->id);

            return response()->json([
                'success' => true,
                'message' => '拒绝成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '拒绝失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除考勤表
     */
    public function destroy(Request $request, $id)
    {
        if ($response = $this->checkPermission('attendance.delete')) {
            return $response;
        }
        
        try {
            $accountSetId = $this->getAccountSetId($request);

            $sheet = AttendanceSheet::byAccountSet($accountSetId)->findOrFail($id);

            // 检查是否在审批中，审批中的数据不允许删除
            if ($sheet->status === AttendanceSheet::STATUS_SUBMITTED) {
                return response()->json([
                    'success' => false,
                    'message' => '该考勤表正在审批中，不允许删除'
                ], 400);
            }

            // 删除关联的考勤记录和统计数据
            DB::transaction(function () use ($sheet) {
                // 删除考勤记录
                AttendanceRecord::where('attendance_sheet_id', $sheet->id)->delete();
                
                // 删除考勤统计
                AttendanceStatistics::where('attendance_sheet_id', $sheet->id)->delete();
                
                // 删除考勤表
                $sheet->delete();
            });

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除考勤表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上传考勤表附件
     */
    public function uploadFiles(Request $request, $id)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);

            $sheet = AttendanceSheet::byAccountSet($accountSetId)->findOrFail($id);

            // 验证文件
            $validator = Validator::make($request->all(), [
                'files' => 'required|array|max:10',
                'files.*' => 'required|file|max:10240' // 10MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '文件验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uploadedFiles = [];
            $files = $request->file('files');

            foreach ($files as $index => $file) {
                // 先获取文件信息（在移动之前）
                $originalName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                $fileExtension = $file->getClientOriginalExtension();
                
                // 生成唯一文件名
                $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
                
                // 直接存储到 public/attendance-attachments 目录
                $destinationPath = public_path('attendance-attachments');
                
                // 确保目录存在
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                // 移动文件
                $file->move($destinationPath, $fileName);
                
                // 相对路径
                $relativePath = 'attendance-attachments/' . $fileName;
                
                $uploadedFiles[] = [
                    'file_path' => $relativePath,
                    'original_name' => $originalName,
                    'file_size' => $fileSize,
                    'file_type' => $fileExtension,
                    'url' => asset($relativePath)
                ];

                Log::info('考勤表附件上传成功', [
                    'attendance_sheet_id' => $id,
                    'file_path' => $relativePath,
                    'original_name' => $originalName
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '文件上传成功',
                'data' => [
                    'files' => $uploadedFiles
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('考勤表附件上传失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '文件上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 保存考勤数据
     */
    public function saveAttendanceData(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $accountSetId = $this->getAccountSetId($request);

            $sheet = AttendanceSheet::byAccountSet($accountSetId)->findOrFail($id);

            if (!$sheet->canEdit()) {
                return response()->json([
                    'success' => false,
                    'message' => '当前状态不允许编辑'
                ], 422);
            }

            $attendanceData = $request->input('attendance_data', []);
            if (is_string($attendanceData)) {
                $decoded = json_decode($attendanceData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $attendanceData = $decoded;
                }
            }

            if (!is_array($attendanceData) || empty($attendanceData)) {
                return response()->json([
                    'success' => false,
                    'message' => '考勤明细不能为空，请至少填写1天有效考勤状态'
                ], 422);
            }

            $recordMap = [];
            foreach ($attendanceData as $employeeData) {
                if (!is_array($employeeData)) {
                    continue;
                }

                $employeeId = (int)($employeeData['employee_id'] ?? 0);
                if ($employeeId <= 0) {
                    continue;
                }

                $attendance = $employeeData['attendance'] ?? [];
                if (is_string($attendance)) {
                    $decoded = json_decode($attendance, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $attendance = $decoded;
                    }
                }
                if (!is_array($attendance)) {
                    continue;
                }

                foreach ($attendance as $day => $statusRaw) {
                    $dayNum = (int)$day;
                    if ($dayNum < 1 || $dayNum > (int)$sheet->work_days) {
                        continue;
                    }

                    $status = $this->normalizeAttendanceStatus($statusRaw);
                    if ($status === null) {
                        continue;
                    }

                    // 同一员工同一天重复值以最后一次为准
                    $recordMap[$employeeId . '-' . $dayNum] = [
                        'employee_id' => $employeeId,
                        'day_of_month' => $dayNum,
                        'status' => $status,
                    ];
                }
            }

            if (empty($recordMap)) {
                return response()->json([
                    'success' => false,
                    'message' => '未检测到有效考勤明细，请至少填写1天有效考勤状态'
                ], 422);
            }

            DB::transaction(function () use ($sheet, $recordMap, $user, $accountSetId) {
                // 删除现有考勤记录
                AttendanceRecord::where('attendance_sheet_id', $sheet->id)->delete();

                // 创建新的考勤记录
                foreach ($recordMap as $record) {
                    AttendanceRecord::create([
                        'account_set_id' => $accountSetId,
                        'attendance_sheet_id' => $sheet->id,
                        'employee_id' => $record['employee_id'],
                        'project_id' => $sheet->project_id,
                        'date' => $sheet->month . '-' . str_pad($record['day_of_month'], 2, '0', STR_PAD_LEFT),
                        'day_of_month' => $record['day_of_month'],
                        'status' => $record['status'],
                        'created_by' => $user->id
                    ]);
                }
                
                // 生成考勤统计
                $this->generateAttendanceStatistics($sheet->id);
            });

            return response()->json([
                'success' => true,
                'message' => '保存成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '保存失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 兼容前后端不同状态值，统一为系统内部状态
     */
    private function normalizeAttendanceStatus($statusRaw): ?string
    {
        if ($statusRaw === null) {
            return null;
        }

        $raw = trim((string)$statusRaw);
        if ($raw === '') {
            return null;
        }

        $value = mb_strtolower($raw, 'UTF-8');

        $map = [
            'normal' => 'normal',
            'late' => 'late',
            'early' => 'early',
            'absent' => 'absent',
            'leave' => 'leave',
            'off' => 'off',
            '正常' => 'normal',
            '出勤' => 'normal',
            '迟到' => 'late',
            '早退' => 'early',
            '缺勤' => 'absent',
            '请假' => 'leave',
            '调休' => 'off',
            '休息' => 'off',
            '1' => 'normal',
        ];

        return $map[$value] ?? null;
    }

    /**
     * 获取项目员工列表
     */
    public function getProjectEmployees(Request $request, $projectId)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);

            // 通过项目关联获取员工
            $project = Project::find($projectId);
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => '项目不存在'
                ], 404);
            }

            $employees = $project->employees()
                ->where('employees.account_set_id', $accountSetId)
                ->where('employee_projects.status', 'active')
                // 过滤：只选择合同状态为"在职"的员工
                ->whereIn('employees.contract_status', ['active', 'approved'])
                ->select('employees.id', 'employees.name', 'employees.id_number')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employees
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取员工列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取考勤数据
     */
    private function getAttendanceData($sheetId)
    {
        $sheet = AttendanceSheet::find($sheetId);
        if (!$sheet) {
            return [];
        }

        // 通过项目关联获取员工
        $project = Project::find($sheet->project_id);
        if (!$project) {
            return [];
        }

        $employees = $project->employees()
            ->where('employees.account_set_id', $sheet->account_set_id)
            ->where('employee_projects.status', 'active')
            // 过滤：只选择合同状态为"在职"的员工
            ->whereIn('employees.contract_status', ['active', 'approved'])
            ->get();

        $records = AttendanceRecord::where('attendance_sheet_id', $sheetId)
            ->get()
            ->groupBy('employee_id');

        $attendanceData = [];

        foreach ($employees as $employee) {
            $employeeRecords = $records->get($employee->id, collect());
            $attendance = [];

            // 初始化所有天数的考勤状态
            for ($day = 1; $day <= $sheet->work_days; $day++) {
                $record = $employeeRecords->where('day_of_month', $day)->first();
                $attendance[$day] = $record ? $record->status : '';
            }

            $attendanceData[] = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'id_number' => $employee->id_number,
                'attendance' => $attendance
            ];
        }

        return $attendanceData;
    }

    /**
     * 生成考勤统计
     */
    private function generateAttendanceStatistics($sheetId)
    {
        $sheet = AttendanceSheet::find($sheetId);
        if (!$sheet) {
            return;
        }

        // 通过项目关联获取员工
        $project = Project::find($sheet->project_id);
        if (!$project) {
            return;
        }

        $employees = $project->employees()
            ->where('employees.account_set_id', $sheet->account_set_id)
            ->where('employee_projects.status', 'active')
            // 过滤：只选择合同状态为"在职"的员工
            ->whereIn('employees.contract_status', ['active', 'approved'])
            ->get();

        foreach ($employees as $employee) {
            AttendanceStatistics::generateStatistics($sheetId, $employee->id);
        }
    }

    /**
     * 发起审批流程
     */
    private function initiateApprovalProcess($sheet, $user, $accountSetId, $stampMethod = 'online')
    {
        // 调试日志
        Log::info('考勤审批流程 - 创建审批实例', [
            'sheet_id' => $sheet->id,
            'stamp_method' => $stampMethod
        ]);
        
        // 获取审批人员配置（跳过经办，从第二个审批节点开始）
        $approvers = DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('approval_level', '>', 1) // 跳过经办（级别1）
            ->orderBy('approval_level')
            ->get();

        if ($approvers->isEmpty()) {
            throw new \Exception('未找到审批人员配置');
        }

        // 使用现有的审批系统创建审批实例
        $instance = \App\Models\ApprovalInstance::create([
            'account_set_id' => $accountSetId,
            'business_type' => '考勤申请',
            'business_id' => $sheet->id,
            'current_step' => 2, // 从第二个审批节点开始
            'total_steps' => $approvers->count() + 1, // 包括经办
            'status' => 'pending',
            'created_by' => $user->id,
            'stamp_method' => $stampMethod,  // 保存盖章方式
        ]);
        
        // 调试日志 - 确认保存结果
        Log::info('考勤审批流程 - 审批实例已创建', [
            'instance_id' => $instance->id,
            'saved_stamp_method' => $instance->stamp_method
        ]);

        // 添加考勤表附件到审批实例
        if ($sheet->attachments) {
            $attachments = is_string($sheet->attachments) ? json_decode($sheet->attachments, true) : $sheet->attachments;
            if (is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    \App\Models\ApprovalAttachment::create([
                        'instance_id' => $instance->id,
                        'file_path' => $attachment['file_path'] ?? '',
                        'file_name' => $attachment['original_name'] ?? '考勤附件',
                        'file_size' => $attachment['file_size'] ?? 0,
                        'file_type' => $attachment['file_type'] ?? '',
                    ]);
                }
            }
        }

        // 创建审批记录（经办自动通过）
        \App\Models\ApprovalRecord::create([
            'instance_id' => $instance->id,
            'step_order' => 1,
            'step_name' => '经办',
            'approver_id' => $user->id,
            'approver_name' => $user->name,
            'status' => 'approved',
            'comment' => '经办提交，自动通过',
            'approved_at' => now(),
        ]);

        // 为第一个审批人创建待办记录
        $firstApprover = $approvers->first();
        $approverUser = \App\Models\User::find($firstApprover->user_id);
        
        \App\Models\ApprovalRecord::create([
            'instance_id' => $instance->id,
            'step_order' => 2,
            'step_name' => $firstApprover->approval_level_name,
            'approver_id' => $firstApprover->user_id,
            'approver_name' => $approverUser->name,
            'status' => 'pending',
            'comment' => null,
            'approved_at' => null,
        ]);

        // 如果有更多审批级别，继续创建记录
        $stepOrder = 3;
        foreach ($approvers->skip(1) as $approver) {
            $approverUser = \App\Models\User::find($approver->user_id);
            
            \App\Models\ApprovalRecord::create([
                'instance_id' => $instance->id,
                'step_order' => $stepOrder,
                'step_name' => $approver->approval_level_name,
                'approver_id' => $approver->user_id,
                'approver_name' => $approverUser->name,
                'status' => 'waiting',
                'comment' => null,
                'approved_at' => null,
            ]);
            $stepOrder++;
        }

        Log::info("考勤表审批流程已发起", [
            'attendance_sheet_id' => $sheet->id,
            'instance_id' => $instance->id,
            'current_approver' => $firstApprover->user_id,
            'approval_level' => $firstApprover->approval_level
        ]);
    }

    }
