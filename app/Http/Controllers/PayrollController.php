<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\AttendanceSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
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
     * 获取可以生成工资表的项目列表
     * （只返回本月考勤已审批的项目）
     */
    public function getAvailableProjects(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            // 获取期间参数（格式：2025-10）
            $period = $request->input('period');
            
            if (!$period) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择工资期间'
                ], 400);
            }
            
            // 查询该期间考勤已审批的项目ID
            $approvedProjectIds = AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('month', $period)
                ->where('status', AttendanceSheet::STATUS_APPROVED)
                ->pluck('project_id')
                ->toArray();
            
            if (empty($approvedProjectIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => '该期间暂无考勤已审批的项目'
                ]);
            }
            
            // 获取这些项目的详细信息
            $projects = Project::where('account_set_id', $accountSetId)
                ->whereIn('id', $approvedProjectIds)
                ->select('id', 'name', 'code', 'status')
                ->get()
                ->map(function ($project) use ($period) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'code' => $project->code,
                        'status' => $project->status,
                        'period' => $period,
                        'can_create_payroll' => true // 考勤已审批，可以生成工资表
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $projects,
                'count' => $projects->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取可用项目失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取所有项目列表（带考勤审批状态标识）
     * 用于下拉选择时显示哪些可选，哪些不可选
     */
    public function getProjectsWithApprovalStatus(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $period = $request->input('period');
            
            if (!$period) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择工资期间'
                ], 400);
            }
            
            // 调试日志
            \Log::info('获取工资表项目列表', [
                'account_set_id' => $accountSetId,
                'period' => $period
            ]);
            
            // 获取所有项目（包含是否需要考勤字段）
            $allProjects = Project::where('account_set_id', $accountSetId)
                ->select('id', 'name', 'code', 'status', 'require_attendance', 'requires_attendance')
                ->get();
            
            \Log::info('查询到的项目数量', ['count' => $allProjects->count()]);
            
            // 获取该期间考勤已审批的项目ID
            $attendanceSheets = AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('month', $period)
                ->get();
            
            // 详细日志：所有考勤表
            \Log::info('该期间的所有考勤表', [
                'period' => $period,
                'account_set_id' => $accountSetId,
                'total_count' => $attendanceSheets->count(),
                'sheets' => $attendanceSheets->map(function($sheet) {
                    return [
                        'id' => $sheet->id,
                        'project_id' => $sheet->project_id,
                        'status' => $sheet->status,
                        'month' => $sheet->month
                    ];
                })->toArray()
            ]);
            
            $approvedProjectIds = $attendanceSheets
                ->where('status', AttendanceSheet::STATUS_APPROVED)
                ->pluck('project_id')
                ->toArray();
            
            \Log::info('考勤已审批的项目ID', [
                'approved_project_ids' => $approvedProjectIds,
                'STATUS_APPROVED' => AttendanceSheet::STATUS_APPROVED
            ]);
            
            // 为每个项目添加考勤审批状态
            $projects = $allProjects->map(function ($project) use ($approvedProjectIds, $period) {
                // 判断项目是否需要考勤（兼容两个字段名）
                // 优先使用 require_attendance，如果不存在或为null，则使用 requires_attendance，默认为true
                if (isset($project->require_attendance)) {
                    // 如果 require_attendance 字段存在，使用它的值（0或1转为布尔值）
                    $requireAttendance = (bool) $project->require_attendance;
                } elseif (isset($project->requires_attendance)) {
                    // 如果 requires_attendance 字段存在，使用它的值
                    $requireAttendance = (bool) $project->requires_attendance;
                } else {
                    // 都不存在，默认需要考勤
                    $requireAttendance = true;
                }
                
                // 如果不需要考勤，则直接可以创建工资表
                // 如果需要考勤，则必须考勤已审批才能创建
                $isApproved = in_array($project->id, $approvedProjectIds);
                $canCreate = !$requireAttendance || $isApproved;
                
                // 生成提示标签
                $label = $project->name;
                if ($requireAttendance && !$isApproved) {
                    $label .= ' (考勤未审批)';
                } elseif (!$requireAttendance) {
                    $label .= ' (无需考勤)';
                }
                
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'status' => $project->status,
                    'period' => $period,
                    'require_attendance' => $requireAttendance,  // 是否需要考勤
                    'attendance_approved' => $isApproved,        // 考勤是否已审批
                    'can_create_payroll' => $canCreate,          // 是否可以生成工资表
                    'disabled' => !$canCreate,                   // 是否禁用（前端使用）
                    'label' => $label                            // 带提示的标签
                ];
            });
            
            // 统计可创建工资表的项目数量
            $canCreateCount = $projects->filter(function ($project) {
                return $project['can_create_payroll'];
            })->count();
            
            // 调试日志
            \Log::info('项目列表处理完成', [
                'total_projects' => $projects->count(),
                'can_create_count' => $canCreateCount,
                'projects' => $projects->toArray()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $projects,
                'approved_count' => count($approvedProjectIds),
                'can_create_count' => $canCreateCount,  // 可创建工资表的项目数量
                'total_count' => $projects->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取项目列表失败: ' . $e->getMessage()
            ], 500);
        }
    }
}

