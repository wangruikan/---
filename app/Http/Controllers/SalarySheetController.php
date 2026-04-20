<?php

namespace App\Http\Controllers;

use App\Models\SalarySheet;
use App\Models\AttendanceSheet;
use App\Models\Project;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SalarySheetController extends Controller
{
    /**
     * 获取工资表列表
     */
    public function index(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $query = SalarySheet::where('account_set_id', $accountSetId)
                ->with(['project', 'attendanceSheet'])
                ->orderBy('created_at', 'desc');

            // 项目筛选
            if ($request->has('project_id') && $request->project_id) {
                $query->where('project_id', $request->project_id);
            }

            // 月份筛选
            if ($request->has('month') && $request->month) {
                $query->where('month', $request->month);
            }

            // 状态筛选
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            $salarySheets = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $salarySheets->items(),
                'total' => $salarySheets->total(),
                'current_page' => $salarySheets->currentPage(),
                'per_page' => $salarySheets->perPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('获取工资表列表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建工资表
     */
    public function store(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            $userId = $request->user()->id;

            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'month' => 'required|date_format:Y-m',
                'attendance_sheet_id' => 'required|exists:attendance_sheets,id',
                'notes' => 'nullable|string'
            ]);

            // 检查考勤表是否已审批通过
            $attendanceSheet = AttendanceSheet::where('id', $validated['attendance_sheet_id'])
                ->where('status', 'approved')
                ->first();

            if (!$attendanceSheet) {
                return response()->json([
                    'success' => false,
                    'message' => '只能基于已审批通过的考勤表创建工资表'
                ], 400);
            }

            // 检查是否已存在相同项目、月份的工资表（排除已驳回的）
            $existingSheet = SalarySheet::where('account_set_id', $accountSetId)
                ->where('project_id', $validated['project_id'])
                ->where('month', $validated['month'])
                ->where('status', '!=', 'rejected') // 排除已驳回的记录
                ->first();

            if ($existingSheet) {
                return response()->json([
                    'success' => false,
                    'message' => '该月份已存在工资表'
                ], 400);
            }

            DB::beginTransaction();

            // 创建工资表
            $salarySheet = SalarySheet::create([
                'account_set_id' => $accountSetId,
                'project_id' => $validated['project_id'],
                'month' => $validated['month'],
                'attendance_sheet_id' => $validated['attendance_sheet_id'],
                'total_employees' => $attendanceSheet->total_employees,
                'total_amount' => 0,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // 获取项目员工列表
            $employees = Employee::whereHas('projects', function ($query) use ($validated) {
                $query->where('project_id', $validated['project_id']);
            })->get();

            // 创建员工工资记录
            foreach ($employees as $employee) {
                EmployeeSalary::create([
                    'salary_sheet_id' => $salarySheet->id,
                    'employee_id' => $employee->id,
                    'basic_salary' => $employee->basic_salary ?? 0,
                    'overtime_pay' => 0,
                    'bonus' => 0,
                    'deductions' => 0,
                    'net_salary' => $employee->basic_salary ?? 0,
                    'notes' => null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '创建成功',
                'data' => $salarySheet->load(['project', 'attendanceSheet'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('创建工资表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取工资表详情
     */
    public function show(Request $request, $id)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $salarySheet = SalarySheet::where('account_set_id', $accountSetId)
                ->with(['project', 'attendanceSheet', 'employeeSalaries.employee'])
                ->findOrFail($id);

            // 获取考勤数据
            $attendanceData = [];
            if ($salarySheet->attendanceSheet) {
                $attendanceData = $this->getAttendanceData($salarySheet->attendanceSheet->id);
            }

            // 获取详细考勤数据（用于审批人查看）
            $detailedAttendanceData = [];
            if ($salarySheet->attendanceSheet) {
                $detailedAttendanceData = $this->getDetailedAttendanceData($salarySheet->attendanceSheet->id);
            }

            // 格式化工资数据
            $salaryData = $salarySheet->employeeSalaries->map(function ($employeeSalary) {
                return [
                    'id' => $employeeSalary->id,
                    'employee_id' => $employeeSalary->employee_id,
                    'employee_name' => $employeeSalary->employee->name ?? 'N/A',
                    'basic_salary' => $employeeSalary->basic_salary,
                    'overtime_pay' => $employeeSalary->overtime_pay,
                    'bonus' => $employeeSalary->bonus,
                    'deductions' => $employeeSalary->deductions,
                    'net_salary' => $employeeSalary->net_salary,
                    'notes' => $employeeSalary->notes,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'sheet' => $salarySheet,
                    'salary_data' => $salaryData,
                    'attendance_data' => $attendanceData,
                    'detailed_attendance_data' => $detailedAttendanceData,
                    'attendance_sheet' => $salarySheet->attendanceSheet,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('获取工资表详情失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新工资表
     */
    public function update(Request $request, $id)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $salarySheet = SalarySheet::where('account_set_id', $accountSetId)
                ->findOrFail($id);

            $validated = $request->validate([
                'notes' => 'nullable|string'
            ]);

            $salarySheet->update($validated);

            return response()->json([
                'success' => true,
                'message' => '更新成功',
                'data' => $salarySheet
            ]);
        } catch (\Exception $e) {
            Log::error('更新工资表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交工资表审批
     */
    public function submit(Request $request, $id)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $salarySheet = SalarySheet::where('account_set_id', $accountSetId)
                ->findOrFail($id);

            if ($salarySheet->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => '只能提交草稿状态的工资表'
                ], 400);
            }

            // 发起审批流程（跳过经办，直接到二级审批）
            $this->initiateApprovalProcess($salarySheet);

            $salarySheet->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '提交成功'
            ]);
        } catch (\Exception $e) {
            Log::error('提交工资表审批失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 审批工资表
     */
    public function approve(Request $request, $id)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $salarySheet = SalarySheet::where('account_set_id', $accountSetId)
                ->findOrFail($id);

            if ($salarySheet->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => '只能审批已提交的工资表'
                ], 400);
            }

            // 完成审批流程
            $this->completeApprovalProcess($salarySheet);

            $salarySheet->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '审批成功'
            ]);
        } catch (\Exception $e) {
            Log::error('审批工资表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '审批失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 拒绝工资表
     */
    public function reject(Request $request, $id)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $salarySheet = SalarySheet::where('account_set_id', $accountSetId)
                ->findOrFail($id);

            if ($salarySheet->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => '只能拒绝已提交的工资表'
                ], 400);
            }

            $validated = $request->validate([
                'reason' => 'required|string|min:2'
            ]);

            // 完成审批流程
            $this->completeApprovalProcess($salarySheet, 'rejected');

            $salarySheet->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $validated['reason'],
            ]);

            return response()->json([
                'success' => true,
                'message' => '拒绝成功'
            ]);
        } catch (\Exception $e) {
            Log::error('拒绝工资表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '拒绝失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 保存工资数据
     */
    public function saveSalaryData(Request $request, $id)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $salarySheet = SalarySheet::where('account_set_id', $accountSetId)
                ->findOrFail($id);

            if ($salarySheet->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => '只能编辑草稿状态的工资表'
                ], 400);
            }

            $validated = $request->validate([
                'salary_data' => 'required|array',
                'salary_data.*.id' => 'required|exists:employee_salaries,id',
                'salary_data.*.basic_salary' => 'required|numeric|min:0',
                'salary_data.*.overtime_pay' => 'required|numeric|min:0',
                'salary_data.*.bonus' => 'required|numeric|min:0',
                'salary_data.*.deductions' => 'required|numeric|min:0',
                'salary_data.*.notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $totalAmount = 0;
            foreach ($validated['salary_data'] as $salaryData) {
                $netSalary = $salaryData['basic_salary'] + $salaryData['overtime_pay'] + $salaryData['bonus'] - $salaryData['deductions'];
                $totalAmount += $netSalary;

                EmployeeSalary::where('id', $salaryData['id'])
                    ->where('salary_sheet_id', $id)
                    ->update([
                        'basic_salary' => $salaryData['basic_salary'],
                        'overtime_pay' => $salaryData['overtime_pay'],
                        'bonus' => $salaryData['bonus'],
                        'deductions' => $salaryData['deductions'],
                        'net_salary' => $netSalary,
                        'notes' => $salaryData['notes'] ?? null,
                    ]);
            }

            $salarySheet->update(['total_amount' => $totalAmount]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '保存成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('保存工资数据失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '保存失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取已审批的考勤表
     */
    public function getApprovedAttendanceSheets(Request $request)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $query = AttendanceSheet::where('account_set_id', $accountSetId)
                ->where('status', 'approved')
                ->with('project');

            if ($request->has('project_id') && $request->project_id) {
                $query->where('project_id', $request->project_id);
            }

            if ($request->has('month') && $request->month) {
                $query->where('month', $request->month);
            }

            $attendanceSheets = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $attendanceSheets
            ]);
        } catch (\Exception $e) {
            Log::error('获取已审批考勤表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出工资表
     */
    public function export(Request $request, $id)
    {
        try {
            $accountSetId = $this->getAccountSetId($request);
            
            $salarySheet = SalarySheet::where('account_set_id', $accountSetId)
                ->with(['project', 'employeeSalaries.employee'])
                ->findOrFail($id);

            // 这里实现导出逻辑
            // 暂时返回成功消息
            return response()->json([
                'success' => true,
                'message' => '导出功能开发中'
            ]);
        } catch (\Exception $e) {
            Log::error('导出工资表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取考勤数据
     */
    private function getAttendanceData($attendanceSheetId)
    {
        try {
            // 这里应该从考勤表获取统计数据
            // 暂时返回模拟数据
            return [];
        } catch (\Exception $e) {
            Log::error('获取考勤数据失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取详细考勤数据
     */
    private function getDetailedAttendanceData($attendanceSheetId)
    {
        try {
            // 这里应该从考勤表获取详细数据
            // 暂时返回模拟数据
            return [];
        } catch (\Exception $e) {
            Log::error('获取详细考勤数据失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 发起审批流程
     */
    private function initiateApprovalProcess($salarySheet)
    {
        // 这里实现审批流程逻辑
        // 跳过经办，直接到二级审批
        Log::info("发起工资表审批流程: {$salarySheet->id}");
    }

    /**
     * 完成审批流程
     */
    private function completeApprovalProcess($salarySheet, $result = 'approved')
    {
        // 这里实现审批流程完成逻辑
        Log::info("完成工资表审批流程: {$salarySheet->id}, 结果: {$result}");
    }

    /**
     * 获取账套ID
     */
    private function getAccountSetId(Request $request)
    {
        return $request->get('current_account_set_id', 1);
    }
}
