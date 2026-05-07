<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use App\Models\Employee;
use App\Models\Project;
use App\Models\AttendanceSheet;
use App\Models\InsurancePersonnel;
use App\Models\InsuranceCompensationRecord;
use App\Models\SocialSecurityType;
use App\Models\HousingFundConfig;
use App\Models\PersonnelChangeRequest;
use App\Services\LargeMedicalPaymentCycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Traits\ChecksPermission;

class SalaryController extends Controller
{
    use ChecksPermission;
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('salaries.view')) {
            return $response;
        }
        
        $query = Salary::query();
        
        // 调试：记录请求参数
        \Log::info('Salary Index Request', $request->all());
        
        // 【账套过滤】
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }
        
        if ($request->has('project_id') && $request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }
        
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // 兼容历史数据：为旧草稿记录补充批次号，避免重复创建后仍被聚合成一条
        if ($currentAccountSetId) {
            $this->backfillLegacyDraftBatchIds(
                intval($currentAccountSetId),
                $request->input('project_id'),
                $request->input('month')
            );
        }
        
        // 按项目+月份+审批ID+草稿批次分组
        // 草稿工资表（salary_approval_id = NULL）通过 seq_number 拆分，确保可重复创建并独立显示
        $salaries = $query->with(['project:id,name'])
                          ->selectRaw('project_id, month, status, salary_approval_id, seq_number,
                                      MAX(seq_number) as draft_batch_id,
                                      MIN(period_start) as period_start,
                                      MIN(period_end) as period_end,
                                      COUNT(DISTINCT employee_id) as employee_count,
                                      SUM(gross_salary) as total_gross_salary,
                                      SUM(net_salary) as total_net_salary,
                                      MIN(created_at) as created_at,
                                      MAX(approved_at) as approved_at')
                          ->groupBy('project_id', 'month', 'status', 'salary_approval_id', 'seq_number')
                          ->orderBy('month', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->get();
        
        \Log::info('工资表查询结果', [
            'count' => $salaries->count(),
            'data' => $salaries->map(function($s) {
                return [
                    'project_id' => $s->project_id,
                    'month' => $s->month,
                    'status' => $s->status,
                    'employee_count' => $s->employee_count,
                    'salary_approval_id' => $s->salary_approval_id,
                    'draft_batch_id' => $s->draft_batch_id,
                ];
            })->toArray()
        ]);
        
        // 添加项目名称和审批状态
        $salaries->transform(function ($item) use ($currentAccountSetId) {
            \Log::info('处理工资表记录 - 开始', [
                'project_id' => $item->project_id,
                'month' => $item->month,
                'status' => $item->status,
                'salary_approval_id' => $item->salary_approval_id,
                'draft_batch_id' => $item->draft_batch_id,
                'has_approval_id' => $item->salary_approval_id ? true : false,
            ]);
            \Log::info('处理工资表记录', [
                'project_id' => $item->project_id,
                'month' => $item->month,
                'status' => $item->status,
                'salary_approval_id' => $item->salary_approval_id,
                'draft_batch_id' => $item->draft_batch_id,
            ]);
            
            // 获取该项目+月份的所有员工的部门字段，提取项目名称
            $departments = Salary::where('project_id', $item->project_id)
                                 ->where('month', $item->month)
                                 ->where('account_set_id', $currentAccountSetId)
                                 ->where(function($q) use ($item) {
                                     if ($item->salary_approval_id) {
                                         $q->where('salary_approval_id', $item->salary_approval_id);
                                     } else {
                                         $q->whereNull('salary_approval_id');
                                         if ($item->draft_batch_id !== null) {
                                             $q->where('seq_number', intval($item->draft_batch_id));
                                         } else {
                                             $q->whereNull('seq_number');
                                         }
                                     }
                                 })
                                 ->pluck('department')
                                 ->unique()
                                 ->filter()
                                 ->toArray();
            
            // 合并所有部门字段，提取唯一的项目名称
            $allProjectNames = [];
            foreach ($departments as $dept) {
                if ($dept) {
                    $names = array_map('trim', explode('、', $dept));
                    $allProjectNames = array_merge($allProjectNames, $names);
                }
            }
            $allProjectNames = array_unique($allProjectNames);
            $allProjectNames = array_filter($allProjectNames, function($name) {
                return $name !== '未知部门';
            });
            
            // 如果没有找到项目名称，使用 project 关系
            if (empty($allProjectNames)) {
                $item->project_name = $item->project ? $item->project->name : '';
            } else {
                $item->project_name = implode('、', $allProjectNames);
            }
            
            $item->draft_batch_id = $item->draft_batch_id !== null ? intval($item->draft_batch_id) : null;

            // 如果有 salary_approval_id，查找对应的审批记录
            if ($item->salary_approval_id) {
                $approval = \App\Models\SalaryApproval::find($item->salary_approval_id);
                
                \Log::info('查找审批记录', [
                    'salary_approval_id' => $item->salary_approval_id,
                    'found' => $approval ? true : false,
                    'approval_status' => $approval ? $approval->status : null,
                ]);
                
                if ($approval) {
                    $item->has_approval = true;
                    $item->approval_status = $approval->status;
                    $item->approval_type = $approval->approval_type;
                    $item->attachments = $approval->attachments;
                    
                    // 检查是否已经发起过付款申请
                    $paymentRequest = \App\Models\PaymentRequest::where('salary_approval_id', $approval->id)
                                                                ->where('payment_type', 'salary')
                                                                ->first();
                    if ($paymentRequest) {
                        $item->has_payment_request = true;
                        $item->payment_request_status = $paymentRequest->status;
                    } else {
                        $item->has_payment_request = false;
                        $item->payment_request_status = null;
                    }
                } else {
                    $item->has_approval = false;
                    $item->approval_status = null;
                    $item->approval_type = null;
                    $item->has_payment_request = false;
                    $item->payment_request_status = null;
                    $item->attachments = [];
                }
            } else {
                // 没有 salary_approval_id，说明是新创建的工资表，还没有提交审批
                \Log::info('新创建的工资表，没有审批记录');
                
                $item->has_approval = false;
                $item->approval_status = null;
                $item->approval_type = null;
                $item->has_payment_request = false;
                $item->payment_request_status = null;
                $item->attachments = [];
            }
            
            return $item;
        });
        
        // 手动分页
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 50);
        $total = $salaries->count();
        $data = $salaries->forPage($page, $perPage)->values();
        
        return response()->json([
            'success' => true,
            'data' => [
                'data' => $data,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ]
        ]);
    }

    private function backfillLegacyDraftBatchIds(int $accountSetId, $projectId = null, $month = null): void
    {
        $legacyDrafts = Salary::query()
            ->where('account_set_id', $accountSetId)
            ->where('status', 'draft')
            ->whereNull('salary_approval_id')
            ->whereNull('seq_number')
            ->when($projectId, function ($query) use ($projectId) {
                $query->where('project_id', $projectId);
            })
            ->when($month, function ($query) use ($month) {
                $query->where('month', $month);
            })
            ->orderBy('project_id')
            ->orderBy('month')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'project_id', 'month', 'created_at']);

        if ($legacyDrafts->isEmpty()) {
            return;
        }

        $groups = $legacyDrafts->groupBy(function ($item) {
            $createdAt = $item->created_at
                ? Carbon::parse($item->created_at)->format('Y-m-d H:i:s')
                : '1970-01-01 00:00:00';
            return $item->project_id . '|' . $item->month . '|' . $createdAt;
        });

        $nextBatchByScope = [];
        foreach ($groups as $group) {
            $first = $group->first();
            if (!$first) {
                continue;
            }

            $scopeKey = $first->project_id . '|' . $first->month;
            if (!array_key_exists($scopeKey, $nextBatchByScope)) {
                $maxSeq = Salary::where('account_set_id', $accountSetId)
                    ->where('project_id', $first->project_id)
                    ->where('month', $first->month)
                    ->max('seq_number');
                $nextBatchByScope[$scopeKey] = intval($maxSeq) > 0 ? intval($maxSeq) : 0;
            }

            $nextBatchByScope[$scopeKey]++;
            $batchId = $nextBatchByScope[$scopeKey];

            $ids = $group->pluck('id')->filter()->values()->toArray();
            if (!empty($ids)) {
                Salary::whereIn('id', $ids)->update(['seq_number' => $batchId]);
            }
        }
    }

    public function store(Request $request)
    {
        if ($response = $this->checkPermission('salaries.create')) {
            return $response;
        }
        
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'project_id' => 'required|exists:projects,id',
            'month' => 'required|date_format:Y-m',
            'basic_salary' => 'required|numeric|min:0',
            // 'allowance' => 'nullable|numeric|min:0', // 数据库中不存在
            'overtime_pay' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            // 'special_deduction' => 'nullable|numeric|min:0', // 数据库中不存在
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 检查是否已存在该员工该月的工资记录（排除已驳回的）
        $existing = Salary::where('employee_id', $request->employee_id)
            ->where('project_id', $request->project_id)
            ->where('month', $request->month)
            ->where('status', '!=', 'rejected') // 排除已驳回的记录
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => '该员工该月的工资记录已存在'
            ], 422);
        }

        // 计算实发工资
        $basicSalary = $request->basic_salary;
        $overtimePay = $request->overtime_pay ?? 0;
        $bonus = $request->bonus ?? 0;
        $deductions = $request->deductions ?? 0;
        $netSalary = $basicSalary + $overtimePay + $bonus - $deductions;
        
        // $employee = Employee::findOrFail($request->employee_id); // 如果需要员工信息可取消注释
        
        $salary = Salary::create([
            'employee_id' => $request->employee_id,
            'project_id' => $request->project_id,
            'month' => $request->month,
            'basic_salary' => $basicSalary,
            // 'allowance' => $request->allowance ?? 0, // 数据库中不存在
            'overtime_pay' => $overtimePay,
            'bonus' => $bonus,
            'deductions' => $deductions,
            'net_salary' => $netSalary,
            'status' => 'draft',
            'notes' => $request->notes,
            // 以下字段数据库中不存在
            // 'special_deduction' => $request->special_deduction ?? $employee->special_deduction,
            // 'social_security' => $employee->social_security_base * 0.1,
            // 'housing_fund' => $employee->housing_fund_base * 0.12,
        ]);

        // 自动计算工资 - 因字段不存在已注释
        // $salary->calculateAll();

        return response()->json([
            'success' => true,
            'message' => '工资记录创建成功',
            'data' => $salary->load(['employee', 'project'])
        ]);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('salaries.edit')) {
            return $response;
        }
        
        $salary = Salary::findOrFail($id);
        
        if ($salary->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能修改草稿状态的工资记录'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'employee_id' => 'sometimes|required|exists:employees,id', // 如需修改员工
            'project_id' => 'sometimes|required|exists:projects,id', // 如需修改项目
            'month' => 'sometimes|required|date_format:Y-m', // 如需修改月份
            'basic_salary' => 'sometimes|required|numeric|min:0',
            // 'allowance' => 'nullable|numeric|min:0', // 数据库中不存在
            'overtime_pay' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            // 'special_deduction' => 'nullable|numeric|min:0', // 数据库中不存在
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'employee_id', 'project_id', 'month', // 允许修改这些字段
            'basic_salary', 'overtime_pay', 'bonus', 'deductions', 'notes'
        ]);
        
        // 重新计算实发工资
        if ($request->has(['basic_salary', 'overtime_pay', 'bonus', 'deductions'])) {
            $basicSalary = $request->basic_salary ?? $salary->basic_salary;
            $overtimePay = $request->overtime_pay ?? $salary->overtime_pay;
            $bonus = $request->bonus ?? $salary->bonus;
            $deductions = $request->deductions ?? $salary->deductions;
            $updateData['net_salary'] = $basicSalary + $overtimePay + $bonus - $deductions;
        }
        
        $salary->update($updateData);

        return response()->json([
            'success' => true,
            'message' => '工资记录更新成功',
            'data' => $salary->load(['employee', 'project'])
        ]);
    }

    public function destroy($id)
    {
        if ($response = $this->checkPermission('salaries.delete')) {
            return $response;
        }
        
        $salary = Salary::findOrFail($id);
        
        if ($salary->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能删除草稿状态的工资记录'
            ], 422);
        }
        
        $salary->delete();

        return response()->json([
            'success' => true,
            'message' => '工资记录删除成功'
        ]);
    }

    public function submit(Request $request, $id)
    {
        $salary = Salary::findOrFail($id);
        
        if ($salary->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能提交草稿状态的工资记录'
            ], 422);
        }

        $salary->update([
            'status' => 'submitted',
            // 'submitted_by' => $request->user()->id, // 数据库中不存在
            // 'submitted_at' => now(), // 数据库中不存在
        ]);

        return response()->json([
            'success' => true,
            'message' => '工资记录提交成功'
        ]);
    }

    public function approve(Request $request, $id)
    {
        $salary = Salary::findOrFail($id);
        
        if (!$salary->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => '该工资记录不能被审批'
            ], 422);
        }

        $salary->update([
            'status' => 'approved',
            // 'approved_by' => $request->user()->id, // 数据库中不存在
            // 'approved_at' => now(), // 数据库中不存在
        ]);

        return response()->json([
            'success' => true,
            'message' => '工资记录审批通过'
        ]);
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $salary = Salary::findOrFail($id);
        
        if (!$salary->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => '该工资记录不能被拒绝'
            ], 422);
        }

        $updateData = ['status' => 'rejected'];
        if ($request->reason) {
            $updateData['notes'] = $request->reason; // 使用notes字段存储拒绝原因
        }
        
        $salary->update($updateData);

        return response()->json([
            'success' => true,
            'message' => '工资记录已拒绝'
        ]);
    }

    public function batchCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'month' => 'required|date_format:Y-m',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::findOrFail($request->project_id);
        $created = [];

        foreach ($request->employee_ids as $employeeId) {
            // $employee = Employee::findOrFail($employeeId); // 如果需要员工信息可取消注释
            
            // 检查是否已存在
            $existing = Salary::where('employee_id', $employeeId)
                ->where('project_id', $request->project_id)
                ->where('month', $request->month)
                ->first();

            if (!$existing) {
                $salary = Salary::create([
                    'employee_id' => $employeeId,
                    'project_id' => $request->project_id,
                    'month' => $request->month,
                    'basic_salary' => 0,
                    'overtime_pay' => 0,
                    'bonus' => 0,
                    'deductions' => 0,
                    'net_salary' => 0,
                    'status' => 'draft',
                    // 以下字段数据库中不存在
                    // 'special_deduction' => $employee->special_deduction,
                    // 'social_security' => $employee->social_security_base * 0.1,
                    // 'housing_fund' => $employee->housing_fund_base * 0.12,
                ]);

                // $salary->calculateAll(); // 因字段不存在已注释
                $created[] = $salary;
            }
        }

        return response()->json([
            'success' => true,
            'message' => '批量创建工资记录成功',
            'data' => $created
        ]);
    }

    public function getSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'month' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $salaries = Salary::where('project_id', $request->project_id)
            ->where('month', $request->month)
            ->get();

        $summary = [
            'total_employees' => $salaries->count(),
            'total_basic_salary' => $salaries->sum('basic_salary'),
            'total_overtime_pay' => $salaries->sum('overtime_pay'),
            'total_bonus' => $salaries->sum('bonus'),
            'total_deductions' => $salaries->sum('deductions'),
            'total_net_salary' => $salaries->sum('net_salary'),
            // 以下字段数据库中不存在
            // 'total_gross_salary' => $salaries->sum('gross_salary'),
            // 'total_social_security' => $salaries->sum('social_security'),
            // 'total_housing_fund' => $salaries->sum('housing_fund'),
            // 'total_personal_tax' => $salaries->sum('personal_tax'),
            // 'total_paid_salary' => $salaries->sum('paid_salary'),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    public function generatePayslip(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'nullable|exists:employees,id',
            'project_id' => 'nullable|exists:projects,id',
            'month_from' => 'required|date_format:Y-m',
            'month_to' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Salary::with(['employee', 'project'])
            ->whereBetween('month', [$request->month_from, $request->month_to]);

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        $salaries = $query->get();

        return response()->json([
            'success' => true,
            'data' => $salaries
        ]);
    }

    /**
     * 生成工资表（新版）
     */
    public function generate(Request $request)
    {
        \Log::info('🚀 generate 方法被调用', [
            'request_data' => $request->all(),
        ]);

        // 兼容旧的单项目和新的多项目两种方式
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $request->header('X-Account-Set-Id') 
            ?: $request->input('current_account_set_id') 
            ?: $user->account_set_id;
        $month = $request->month;
        
        // 兼容处理：支持 project_id（旧）和 project_ids（新）
        if ($request->has('project_ids')) {
            $projectIds = $request->project_ids;
        } elseif ($request->has('project_id')) {
            $projectIds = [$request->project_id];
        } else {
            return response()->json([
                'success' => false,
                'message' => '请选择项目'
            ], 422);
        }
        
        // 获取工资周期
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');
        
        \Log::info('✅ 验证通过，开始生成', [
            'account_set_id' => $accountSetId,
            'month' => $month,
            'project_ids' => $projectIds,
            'project_count' => count($projectIds),
        ]);

        // 使用第一个项目作为主项目
        $mainProjectId = $projectIds[0];
        
        // 获取所有项目
        $allProjects = Project::whereIn('id', $projectIds)->get();
        $mainProject = $allProjects->where('id', $mainProjectId)->first();
        
        if (!$mainProject) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 422);
        }
        
        // 检查所有项目的考勤状态
        foreach ($allProjects as $project) {
            $requireAttendance = true;
            if (isset($project->require_attendance)) {
                $requireAttendance = (bool) $project->require_attendance;
            } elseif (isset($project->requires_attendance)) {
                $requireAttendance = (bool) $project->requires_attendance;
            }
            
            \Log::info('📋 检查项目', [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'require_attendance' => $requireAttendance,
            ]);
            
            if ($requireAttendance) {
                $attendanceSheet = AttendanceSheet::where('project_id', $project->id)
                    ->where('account_set_id', $accountSetId)
                    ->where('month', $month)
                    ->first();

                if (!$attendanceSheet || $attendanceSheet->status !== AttendanceSheet::STATUS_APPROVED) {
                    return response()->json([
                        'success' => false,
                        'message' => "项目「{$project->name}」该月的考勤表未审批"
                    ], 422);
                }
            }
        }

        // 检查所有项目的工资依据
        foreach ($allProjects as $project) {
            // 如果项目设置了需要上传工资依据
            if ($project->requires_salary_basis) {
                $basisExists = \App\Models\BasisRecord::where('project_id', $project->id)
                    ->where('month', $month)
                    ->where('type', 'salary')
                    ->exists();
                
                if (!$basisExists) {
                    return response()->json([
                        'success' => false,
                        'message' => "项目「{$project->name}」设置了需要上传工资依据，请先在【依据管理-工资依据】中上传本月的工资依据后再生成工资表"
                    ], 422);
                }
                
                \Log::info('✅ 工资依据检查通过', [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                ]);
            }
        }

        // 检查主项目是否已存在工资表
        // 逻辑：只检查"有效"的工资数据，排除已驳回的
        // 1. 未提交审批的工资数据（salary_approval_id = NULL）
        // 2. 已提交但未被驳回的审批记录（status != 'rejected'）
        
        \Log::info('🔍 开始检查是否可以创建工资表', [
            'main_project_id' => $mainProjectId,
            'account_set_id' => $accountSetId,
            'month' => $month,
        ]);
        
        // 先检查是否有未提交审批的工资数据（salary_approval_id = NULL）
        $unsubmittedSalaries = Salary::where('project_id', $mainProjectId)
            ->where('account_set_id', $accountSetId)
            ->where('month', $month)
            ->whereNull('salary_approval_id')
            ->get();
        
        \Log::info('📋 检查未提交审批的工资数据', [
            'count' => $unsubmittedSalaries->count(),
            'salaries' => $unsubmittedSalaries->map(function($s) {
                return [
                    'id' => $s->id,
                    'employee_id' => $s->employee_id,
                    'salary_approval_id' => $s->salary_approval_id,
                ];
            })->toArray()
        ]);
        
        if ($unsubmittedSalaries->isNotEmpty()) {
            \Log::info('ℹ️ 检测到未提交审批工资数据，当前已允许重复创建，不拦截', [
                'count' => $unsubmittedSalaries->count()
            ]);
        }
        
        // 再检查是否有已提交但未被驳回的审批记录
        $existingApprovals = \App\Models\SalaryApproval::where('project_id', $mainProjectId)
            ->where('account_set_id', $accountSetId)
            ->where('month', $month)
            ->get();
        
        \Log::info('📋 所有审批记录', [
            'count' => $existingApprovals->count(),
            'approvals' => $existingApprovals->map(function($a) {
                return [
                    'id' => $a->id,
                    'status' => $a->status,
                    'created_at' => $a->created_at,
                ];
            })->toArray()
        ]);
        
        $nonRejectedApprovals = $existingApprovals->where('status', '!=', 'rejected');
        
        \Log::info('📋 非驳回状态的审批记录', [
            'count' => $nonRejectedApprovals->count(),
            'approvals' => $nonRejectedApprovals->map(function($a) {
                return [
                    'id' => $a->id,
                    'status' => $a->status,
                ];
            })->toArray()
        ]);

        if ($nonRejectedApprovals->isNotEmpty()) {
            \Log::info('ℹ️ 检测到非驳回审批记录，当前已允许重复创建，不拦截', [
                'count' => $nonRejectedApprovals->count()
            ]);
        }
        
        \Log::info('✅ 重复检查通过，可以创建新的工资表');
        
        // 获取所有项目的员工（去重）
        // project_ids 是 TEXT 类型存储的 JSON 数组，使用 LIKE 查询兼容低版本 MySQL
        // 包含在职、离职、退休、合同到期的员工
        
        \Log::info('开始查询员工', [
            'account_set_id' => $accountSetId,
            'project_ids' => $projectIds,
        ]);
        
        $allEmployees = Employee::where('account_set_id', $accountSetId)
            ->where(function($query) use ($projectIds) {
                foreach ($projectIds as $projectId) {
                    // 使用 LIKE 查询 JSON 数组中的值，兼容低版本 MySQL
                    // 匹配格式如: [1,2,3] 或 [1, 2, 3] 中的数字
                    $query->orWhere('project_ids', 'LIKE', '%"' . $projectId . '"%')
                          ->orWhere('project_ids', 'LIKE', '%[' . $projectId . ']%')
                          ->orWhere('project_ids', 'LIKE', '%[' . $projectId . ',%')
                          ->orWhere('project_ids', 'LIKE', '%,' . $projectId . ']%')
                          ->orWhere('project_ids', 'LIKE', '%,' . $projectId . ',%');
                }
            })
            // 包含：在职、已审批、离职、退休、合同到期
            ->whereIn('contract_status', ['active', 'approved', 'terminated', 'retired', 'expired'])
            ->get()
            ->unique('id');
        
        \Log::info('员工查询结果', [
            'employee_count' => $allEmployees->count(),
            'employees' => $allEmployees->map(function($emp) {
                return [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'project_ids' => $emp->project_ids,
                    'contract_status' => $emp->contract_status,
                ];
            })->toArray()
        ]);
        
        if ($allEmployees->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '所选项目没有员工，无法生成工资表'
            ], 422);
        }

        // 若账套启用了专项附加扣除项目，则生成工资表前强制校验员工是否已配置专项
        $enabledDeductionItemCount = \App\Models\SpecialDeductionItem::where('account_set_id', $accountSetId)
            ->where('is_active', 1)
            ->count();

        if ($enabledDeductionItemCount > 0) {
            $targetEmployeeIds = $allEmployees->pluck('id')->unique()->values()->toArray();

            $configuredEmployeeIds = \App\Models\EmployeeDeductionDetail::where('account_set_id', $accountSetId)
                ->where('is_active', 1)
                ->whereIn('employee_id', $targetEmployeeIds)
                ->pluck('employee_id')
                ->unique()
                ->values()
                ->toArray();

            $missingDeductionEmployees = $allEmployees->filter(function ($employee) use ($configuredEmployeeIds) {
                return !in_array($employee->id, $configuredEmployeeIds, true);
            })->values();

            if ($missingDeductionEmployees->isNotEmpty()) {
                $previewNames = $missingDeductionEmployees->pluck('name')->take(10)->implode('、');
                $suffix = $missingDeductionEmployees->count() > 10 ? ' 等' : '';

                return response()->json([
                    'success' => false,
                    'message' => '专项附加扣除未配置完整，请先在专项管理中完成配置后再生成工资表',
                    'errors' => [
                        'special_deduction' => [
                            'missing_count' => $missingDeductionEmployees->count(),
                            'missing_employees_preview' => $previewNames . $suffix,
                        ],
                    ],
                ], 422);
            }
        }

        // 根据主项目的保险导入设置，确定使用哪个月的保险数据
        $insuranceImportMonth = $mainProject->insurance_import_month ?? 'current';
        $insuranceMonth = $month; // 默认使用当前月
        
        if ($insuranceImportMonth === 'next') {
            // 如果设置为次月，则使用上个月的保险数据
            $date = \Carbon\Carbon::createFromFormat('Y-m', $month);
            $insuranceMonth = $date->subMonth()->format('Y-m');
            \Log::info('保险导入设置为次月，使用上个月数据', [
                'current_month' => $month,
                'insurance_month' => $insuranceMonth,
            ]);
        } elseif ($insuranceImportMonth === 'none') {
            // 如果设置为不导入，保险数据全部为0
            $insuranceMonth = null;
            \Log::info('保险导入设置为不导入，保险数据将全部为0');
        } else {
            // current - 使用当月数据
            \Log::info('保险导入设置为当月，使用当前月数据', [
                'insurance_month' => $insuranceMonth,
            ]);
        }

        // 为本次生成分配草稿批次号（不改表结构，复用 seq_number 字段）
        $latestDraftBatchId = Salary::where('project_id', $mainProjectId)
            ->where('account_set_id', $accountSetId)
            ->where('month', $month)
            ->max('seq_number');
        $draftBatchId = (intval($latestDraftBatchId) > 0 ? intval($latestDraftBatchId) : 0) + 1;

        // 批量创建工资记录
        $created = [];
        
        $projectNames = $allProjects->pluck('name')->toArray();
        
        \Log::info('开始生成工资表', [
            'main_project_id' => $mainProjectId,
            'main_project_name' => $mainProject->name,
            'all_projects' => $projectNames,
            'month' => $month,
            'draft_batch_id' => $draftBatchId,
            'insurance_import_month' => $insuranceImportMonth,
            'insurance_month' => $insuranceMonth,
            'employee_count' => $allEmployees->count(),
        ]);
        
        foreach ($allEmployees as $employee) {
            \Log::info('处理员工 - 基本信息', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_position' => $employee->position,
                'employee_project_id' => $employee->project_id,
                'will_use_main_project_id' => $mainProjectId,
            ]);
            
            // 允许重复创建：不再按员工做"已存在工资记录"拦截
            
            // 如果存在被驳回的记录，先删除它们，为新记录腾出空间
            $rejectedSalaries = Salary::where('employee_id', $employee->id)
                ->where('project_id', $mainProjectId)
                ->where('month', $month)
                ->where('account_set_id', $accountSetId)
                ->whereHas('salaryApproval', function($q) {
                    $q->where('status', 'rejected');
                })
                ->get();
            
            if ($rejectedSalaries->isNotEmpty()) {
                foreach ($rejectedSalaries as $rejectedSalary) {
                    \Log::info('🗑️ 删除被驳回的工资记录', [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'salary_id' => $rejectedSalary->id,
                        'salary_approval_id' => $rejectedSalary->salary_approval_id,
                    ]);
                    $rejectedSalary->delete();
                }
            }
            
            // 根据保险导入设置，计算保险金额
            if ($insuranceMonth === null) {
                // 不导入保险数据，全部为0
                $insuranceData = [
                    'company_social_security' => 0,
                    'personal_social_security' => 0,
                    'company_housing_fund' => 0,
                    'personal_housing_fund' => 0,
                    'company_large_medical' => 0,
                    'personal_large_medical' => 0,
                    'details' => [
                        'social_security' => [],
                        'housing_fund' => null,
                        'large_medical' => null,
                    ]
                ];
                \Log::info('保险数据全部设为0（不导入）', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                ]);
            } else {
                // 使用指定月份的保险数据
                $insuranceData = $this->calculateMonthlyInsurance($employee, $insuranceMonth);
                \Log::info('使用指定月份保险数据', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'insurance_month' => $insuranceMonth,
                ]);
            }
            
            \Log::info('保险数据返回值', [
                'insurance_data' => $insuranceData,
            ]);
            
            // 根据保险导入设置，决定是否计算补差金额
            if ($insuranceMonth === null) {
                // 不导入保险数据，补差也为0
                $compensationData = [
                    'total' => 0,
                    'details' => [
                        'social_security' => [],
                        'housing_fund' => [],
                    ]
                ];
                \Log::info('补差数据全部设为0（不导入保险）', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                ]);
            } else {
                // 使用指定月份计算补差金额
                $compensationData = $this->calculateCompensation($employee, $insuranceMonth);
                \Log::info('使用指定月份补差数据', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'compensation_month' => $insuranceMonth,
                ]);
            }
            
            // 根据考勤数据计算应发工资和缺勤扣款
            // 使用主项目ID获取考勤数据
            $attendanceData = $this->calculateGrossSalaryWithAttendance($employee, $month, $mainProjectId, $accountSetId);
            $grossSalary = $attendanceData['gross_salary'];
            
            \Log::info('应发工资计算完成', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'gross_salary' => $grossSalary,
                'attendance_data' => $attendanceData,
            ]);
            
            // 计算累计收入（今年1月到当前月的应发工资总和，不包括当前月）
            // 当月应发工资初始为0，等Excel导入后再更新累计收入
            $cumulativeIncome = $this->calculateCumulativeIncome($employee->id, $month, $accountSetId);
            
            // 累计收入暂时只包含之前月份的应发工资，当月应发工资导入后会重新计算
            $cumulativeIncomeWithCurrent = $cumulativeIncome;
            
            \Log::info('累计收入计算完成', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'previous_cumulative' => $cumulativeIncome,
                'current_gross_salary' => 0, // 当月应发工资初始为0
                'total_cumulative_income' => $cumulativeIncomeWithCurrent,
            ]);
            
            // 个人保险合计 = 社保个人 + 公积金个人 + 大额医疗个人
            $personalInsuranceTotal = $insuranceData['personal_social_security'] 
                + $insuranceData['personal_housing_fund']
                + $insuranceData['personal_large_medical'];
            
            // 单位保险合计 = 社保单位 + 公积金单位 + 大额医疗单位
            $companyInsuranceTotal = $insuranceData['company_social_security'] 
                + $insuranceData['company_housing_fund']
                + $insuranceData['company_large_medical'];
            
            \Log::info('生成工资表 - 保险合计', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'company_social_security' => $insuranceData['company_social_security'],
                'company_housing_fund' => $insuranceData['company_housing_fund'],
                'company_large_medical' => $insuranceData['company_large_medical'],
                'company_insurance_total' => $companyInsuranceTotal,
                'personal_social_security' => $insuranceData['personal_social_security'],
                'personal_housing_fund' => $insuranceData['personal_housing_fund'],
                'personal_large_medical' => $insuranceData['personal_large_medical'],
                'personal_insurance_total' => $personalInsuranceTotal,
            ]);
            
            // 补差合计
            $compensationTotal = $compensationData['total'];
            
            // 计算当月专项扣除（6项扣除）
            $specialDeductionData = $this->calculateSpecialDeduction($employee, $month);
            $specialDeductionMonthly = $specialDeductionData['total']; // 当月专项附加扣除
            
            // 计算累计应纳税所得额 = 累计收入 - 累计减除费用(5000×月数) - 累计专项扣除(社保公积金) - 累计专项附加扣除
            list($year, $monthNum) = explode('-', $month);
            $monthCount = intval($monthNum); // 当前是第几个月
            
            // 查询之前月份的数据
            $previousSalaries = Salary::where('employee_id', $employee->id)
                ->where('account_set_id', $accountSetId)
                ->where('month', '>=', $year . '-01')
                ->where('month', '<', $month)
                ->get();
            
            // 累计社保公积金 = 之前月份 + 当前月份
            $cumulativeSocialSecurity = $previousSalaries->sum('social_security') + $insuranceData['personal_social_security'];
            $cumulativeHousingFund = $previousSalaries->sum('housing_fund') + $insuranceData['personal_housing_fund'];
            
            // 累计专项扣除（社保公积金个人部分）= 累计社保 + 累计公积金
            $cumulativeSpecialDeductionInsurance = $cumulativeSocialSecurity + $cumulativeHousingFund;
            
            \Log::info('累计专项扣除（社保公积金）计算', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'month' => $month,
                'cumulative_social_security' => $cumulativeSocialSecurity,
                'cumulative_housing_fund' => $cumulativeHousingFund,
                'cumulative_special_deduction_insurance' => $cumulativeSpecialDeductionInsurance,
            ]);
            
            // 累计专项附加扣除 = 之前月份的累计专项附加扣除 + 当前月的专项附加扣除
            $previousCumulativeSpecialDeduction = $previousSalaries->sum('special_deduction_monthly'); // 累加之前所有月份的当月专项附加扣除
            $cumulativeSpecialDeduction = $previousCumulativeSpecialDeduction + $specialDeductionMonthly;
            
            \Log::info('专项附加扣除计算', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'month' => $month,
                'special_deduction_monthly' => $specialDeductionMonthly,
                'previous_cumulative' => $previousCumulativeSpecialDeduction,
                'cumulative_special_deduction' => $cumulativeSpecialDeduction,
            ]);
            
            // 累计减除费用 = 5000 × 实际工作月数
            // 需要考虑员工入职时间：如果入职时间在当年1月之后，则从入职月份开始计算
            $actualMonthCount = $monthCount; // 默认从1月开始
            
            if ($employee->hire_date) {
                $hireDate = \Carbon\Carbon::parse($employee->hire_date);
                $currentYear = intval($year);
                
                // 如果入职年份是当年，且入职月份在1月之后
                if ($hireDate->year == $currentYear && $hireDate->month > 1) {
                    // 从入职月份到本月的月数
                    $actualMonthCount = $monthCount - $hireDate->month + 1;
                    
                    \Log::info('累计减除费用 - 入职时间调整', [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'hire_date' => $employee->hire_date,
                        'hire_month' => $hireDate->month,
                        'current_month' => $monthCount,
                        'actual_month_count' => $actualMonthCount,
                    ]);
                }
                // 如果入职年份晚于当年，则月数为0（不应该发生，但做保护）
                elseif ($hireDate->year > $currentYear) {
                    $actualMonthCount = 0;
                }
                // 如果入职年份早于当年，或入职月份是1月，则使用默认月数
            }
            
            $cumulativeBasicDeduction = 5000 * $actualMonthCount;
            
            // 累计应纳税所得额
            $cumulativeTaxableIncome = $cumulativeIncomeWithCurrent 
                - $cumulativeBasicDeduction 
                - $cumulativeSocialSecurity 
                - $cumulativeHousingFund 
                - $cumulativeSpecialDeduction;
            
            \Log::info('累计应纳税所得额计算', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'month' => $month,
                'cumulative_income' => $cumulativeIncomeWithCurrent,
                'cumulative_basic_deduction' => $cumulativeBasicDeduction,
                'cumulative_social_security' => $cumulativeSocialSecurity,
                'cumulative_housing_fund' => $cumulativeHousingFund,
                'cumulative_special_deduction' => $cumulativeSpecialDeduction,
                'taxable_income_before_max' => $cumulativeTaxableIncome,
            ]);
            
            // 如果累计应纳税所得额为负数，则为0
            $cumulativeTaxableIncome = max(0, $cumulativeTaxableIncome);
            
            \Log::info('累计应纳税所得额最终值', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'taxable_income_final' => $cumulativeTaxableIncome,
            ]);
            
            // 根据累计应纳税所得额计算税率和速算扣除数
            $taxData = $this->calculateTaxRateAndQuickDeduction($cumulativeTaxableIncome);
            
            // 计算累计应扣缴税额 = 累计应纳税所得额 × 税率% - 速算扣除数
            $cumulativeTaxPayable = ($cumulativeTaxableIncome * $taxData['tax_rate'] / 100) - $taxData['quick_deduction'];
            $cumulativeTaxPayable = max(0, $cumulativeTaxPayable); // 如果为负数，则为0
            
            \Log::info('累计应扣缴税额计算', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'cumulative_taxable_income' => $cumulativeTaxableIncome,
                'tax_rate' => $taxData['tax_rate'],
                'quick_deduction' => $taxData['quick_deduction'],
                'cumulative_tax_payable' => $cumulativeTaxPayable,
            ]);
            
            // 计算已扣缴税额（取上个月的累计应扣缴税额）
            $taxAlreadyWithheld = 0;
            if ($monthNum > 1) {
                // 查询上个月的工资记录
                $lastMonthSalary = Salary::where('employee_id', $employee->id)
                    ->where('account_set_id', $accountSetId)
                    ->where('month', $year . '-' . str_pad($monthNum - 1, 2, '0', STR_PAD_LEFT))
                    ->first();
                
                // 已扣缴税额 = 上个月的累计应扣缴税额
                $taxAlreadyWithheld = $lastMonthSalary ? $lastMonthSalary->cumulative_tax_payable : 0;
                
                \Log::info('已扣缴税额计算', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'year' => $year,
                    'current_month' => $monthNum,
                    'last_month' => $monthNum - 1,
                    'last_month_cumulative_tax' => $taxAlreadyWithheld,
                ]);
            }
            
            // 应发工资通过Excel导入前为0，税额等导入应发工资后再按新规则重算
            $taxPayableOrRefundable = 0;
            
            \Log::info('应补（退）税额计算', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'gross_salary' => 0,
                'personal_social_security' => $insuranceData['personal_social_security'],
                'personal_housing_fund' => $insuranceData['personal_housing_fund'],
                'special_deduction_monthly' => $specialDeductionMonthly,
                'tax_payable_or_refundable' => $taxPayableOrRefundable,
            ]);
            
            // 实发工资 = 应发工资 - 个人保险合计 - 应补（退）税额
            $netSalary = $grossSalary - $personalInsuranceTotal - $taxPayableOrRefundable;
            
            // 获取员工所属项目名称作为部门
            // employee->project_ids 是数组，可能属于多个项目
            // 从当前选中的项目中找出员工所属的项目，如果有多个，用顿号连接
            $employeeProjectNames = [];
            if (is_array($employee->project_ids)) {
                foreach ($employee->project_ids as $empProjectId) {
                    if (in_array($empProjectId, $projectIds)) {
                        $project = $allProjects->firstWhere('id', $empProjectId);
                        if ($project) {
                            $employeeProjectNames[] = $project->name;
                        }
                    }
                }
            }
            $departmentName = !empty($employeeProjectNames) ? implode('、', $employeeProjectNames) : '未知部门';
            
            \Log::info('生成工资表 - 部门岗位', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_project_ids' => $employee->project_ids,
                'selected_project_ids' => $projectIds,
                'matched_projects' => $employeeProjectNames,
                'department_name' => $departmentName,
                'main_project_id' => $mainProjectId,
                'employee_position' => $employee->position,
            ]);
            
            $salary = Salary::create([
                'seq_number' => $draftBatchId,
                'account_set_id' => $accountSetId,
                'employee_id' => $employee->id,
                'id_card' => $employee->id_number,    // 身份证号（员工表字段是id_number）
                'employee_name' => $employee->name,   // 员工姓名
                'project_id' => $mainProjectId,  // 使用主项目ID
                'month' => $month,
                'period_start' => $periodStart,  // 工资周期开始日期
                'period_end' => $periodEnd,      // 工资周期结束日期
                'department' => $departmentName, // 部门使用员工实际所属项目名称
                'position' => $employee->position,
                'insurance_import_setting' => $insuranceImportMonth,  // 保存生成时的保险导入设置
                'work_days' => $attendanceData['work_days'],
                'actual_work_days' => $attendanceData['actual_work_days'],
                'absent_days' => $attendanceData['absent_days'],
                'absent_deduction' => $attendanceData['absent_deduction'],
                'basic_salary' => floatval(str_replace(',', '', $employee->basic_salary ?? 0)),
                'gross_salary' => 0,  // 初始为0，等待通过Excel导入
                'cumulative_income' => $cumulativeIncomeWithCurrent,
                'cumulative_basic_deduction' => $cumulativeBasicDeduction,  // 累计减除费用
                'cumulative_special_deduction_insurance' => $cumulativeSpecialDeductionInsurance,  // 累计专项扣除（社保公积金）
                'tax_rate' => $taxData['tax_rate'],
                'quick_deduction' => $taxData['quick_deduction'],
                'cumulative_tax_payable' => $cumulativeTaxPayable,  // 累计应扣缴税额
                'tax_already_withheld' => $taxAlreadyWithheld,       // 已扣缴税额
                'social_security' => $insuranceData['personal_social_security'],
                'housing_fund' => $insuranceData['personal_housing_fund'],
                'company_insurance_total' => $companyInsuranceTotal,
                'personal_insurance_total' => $personalInsuranceTotal,
                'special_deduction_monthly' => $specialDeductionMonthly,  // 当月专项附加扣除
                'special_deduction' => $cumulativeSpecialDeduction,       // 累计专项附加扣除
                'taxable_income' => $cumulativeTaxableIncome,
                'cumulative_other_taxable' => 0,                 // 累计其他应纳税项（合并扣税）
                'tax_payable_or_refundable' => $taxPayableOrRefundable,  // 应补（退）税额
                'employee_signature' => null,                    // 本人签字
                'net_salary' => $netSalary,
                'paid_salary' => 0,
                'status' => 'draft',
                'submitted_by' => null,
                'approved_by' => null,
            ]);
            $created[] = $salary;
        }

        return response()->json([
            'success' => true,
            'message' => "成功生成 {$month} 的工资表，共 " . count($created) . " 条记录（来自 " . count($projectIds) . " 个项目）",
            'data' => [
                'count' => count($created),
                'draft_batch_id' => $draftBatchId,
                'project_id' => $mainProjectId,
                'project_ids' => $projectIds,
                'project_names' => $projectNames,
                'month' => $month
            ]
        ]);
    }

    /**
     * 获取工资明细（新版）
     */
    public function details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
            'project_id' => 'required|exists:projects,id',
            'salary_approval_id' => 'nullable|integer',
            'draft_batch_id' => 'nullable|integer|min:1',
            'has_approval' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                        $fail('The ' . $attribute . ' field must be true or false.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $user->account_set_id;

        $salaryQuery = Salary::with(['employee:id,name,id_number,account_set_id', 'project:id,name'])
            ->where('project_id', $request->project_id)
            ->where('account_set_id', $accountSetId)
            ->where('month', $request->month);

        // 精确到当前工资表批次：已审批按 salary_approval_id，未审批按 NULL 分组
        $isDraftGroup = false;
        if ($request->filled('salary_approval_id')) {
            $salaryQuery->where('salary_approval_id', $request->salary_approval_id);
        } elseif ($request->has('has_approval')) {
            if ($request->boolean('has_approval')) {
                $salaryQuery->whereNotNull('salary_approval_id');
            } else {
                $salaryQuery->whereNull('salary_approval_id');
                if ($request->filled('draft_batch_id')) {
                    $salaryQuery->where('seq_number', intval($request->draft_batch_id));
                }
                $isDraftGroup = true;
            }
        }

        $salaries = $salaryQuery->orderByDesc('id')->get();

        // 草稿支持重复创建后，同员工可能出现多条历史草稿，这里仅保留最新一条
        if ($isDraftGroup) {
            $salaries = $salaries->unique('employee_id')->values();
            foreach ($salaries as $salary) {
                $this->syncSalaryTaxFields($salary, $request->month, $accountSetId);
            }
        }

        // 格式化数据，包含保险详细明细和补差明细
        $month = $request->month; // 提取变量
        $details = $salaries->map(function ($salary) use ($month) {
            \Log::info('工资详情 - 原始数据', [
                'id' => $salary->id,
                'employee_name' => $salary->employee->name ?? '',
                'gross_salary_raw' => $salary->gross_salary,
                'cumulative_income_raw' => $salary->cumulative_income,
                'cumulative_income_attribute' => $salary->getAttribute('cumulative_income'),
                'insurance_import_setting' => $salary->insurance_import_setting,
                'all_attributes' => $salary->getAttributes(),
            ]);
            
            // ✅ 关键修改：使用工资表保存的保险导入设置，而不是项目当前的设置
            // 这样确保查看历史工资表时，不会受项目当前设置的影响
            $insuranceImportSetting = $salary->insurance_import_setting ?? 'current';
            
            if ($insuranceImportSetting === 'none') {
                // 不导入保险数据，使用空明细
                $insuranceData = [
                    'details' => [
                        'social_security' => [],
                        'housing_fund' => null,
                        'large_medical' => null,
                    ]
                ];
                $compensationData = [
                    'details' => [
                        'social_security' => [],
                        'housing_fund' => [],
                    ]
                ];
                \Log::info('工资详情 - 不导入保险（使用工资表保存的设置），使用空明细', [
                    'salary_id' => $salary->id,
                    'employee_id' => $salary->employee_id,
                    'employee_name' => $salary->employee->name ?? '',
                    'insurance_import_setting' => $insuranceImportSetting,
                ]);
            } else {
                // 确定使用哪个月的数据
                $insuranceMonth = $month;
                if ($insuranceImportSetting === 'next') {
                    $date = \Carbon\Carbon::createFromFormat('Y-m', $month);
                    $insuranceMonth = $date->subMonth()->format('Y-m');
                }
                
                // 重新计算保险明细（实时获取最新数据）
                $insuranceData = $this->calculateMonthlyInsurance($salary->employee, $insuranceMonth);
                
                // 计算补差明细
                $compensationData = $this->calculateCompensation($salary->employee, $insuranceMonth);
                
                \Log::info('工资详情 - 使用保险数据（使用工资表保存的设置）', [
                    'salary_id' => $salary->id,
                    'employee_id' => $salary->employee_id,
                    'employee_name' => $salary->employee->name ?? '',
                    'insurance_import_setting' => $insuranceImportSetting,
                    'insurance_month' => $insuranceMonth,
                ]);
            }
            
            return [
                'id' => $salary->id,
                'employee_id' => $salary->employee_id,
                'employee_name' => $salary->employee_name ?? ($salary->employee->name ?? ''),
                'id_card' => $salary->id_card ?? ($salary->employee->id_number ?? ''),
                'department' => $salary->department,
                'position' => $salary->position,
                'work_days' => $salary->work_days,
                'actual_work_days' => $salary->actual_work_days,
                'absent_days' => $salary->absent_days,
                'absent_deduction' => $salary->absent_deduction,
                'basic_salary' => $salary->basic_salary,
                'gross_salary' => $salary->gross_salary,
                'cumulative_income' => $salary->cumulative_income,
                'cumulative_basic_deduction' => $salary->cumulative_basic_deduction,
                'cumulative_special_deduction_insurance' => $salary->cumulative_special_deduction_insurance,
                'tax_rate' => $salary->tax_rate,
                'quick_deduction' => $salary->quick_deduction,
                'cumulative_tax_payable' => $salary->cumulative_tax_payable,
                'tax_already_withheld' => $salary->tax_already_withheld,
                'company_insurance_total' => $salary->company_insurance_total,
                'social_security' => $salary->social_security,
                'housing_fund' => $salary->housing_fund,
                'personal_insurance_total' => $salary->personal_insurance_total,
                'special_deduction_monthly' => $salary->special_deduction_monthly,  // 当月专项附加扣除
                'special_deduction' => $salary->special_deduction,                  // 累计专项附加扣除
                'taxable_income' => $salary->taxable_income,
                'cumulative_other_taxable' => $salary->cumulative_other_taxable,    // 累计其他应纳税项
                'tax_payable_or_refundable' => $salary->tax_payable_or_refundable,  // 应补（退）税额
                'employee_signature' => $salary->employee_signature,                // 本人签字
                'import_extra_columns' => $this->normalizeImportExtraColumns($salary->import_extra_columns ?? []),
                'net_salary' => $salary->net_salary,
                'paid_salary' => $salary->paid_salary,
                'status' => $salary->status,
                // 保险详细明细
                'insurance_details' => $insuranceData['details'],
                // 补差详细明细
                'compensation_details' => $compensationData['details'],
                // 专项扣除详细明细
                'special_deduction_details' => $this->calculateSpecialDeduction($salary->employee, $month),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'details' => $details,
                'total' => $details->count()
            ]
        ]);
    }

    /**
     * 提交审批（新版）
     */
    public function submitSalary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
            'project_id' => 'required|exists:projects,id',
            'draft_batch_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $user->account_set_id;

        $updated = Salary::where('project_id', $request->project_id)
            ->where('account_set_id', $accountSetId)
            ->where('month', $request->month)
            ->where('status', 'draft')
            ->whereNull('salary_approval_id')
            ->when($request->filled('draft_batch_id'), function ($query) use ($request) {
                $query->where('seq_number', intval($request->draft_batch_id));
            })
            ->update([
                'status' => 'submitted',
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => '没有可提交的工资记录或工资表不是草稿状态'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => '工资表提交成功'
        ]);
    }

    /**
     * 审批通过（新版）
     */
    public function approveSalary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $user->account_set_id;

        $updated = Salary::where('project_id', $request->project_id)
            ->where('account_set_id', $accountSetId)
            ->where('month', $request->month)
            ->where('status', 'submitted')
            ->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => '没有可审批的工资记录或工资表不是已提交状态'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => '工资表审批通过'
        ]);
    }

    /**
     * 审批拒绝（新版）
     */
    public function rejectSalary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
            'project_id' => 'required|exists:projects,id',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $user->account_set_id;

        $updated = Salary::where('project_id', $request->project_id)
            ->where('account_set_id', $accountSetId)
            ->where('month', $request->month)
            ->where('status', 'submitted')
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $request->reason,
            ]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => '没有可拒绝的工资记录或工资表不是已提交状态'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => '工资表已拒绝'
        ]);
    }

    /**
     * 标记发放（新版）
     */
    public function paySalary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $user->account_set_id;

        $updated = Salary::where('project_id', $request->project_id)
            ->where('account_set_id', $accountSetId)
            ->where('month', $request->month)
            ->where('status', 'approved')
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => '没有可标记发放的工资记录或工资表不是已审批状态'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => '工资表已标记为发放'
        ]);
    }

    /**
     * 提交审批前验证工资表
     * 检查：1.实发工资小于0  2.项目在职人员未计算  3.应发工资小于5000但有个税
     */
    public function validateBeforeSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $user->account_set_id;
        $projectId = $request->project_id;
        $month = $request->month;

        // 获取该项目该月的所有工资记录
        $salaries = Salary::with('employee:id,name,id_number')
            ->where('project_id', $projectId)
            ->where('account_set_id', $accountSetId)
            ->where('month', $month)
            ->where('status', 'draft')
            ->whereNull('salary_approval_id')
            ->when($request->filled('draft_batch_id'), function ($query) use ($request) {
                $query->where('seq_number', intval($request->draft_batch_id));
            })
            ->orderByDesc('id')
            ->get()
            ->unique('employee_id')
            ->values();

        foreach ($salaries as $salary) {
            $this->syncSalaryTaxFields($salary, $month, $accountSetId);
        }

        $warnings = [];

        // 1. 检查实发工资小于0的员工
        $negativeNetSalaryEmployees = $salaries->filter(function ($salary) {
            return $salary->net_salary < 0;
        })->map(function ($salary) {
            return [
                'name' => $salary->employee_name ?? ($salary->employee->name ?? '未知'),
                'id_card' => $salary->id_card ?? ($salary->employee->id_number ?? ''),
                'net_salary' => $salary->net_salary,
            ];
        })->values()->toArray();

        if (count($negativeNetSalaryEmployees) > 0) {
            $warnings[] = [
                'type' => 'negative_net_salary',
                'title' => '实发工资小于0',
                'message' => '以下员工实发工资为负数（扣款超过应发工资）',
                'employees' => $negativeNetSalaryEmployees,
            ];
        }

        // 2. 检查项目在职人员是否都已计算工资
        // 获取项目下所有在职员工（使用 LIKE 查询兼容 MySQL 5.6）
        $activeEmployees = Employee::where('account_set_id', $accountSetId)
            ->where(function($query) use ($projectId) {
                $query->where('project_ids', 'LIKE', '%"' . $projectId . '"%')
                      ->orWhere('project_ids', 'LIKE', '%[' . $projectId . ']%')
                      ->orWhere('project_ids', 'LIKE', '%[' . $projectId . ',%')
                      ->orWhere('project_ids', 'LIKE', '%,' . $projectId . ']%')
                      ->orWhere('project_ids', 'LIKE', '%,' . $projectId . ',%');
            })
            ->whereIn('contract_status', ['active', 'approved'])
            ->get();

        // 获取已计算工资的员工ID列表
        $calculatedEmployeeIds = $salaries->pluck('employee_id')->toArray();

        // 找出未计算工资的在职员工
        $missingEmployees = $activeEmployees->filter(function ($employee) use ($calculatedEmployeeIds) {
            return !in_array($employee->id, $calculatedEmployeeIds);
        })->map(function ($employee) {
            return [
                'name' => $employee->name,
                'id_card' => $employee->id_number ?? '',
            ];
        })->values()->toArray();

        if (count($missingEmployees) > 0) {
            $warnings[] = [
                'type' => 'missing_employees',
                'title' => '在职人员未计算工资',
                'message' => '以下在职员工未包含在本月工资表中',
                'employees' => $missingEmployees,
            ];
        }

        // 3. 检查应发工资小于5000但有个税的员工
        $invalidTaxEmployees = $salaries->filter(function ($salary) {
            // 应发工资小于5000，但应补（退）税额大于0
            return $salary->gross_salary < 5000 && $salary->tax_payable_or_refundable > 0;
        })->map(function ($salary) {
            return [
                'name' => $salary->employee_name ?? ($salary->employee->name ?? '未知'),
                'id_card' => $salary->id_card ?? ($salary->employee->id_number ?? ''),
                'gross_salary' => $salary->gross_salary,
                'tax_payable_or_refundable' => $salary->tax_payable_or_refundable,
            ];
        })->values()->toArray();

        if (count($invalidTaxEmployees) > 0) {
            $warnings[] = [
                'type' => 'invalid_tax',
                'title' => '个税计算异常',
                'message' => '以下员工应发工资低于5000元但存在应缴税额，请检查',
                'employees' => $invalidTaxEmployees,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_warnings' => count($warnings) > 0,
                'warnings' => $warnings,
            ]
        ]);
    }

    /**
     * 删除工资表（新版）
     */
    public function deleteSalary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
            'project_id' => 'required|exists:projects,id',
            'draft_batch_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $user->account_set_id;

        $deleted = Salary::where('project_id', $request->project_id)
            ->where('account_set_id', $accountSetId)
            ->where('month', $request->month)
            ->where('status', 'draft')
            ->whereNull('salary_approval_id')
            ->when($request->filled('draft_batch_id'), function ($query) use ($request) {
                $query->where('seq_number', intval($request->draft_batch_id));
            })
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => '没有可删除的工资记录或工资表不是草稿状态'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => '工资表删除成功'
        ]);
    }

    /**
     * 计算员工本月保险金额（单位和个人），包含详细明细
     */
    private function calculateMonthlyInsurance($employee, $month)
    {
        $companySocialSecurity = 0;
        $personalSocialSecurity = 0;
        $companyHousingFund = 0;
        $personalHousingFund = 0;
        $largeMedicalCompanyAmount = 0;
        $largeMedicalEmployeeAmount = 0;
        
        // 详细明细数组
        $details = [
            'social_security' => [],
            'housing_fund' => [],
            'large_medical' => null,
        ];

        // 查找员工的参保记录
        $insurancePersonnel = InsurancePersonnel::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->first();

        \Log::info('=== 开始计算保险 ===', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'has_insurance_personnel' => $insurancePersonnel ? 'YES' : 'NO',
        ]);

        if ($insurancePersonnel) {
            \Log::info('参保记录详情', [
                'insurance_personnel_id' => $insurancePersonnel->id,
                'employee_social_security_base' => $insurancePersonnel->employee_social_security_base,
                'employee_housing_fund_base' => $insurancePersonnel->employee_housing_fund_base,
                'employee_large_medical_base' => $insurancePersonnel->employee_large_medical_base,
                'social_security_types' => $insurancePersonnel->social_security_types,
                'housing_fund_config_id' => $insurancePersonnel->housing_fund_config_id,
            ]);

            // 计算社保（单位和个人）- 包含详细明细
            if ($insurancePersonnel->social_security_types) {
                $socialSecurityTypes = is_string($insurancePersonnel->social_security_types) 
                    ? json_decode($insurancePersonnel->social_security_types, true) 
                    : $insurancePersonnel->social_security_types;

                \Log::info('社保险种数据', [
                    'is_array' => is_array($socialSecurityTypes),
                    'count' => is_array($socialSecurityTypes) ? count($socialSecurityTypes) : 0,
                    'data' => $socialSecurityTypes,
                ]);

                if (is_array($socialSecurityTypes)) {
                    foreach ($socialSecurityTypes as $index => $typeConfig) {
                        // 从 insurance_personnel 表读取社保基数
                        $base = $insurancePersonnel->employee_social_security_base ?? 0;
                        $companyRatio = floatval($typeConfig['company_ratio'] ?? 0);
                        $employeeRatio = floatval($typeConfig['employee_ratio'] ?? 0);
                        
                        $companyAmount = $base * $companyRatio;
                        $personalAmount = $base * $employeeRatio;
                        
                        \Log::info("社保险种 #{$index}", [
                            'name' => $typeConfig['name'] ?? '未知',
                            'base' => $base,
                            'company_ratio' => $companyRatio,
                            'employee_ratio' => $employeeRatio,
                            'company_amount' => $companyAmount,
                            'personal_amount' => $personalAmount,
                        ]);
                        
                        $companySocialSecurity += $companyAmount;
                        $personalSocialSecurity += $personalAmount;
                        
                        // 保存明细
                        $details['social_security'][] = [
                            'name' => $typeConfig['name'] ?? '未知险种',
                            'base' => $base,
                            'company_ratio' => $companyRatio,
                            'company_amount' => round($companyAmount, 2),
                            'personal_ratio' => $employeeRatio,
                            'personal_amount' => round($personalAmount, 2),
                        ];
                    }
                }
            }

            // 计算医保（单位和个人）- 从 medical_insurance_types JSON 字段读取
            if ($insurancePersonnel->medical_insurance_types) {
                $medicalInsuranceTypes = is_string($insurancePersonnel->medical_insurance_types)
                    ? json_decode($insurancePersonnel->medical_insurance_types, true)
                    : $insurancePersonnel->medical_insurance_types;
                
                \Log::info('医保险种数据', [
                    'is_array' => is_array($medicalInsuranceTypes),
                    'count' => is_array($medicalInsuranceTypes) ? count($medicalInsuranceTypes) : 0,
                    'data' => $medicalInsuranceTypes,
                ]);
                
                if (is_array($medicalInsuranceTypes)) {
                    foreach ($medicalInsuranceTypes as $index => $typeConfig) {
                        // 医保基数：从表字段读取（与参保明细接口一致）
                        $base = $insurancePersonnel->employee_medical_insurance_base ?? 0;
                        $companyRatio = floatval($typeConfig['company_ratio'] ?? 0);
                        $employeeRatio = floatval($typeConfig['employee_ratio'] ?? 0);
                        
                        $companyAmount = $base * $companyRatio;
                        $personalAmount = $base * $employeeRatio;
                        
                        $companySocialSecurity += $companyAmount;
                        $personalSocialSecurity += $personalAmount;
                        
                        \Log::info("医保险种 #{$index}", [
                            'name' => $typeConfig['name'] ?? '未知',
                            'base' => $base,
                            'company_ratio' => $companyRatio,
                            'employee_ratio' => $employeeRatio,
                            'company_amount' => $companyAmount,
                            'personal_amount' => $personalAmount,
                        ]);
                        
                        // 保存明细（添加到 social_security 数组中，因为医保属于社保的一部分）
                        $details['social_security'][] = [
                            'name' => $typeConfig['name'] ?? '未知险种',
                            'base' => $base,
                            'company_ratio' => $companyRatio,
                            'company_amount' => round($companyAmount, 2),
                            'personal_ratio' => $employeeRatio,
                            'personal_amount' => round($personalAmount, 2),
                        ];
                    }
                }
            }

            // 计算公积金（单位和个人）- 基数从表字段，比例从JSON读取
            $housingFundBase = $insurancePersonnel->employee_housing_fund_base ?? 0;
            
            if ($insurancePersonnel->housing_fund_params && $housingFundBase > 0) {
                $housingFundParams = is_string($insurancePersonnel->housing_fund_params)
                    ? json_decode($insurancePersonnel->housing_fund_params, true)
                    : $insurancePersonnel->housing_fund_params;
                
                \Log::info('公积金参数', [
                    'base_from_table' => $housingFundBase,
                    'housing_fund_params' => $housingFundParams,
                ]);
                
                if ($housingFundParams && is_array($housingFundParams)) {
                    // 基数从表字段，比例从 JSON 读取
                    $companyRatio = floatval($housingFundParams['company_ratio'] ?? 0);
                    $personalRatio = floatval($housingFundParams['employee_ratio'] ?? 0);
                    
                    $companyHousingFund = $housingFundBase * $companyRatio;
                    $personalHousingFund = $housingFundBase * $personalRatio;
                    
                    \Log::info('公积金计算结果', [
                        'base' => $housingFundBase,
                        'company_ratio' => $companyRatio,
                        'personal_ratio' => $personalRatio,
                        'company_amount' => $companyHousingFund,
                        'personal_amount' => $personalHousingFund,
                    ]);
                    
                    // 保存明细
                    $details['housing_fund'] = [
                        'name' => '住房公积金',
                        'base' => $housingFundBase,
                        'company_ratio' => $companyRatio,
                        'company_amount' => round($companyHousingFund, 2),
                        'personal_ratio' => $personalRatio,
                        'personal_amount' => round($personalHousingFund, 2),
                    ];
                }
            }
            
            // 大额医疗（如果有）- 从 large_medical_insurance_config JSON 字段读取
            if ($insurancePersonnel->large_medical_insurance_config) {
                $largeMedicalConfig = is_string($insurancePersonnel->large_medical_insurance_config)
                    ? json_decode($insurancePersonnel->large_medical_insurance_config, true)
                    : $insurancePersonnel->large_medical_insurance_config;
                
                \Log::info('大额医疗配置', [
                    'large_medical_config' => $largeMedicalConfig,
                ]);
                
                if ($largeMedicalConfig && is_array($largeMedicalConfig) && ($largeMedicalConfig['is_enabled'] ?? false)) {
                    $largeMedicalBase = $insurancePersonnel->employee_large_medical_base ?? 0;
                    $largeMedicalCompanyBase = $insurancePersonnel->employee_large_medical_company_base ?? $largeMedicalBase;
                    $periodDate = \Carbon\Carbon::createFromFormat('Y-m', $month);
                    $isPaymentMonth = app(LargeMedicalPaymentCycleService::class)->isPaymentMonth(
                        $insurancePersonnel->employee_id,
                        (int) $periodDate->year,
                        (int) $periodDate->month,
                        $insurancePersonnel->project_id,
                        $insurancePersonnel->account_set_id
                    );

                    if ($isPaymentMonth) {
                        if (($largeMedicalConfig['calculation_type'] ?? 'base') === 'base') {
                            // 按基数计算：公司用公司基数，个人用个人基数
                            $largeMedicalCompanyAmount = $largeMedicalCompanyBase * (floatval($largeMedicalConfig['company_ratio'] ?? 0));
                            $largeMedicalEmployeeAmount = $largeMedicalBase * (floatval($largeMedicalConfig['employee_ratio'] ?? 0));
                        } else {
                            // 按固定金额
                            $largeMedicalCompanyAmount = floatval($largeMedicalConfig['company_amount'] ?? 0);
                            $largeMedicalEmployeeAmount = floatval($largeMedicalConfig['employee_amount'] ?? 0);
                        }
                    } else {
                        $largeMedicalCompanyAmount = 0;
                        $largeMedicalEmployeeAmount = 0;
                    }
                    
                    \Log::info('大额医疗计算结果', [
                        'base' => $largeMedicalBase,
                        'calculation_type' => $largeMedicalConfig['calculation_type'] ?? 'unknown',
                        'payment_cycle' => $largeMedicalConfig['payment_cycle'] ?? 'month',
                        'is_payment_month' => $isPaymentMonth,
                        'company_amount' => $largeMedicalCompanyAmount,
                        'personal_amount' => $largeMedicalEmployeeAmount,
                    ]);
                    
                    $details['large_medical'] = [
                        'name' => '大额医疗',
                        'base' => $largeMedicalBase,
                        'company_amount' => round($largeMedicalCompanyAmount, 2),
                        'personal_amount' => round($largeMedicalEmployeeAmount, 2),
                    ];
                }
            }
        }

        $result = [
            'company_social_security' => round($companySocialSecurity, 2),
            'personal_social_security' => round($personalSocialSecurity, 2),
            'company_housing_fund' => round($companyHousingFund, 2),
            'personal_housing_fund' => round($personalHousingFund, 2),
            'company_large_medical' => round($largeMedicalCompanyAmount, 2),
            'personal_large_medical' => round($largeMedicalEmployeeAmount, 2),
            'details' => $details, // 返回详细明细
        ];

        \Log::info('=== 保险计算完成 ===', [
            'company_social_security' => $result['company_social_security'],
            'personal_social_security' => $result['personal_social_security'],
            'company_housing_fund' => $result['company_housing_fund'],
            'personal_housing_fund' => $result['personal_housing_fund'],
            'company_large_medical' => $result['company_large_medical'],
            'personal_large_medical' => $result['personal_large_medical'],
            'company_total' => $result['company_social_security'] + $result['company_housing_fund'] + $result['company_large_medical'],
            'personal_total' => $result['personal_social_security'] + $result['personal_housing_fund'] + $result['personal_large_medical'],
        ]);

        return $result;
    }

    /**
     * 计算员工专项扣除明细
     */
    private function calculateSpecialDeduction($employee, $month)
    {
        // 获取该账套下所有启用的专项扣除项目
        $allItems = \App\Models\SpecialDeductionItem::where('is_active', 1)
            ->where('account_set_id', $employee->account_set_id)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        
        \Log::info('专项扣除计算 - 查询项目', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_account_set_id' => $employee->account_set_id,
            'found_items_count' => $allItems->count(),
            'found_items' => $allItems->map(function($item) {
                return ['id' => $item->id, 'name' => $item->name, 'account_set_id' => $item->account_set_id];
            })->toArray(),
        ]);
        
        // 查询该员工的专项扣除详情
        $deductionDetail = \App\Models\EmployeeDeductionDetail::where('employee_id', $employee->id)
            ->where('is_active', 1)
            ->first();
        
        // 解析员工绑定的扣除项目（格式：1:100|2:100）
        $employeeItemsMap = [];
        if ($deductionDetail && !empty($deductionDetail->deduction_items)) {
            $parts = explode('|', $deductionDetail->deduction_items);
            foreach ($parts as $part) {
                if (strpos($part, ':') !== false) {
                    list($itemId, $amount) = explode(':', $part);
                    $employeeItemsMap[$itemId] = floatval($amount);
                }
            }
        }
        
        // 构建所有项目的列表（包括员工未绑定的项目，金额为0）
        $items = [];
        $total = 0;
        
        foreach ($allItems as $item) {
            $amount = isset($employeeItemsMap[$item->id]) ? $employeeItemsMap[$item->id] : 0;
            $items[] = [
                'id' => $item->id,
                'name' => $item->name,
                'amount' => $amount,
            ];
            $total += $amount;
        }
        
        return [
            'total' => round($total, 2),
            'items' => $items
        ];
    }

    /**
     * 计算应补（退）税额：应发工资 - 5000 - 个人社保/公积金 - 专项附加扣除。
     * 大额医疗不参与这个公式。
     */
    private function calculateTaxPayableOrRefundable($grossSalary, $personalSocialSecurity, $personalHousingFund, $specialDeduction)
    {
        return round(
            floatval($grossSalary)
            - 5000
            - floatval($personalSocialSecurity)
            - floatval($personalHousingFund)
            - floatval($specialDeduction),
            2
        );
    }

    /**
     * 计算员工本月补差金额
     */
    private function calculateCompensation($employee, $month)
    {
        // 查询该员工在该月份创建的所有补差记录（按创建日期筛选，与参保增减保持一致）
        $compensations = \App\Models\InsuranceCompensationRecord::where('employee_id', $employee->id)
            ->whereYear('created_at', '=', substr($month, 0, 4))
            ->whereMonth('created_at', '=', substr($month, 5, 2))
            ->get();
        
        $total = 0;
        $details = [
            'social_security' => [],  // 社保补差明细
            'housing_fund' => [],     // 公积金补差明细
        ];
        
        foreach ($compensations as $compensation) {
            // 获取补差详情
            $compensationDetails = $compensation->compensation_details ?? [];
            
            // 按补差类型分类
            if ($compensation->compensation_type === 'social_security') {
                // 社保补差
                $details['social_security'][] = [
                    'id' => $compensation->id,
                    'old_base' => $compensation->old_base,
                    'new_base' => $compensation->new_base,
                    'compensation_months' => $compensation->compensation_months,
                    'company_total' => $compensation->company_total,
                    'personal_total' => $compensation->personal_total,
                    'total_amount' => $compensation->total_amount,
                    'details' => $compensationDetails,
                ];
                $total += floatval($compensation->total_amount);
            } elseif ($compensation->compensation_type === 'housing_fund') {
                // 公积金补差
                $details['housing_fund'][] = [
                    'id' => $compensation->id,
                    'old_base' => $compensation->old_base,
                    'new_base' => $compensation->new_base,
                    'compensation_months' => $compensation->compensation_months,
                    'company_total' => $compensation->company_total,
                    'personal_total' => $compensation->personal_total,
                    'total_amount' => $compensation->total_amount,
                    'details' => $compensationDetails,
                ];
                $total += floatval($compensation->total_amount);
            }
        }
        
        \Log::info('补差计算结果', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'month' => $month,
            'total' => $total,
            'social_security_count' => count($details['social_security']),
            'housing_fund_count' => count($details['housing_fund']),
            'details' => $details,  // 输出完整的详情结构
        ]);
        
        return [
            'total' => round($total, 2),
            'details' => $details,
        ];
    }

    /**
     * 根据累计预扣预缴应纳税所得额计算税率和速算扣除数
     * 参考个人所得税税率表
     */
    private function calculateTaxRateAndQuickDeduction($cumulativeTaxableIncome)
    {
        // 累计预扣预缴应纳税所得额
        $taxableIncome = floatval($cumulativeTaxableIncome);
        
        // 根据税率表确定税率和速算扣除数
        if ($taxableIncome <= 36000) {
            // 不超过36000元的部分
            $taxRate = 3;
            $quickDeduction = 0;
        } elseif ($taxableIncome <= 144000) {
            // 超过36000元至144000元的部分
            $taxRate = 10;
            $quickDeduction = 2520;
        } elseif ($taxableIncome <= 300000) {
            // 超过144000元至300000元的部分
            $taxRate = 20;
            $quickDeduction = 16920;
        } elseif ($taxableIncome <= 420000) {
            // 超过300000元至420000元的部分
            $taxRate = 25;
            $quickDeduction = 31920;
        } elseif ($taxableIncome <= 660000) {
            // 超过420000元至660000元的部分
            $taxRate = 30;
            $quickDeduction = 52920;
        } elseif ($taxableIncome <= 960000) {
            // 超过660000元至960000元的部分
            $taxRate = 35;
            $quickDeduction = 85920;
        } else {
            // 超过960000元的部分
            $taxRate = 45;
            $quickDeduction = 181920;
        }
        
        \Log::info('税率和速算扣除数计算', [
            'cumulative_taxable_income' => $taxableIncome,
            'tax_rate' => $taxRate,
            'quick_deduction' => $quickDeduction,
        ]);
        
        return [
            'tax_rate' => $taxRate,
            'quick_deduction' => $quickDeduction,
        ];
    }

    /**
     * 计算累计收入（今年1月到当前月的应发工资总和）
     */
    private function calculateCumulativeIncome($employeeId, $currentMonth, $accountSetId)
    {
        // 解析当前月份（格式：2024-10）
        list($year, $month) = explode('-', $currentMonth);
        $currentMonthInt = intval($month);
        
        // 查询今年1月到当前月（不包括当前月）的所有已生成的工资记录
        $previousSalaries = Salary::where('employee_id', $employeeId)
            ->where('account_set_id', $accountSetId)
            ->where('month', '>=', $year . '-01')
            ->where('month', '<', $currentMonth)
            ->get();
        
        // 累计之前月份的应发工资
        $cumulativeIncome = $previousSalaries->sum('gross_salary');
        
        \Log::info('累计收入计算', [
            'employee_id' => $employeeId,
            'current_month' => $currentMonth,
            'previous_months_count' => $previousSalaries->count(),
            'cumulative_income' => $cumulativeIncome,
        ]);
        
        return round($cumulativeIncome, 2);
    }

    private function resolveCumulativeBasicDeduction($employee, int $year, int $monthNum): float
    {
        $actualMonthCount = $monthNum;

        if ($employee && $employee->hire_date) {
            $hireDate = Carbon::parse($employee->hire_date);

            if ($hireDate->year === $year && $hireDate->month > 1) {
                $actualMonthCount = $monthNum - $hireDate->month + 1;
            } elseif ($hireDate->year > $year) {
                $actualMonthCount = 0;
            }
        }

        return round(5000 * max(0, $actualMonthCount), 2);
    }

    private function buildSalaryTaxFields(Salary $salary, string $month, int $accountSetId): array
    {
        [$year, $monthNum] = explode('-', $month);
        $year = intval($year);
        $monthNum = intval($monthNum);

        $previousSalaries = Salary::where('employee_id', $salary->employee_id)
            ->where('account_set_id', $accountSetId)
            ->where('month', '>=', sprintf('%04d-01', $year))
            ->where('month', '<', $month)
            ->get();

        $grossSalary = round(floatval($salary->gross_salary), 2);
        $socialSecurity = round(floatval($salary->social_security), 2);
        $housingFund = round(floatval($salary->housing_fund), 2);
        $personalInsuranceTotal = round(floatval($salary->personal_insurance_total), 2);
        $cumulativeOtherTaxable = round(floatval($salary->cumulative_other_taxable), 2);

        $cumulativeIncome = round(floatval($previousSalaries->sum('gross_salary')) + $grossSalary, 2);
        $cumulativeSocialSecurity = round(floatval($previousSalaries->sum('social_security')) + $socialSecurity, 2);
        $cumulativeHousingFund = round(floatval($previousSalaries->sum('housing_fund')) + $housingFund, 2);
        $cumulativeSpecialDeductionInsurance = round($cumulativeSocialSecurity + $cumulativeHousingFund, 2);

        $employee = $salary->relationLoaded('employee') ? $salary->employee : null;
        if (!$employee && $salary->employee_id) {
            $employee = Employee::find($salary->employee_id);
        }

        $specialDeductionMonthly = round(floatval($salary->special_deduction_monthly), 2);
        if ($employee) {
            $specialDeductionData = $this->calculateSpecialDeduction($employee, $month);
            $specialDeductionMonthly = round(floatval($specialDeductionData['total'] ?? 0), 2);
        }

        $previousCumulativeSpecialDeduction = round(floatval($previousSalaries->sum('special_deduction_monthly')), 2);
        $cumulativeSpecialDeduction = round($previousCumulativeSpecialDeduction + $specialDeductionMonthly, 2);

        $cumulativeBasicDeduction = $this->resolveCumulativeBasicDeduction($employee, $year, $monthNum);
        $cumulativeTaxableIncome = round(max(
            0,
            $cumulativeIncome
            - $cumulativeBasicDeduction
            - $cumulativeSpecialDeductionInsurance
            - $cumulativeSpecialDeduction
            + $cumulativeOtherTaxable
        ), 2);

        $taxData = $this->calculateTaxRateAndQuickDeduction($cumulativeTaxableIncome);
        $cumulativeTaxPayable = round(max(
            0,
            ($cumulativeTaxableIncome * $taxData['tax_rate'] / 100) - $taxData['quick_deduction']
        ), 2);

        $taxAlreadyWithheld = 0.0;
        if ($monthNum > 1) {
            $lastMonth = sprintf('%04d-%02d', $year, $monthNum - 1);
            $lastMonthSalary = Salary::where('employee_id', $salary->employee_id)
                ->where('account_set_id', $accountSetId)
                ->where('month', $lastMonth)
                ->orderByDesc('id')
                ->first();
            $taxAlreadyWithheld = $lastMonthSalary ? round(floatval($lastMonthSalary->cumulative_tax_payable), 2) : 0.0;
        }

        $taxPayableOrRefundable = round($this->calculateTaxPayableOrRefundable(
            $grossSalary,
            $socialSecurity,
            $housingFund,
            $specialDeductionMonthly
        ), 2);

        $netSalary = round($grossSalary - $personalInsuranceTotal - $taxPayableOrRefundable, 2);

        return [
            'cumulative_income' => $cumulativeIncome,
            'cumulative_basic_deduction' => $cumulativeBasicDeduction,
            'cumulative_special_deduction_insurance' => $cumulativeSpecialDeductionInsurance,
            'special_deduction_monthly' => $specialDeductionMonthly,
            'special_deduction' => $cumulativeSpecialDeduction,
            'taxable_income' => $cumulativeTaxableIncome,
            'tax_rate' => $taxData['tax_rate'],
            'quick_deduction' => $taxData['quick_deduction'],
            'cumulative_tax_payable' => $cumulativeTaxPayable,
            'tax_already_withheld' => $taxAlreadyWithheld,
            'tax_payable_or_refundable' => $taxPayableOrRefundable,
            'net_salary' => $netSalary,
        ];
    }

    private function syncSalaryTaxFields(Salary $salary, string $month, int $accountSetId): void
    {
        $updatedFields = $this->buildSalaryTaxFields($salary, $month, $accountSetId);
        $salary->fill($updatedFields);

        if ($salary->isDirty(array_keys($updatedFields))) {
            $salary->save();
        }
    }

    /**
     * 根据考勤统计计算应发工资和缺勤扣款
     */
    private function calculateGrossSalaryWithAttendance($employee, $month, $projectId, $accountSetId)
    {
        // 获取该月的考勤表
        $attendanceSheet = \App\Models\AttendanceSheet::where('project_id', $projectId)
            ->where('account_set_id', $accountSetId)
            ->where('month', $month)
            ->first();
        
        if (!$attendanceSheet) {
            \Log::warning('未找到考勤表', [
                'employee_id' => $employee->id,
                'month' => $month,
                'project_id' => $projectId,
            ]);
            return [
                'work_days' => 0,
                'actual_work_days' => 0,
                'absent_days' => 0,
                'absent_deduction' => 0,
                'gross_salary' => 0,  // 应发工资初始为0，等待Excel导入
            ];
        }
        
        // 查询该员工在该考勤表中的统计数据
        $attendanceStats = \App\Models\AttendanceStatistics::where('employee_id', $employee->id)
            ->where('attendance_sheet_id', $attendanceSheet->id)
            ->first();
        
        if (!$attendanceStats) {
            \Log::warning('未找到员工考勤统计', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'attendance_sheet_id' => $attendanceSheet->id,
            ]);
            return [
                'work_days' => $attendanceSheet->work_days ?? 0,
                'actual_work_days' => 0,
                'absent_days' => 0,
                'absent_deduction' => 0,
                'gross_salary' => 0,  // 应发工资初始为0，等待Excel导入
            ];
        }
        
        // 获取考勤数据（仅用于展示，不参与工资计算）
        $workDays = intval($attendanceStats->work_days ?? 0); // 应出勤天数
        $actualWorkDays = floatval($attendanceStats->actual_work_days ?? 0); // 实际出勤天数
        $absentDays = floatval($attendanceStats->absent_days ?? 0); // 缺勤天数
        
        \Log::info('考勤数据（仅展示，不参与计算）', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'month' => $month,
            'work_days' => $workDays,
            'actual_work_days' => $actualWorkDays,
            'absent_days' => $absentDays,
        ]);
        
        // 考勤数据仅用于展示，不参与工资计算
        // 应发工资初始为0，等待Excel导入
        // 缺勤扣款不计算
        return [
            'work_days' => $workDays,
            'actual_work_days' => $actualWorkDays,
            'absent_days' => $absentDays,
            'absent_deduction' => 0,  // 不计算缺勤扣款
            'gross_salary' => 0,      // 应发工资初始为0，等待Excel导入
        ];
    }

    /**
     * 发起工资付款申请
     */
    public function createPaymentRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'month' => 'required|date_format:Y-m',
            'salary_approval_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $currentAccountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');

        DB::beginTransaction();
        try {
            // 1. 查找工资表审批记录
            $salaryApproval = SalaryApproval::where('project_id', $request->project_id)
                                           ->where('month', $request->month)
                                           ->where('account_set_id', $currentAccountSetId)
                                           ->where('status', 'approved')
                                           ->when($request->filled('salary_approval_id'), function ($query) use ($request) {
                                               $query->where('id', intval($request->salary_approval_id));
                                           })
                                           ->orderByDesc('id')
                                           ->first();

            if (!$salaryApproval) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到已审批的工资表'
                ], 404);
            }

            // 2. 检查是否已经发起过付款申请
            $existingRequest = PaymentRequest::where('salary_approval_id', $salaryApproval->id)->first();
            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => '该工资表已发起过付款申请'
                ], 422);
            }

            // 3. 计算付款金额（所有员工的实发工资总和）
            $totalAmount = Salary::where('project_id', $request->project_id)
                                ->where('month', $request->month)
                                ->where('account_set_id', $currentAccountSetId)
                                ->where('salary_approval_id', $salaryApproval->id)
                                ->sum('net_salary');

            // 4. 创建付款申请
            $paymentRequest = PaymentRequest::create([
                'payment_type' => 'salary',
                'account_set_id' => $currentAccountSetId,
                'salary_approval_id' => $salaryApproval->id,
                'amount' => $totalAmount,
                'status' => 'pending',
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'remarks' => '工资付款申请 - ' . $salaryApproval->month,
            ]);

            DB::commit();

            \Log::info('工资付款申请已创建', [
                'payment_request_id' => $paymentRequest->id,
                'salary_approval_id' => $salaryApproval->id,
                'amount' => $totalAmount
            ]);

            return response()->json([
                'success' => true,
                'message' => '付款申请已发起',
                'data' => $paymentRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('创建工资付款申请失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '发起付款申请失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导入应发工资Excel
     */
    public function importGrossSalary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls',
            'project_id' => 'required|exists:projects,id',
            'month' => 'required|date_format:Y-m',
            'current_account_set_id' => 'required|exists:account_sets,id',
            'draft_batch_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $mainProjectId = $request->project_id;
            $month = $request->month;
            $accountSetId = $request->input('current_account_set_id');
            $user = Auth::user();

            // 使用PhpSpreadsheet读取Excel
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            if (empty($data) || count($data) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel文件为空或数据不足'
                ], 422);
            }

            $parseResult = $this->parseGrossSalaryImportRows($sheet, $data);
            if (!$parseResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $parseResult['message']
                ], 422);
            }

            $excelPersonnel = $parseResult['personnel'];
            
            if (empty($excelPersonnel)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel中未读取到可用人员数据（身份证和姓名至少要有一个）'
                ], 422);
            }

            // 【智能项目识别】优先用身份证，找不到时回退姓名
            $detectedProjectId = $this->detectProjectFromExcel($excelPersonnel, $accountSetId);
            
            if (!$detectedProjectId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel中的身份证/姓名在系统中均未找到对应员工，请先维护人员档案'
                ], 422);
            }
            
            \Log::info('Excel导入 - 识别到项目', [
                'main_project_id' => $mainProjectId,
                'detected_project_id' => $detectedProjectId,
                'is_same' => $mainProjectId == $detectedProjectId
            ]);
            
            // 使用识别到的项目ID
            $projectId = $detectedProjectId;
            
            // 获取该项目该月的所有工资记录
            $salaries = Salary::where('project_id', $projectId)
                ->where('month', $month)
                ->where('account_set_id', $accountSetId)
                ->where('status', 'draft')
                ->whereNull('salary_approval_id')
                ->when($request->filled('draft_batch_id'), function ($query) use ($request) {
                    $query->where('seq_number', intval($request->draft_batch_id));
                })
                ->orderByDesc('id')
                ->get()
                ->unique('employee_id')
                ->values();
            
            $salaryByName = $salaries->groupBy(function ($item) {
                return trim((string) $item->employee_name);
            });
            $matchedSalaryIds = [];
            $matchedRows = [];
            $missingInSalary = []; // Excel有但工资表没有匹配到 = 需要新增

            foreach ($excelPersonnel as $person) {
                $matchedSalary = null;
                $matchedBy = '';

                if (!empty($person['id_card'])) {
                    $matchedSalary = $salaries->firstWhere('id_card', $person['id_card']);
                    if ($matchedSalary) {
                        $matchedBy = 'id_card';
                    }
                }

                // 身份证找不到，回退姓名匹配
                if (!$matchedSalary && !empty($person['name'])) {
                    $nameMatches = $salaryByName->get($person['name'], collect());
                    if ($nameMatches->count() === 1) {
                        $matchedSalary = $nameMatches->first();
                        $matchedBy = 'name';
                    } elseif ($nameMatches->count() > 1) {
                        $missingInSalary[] = [
                            'id_card' => $person['id_card'],
                            'name' => $person['name'],
                            'reason' => '姓名匹配到多条工资记录，无法唯一定位',
                            'row_index' => $person['row_index']
                        ];
                        continue;
                    }
                }

                if (!$matchedSalary) {
                    $missingInSalary[] = [
                        'id_card' => $person['id_card'],
                        'name' => $person['name'],
                        'reason' => '未在工资表中找到匹配人员',
                        'row_index' => $person['row_index']
                    ];
                    continue;
                }

                $matchedSalaryIds[] = $matchedSalary->id;
                $matchedRows[] = [
                    'salary' => $matchedSalary,
                    'gross_salary' => $person['gross_salary'],
                    'import_extra_columns' => $person['import_extra_columns'] ?? [],
                    'matched_by' => $matchedBy
                ];
            }

            // 工资表有但Excel没有 = 需要减少
            $matchedSalaryIds = array_values(array_unique($matchedSalaryIds));
            $missingInExcel = $salaries
                ->filter(function ($salary) use ($matchedSalaryIds) {
                    return !in_array($salary->id, $matchedSalaryIds, true);
                })
                ->values();
            
            \Log::info('Excel导入 - 人员对比', [
                'project_id' => $projectId,
                'month' => $month,
                'salary_count' => $salaries->count(),
                'excel_count' => count($excelPersonnel),
                'missing_in_salary_count' => count($missingInSalary),
                'missing_in_excel_count' => $missingInExcel->count()
            ]);
            
            // 如果发现人员不匹配，拒绝导入并创建变动记录
            if (!empty($missingInSalary) || $missingInExcel->isNotEmpty()) {
                $errorMessages = [];
                
                // 处理需要新增的人员
                if (!empty($missingInSalary)) {
                    $addPersonnel = [];
                    foreach ($missingInSalary as $person) {
                        $addPersonnel[] = [
                            'id_card' => $person['id_card'],
                            'name' => $person['name']
                        ];
                    }
                    
                    try {
                        $this->createOrUpdatePersonnelChangeRequest(
                            $accountSetId,
                            $projectId,
                            $month,
                            'add',
                            $addPersonnel,
                            $user->id
                        );
                        $errorMessages[] = "Excel中有" . count($missingInSalary) . "个人员在工资表中不存在或无法唯一匹配（需要新增）";
                    } catch (\Exception $e) {
                        $errorMessages[] = $e->getMessage();
                    }
                }
                
                // 处理需要减少的人员
                if ($missingInExcel->isNotEmpty()) {
                    $removePersonnel = [];
                    foreach ($missingInExcel as $salary) {
                        $removePersonnel[] = [
                            'id_card' => $salary->id_card,
                            'name' => $salary ? $salary->employee_name : ''
                        ];
                    }
                    
                    try {
                        $this->createOrUpdatePersonnelChangeRequest(
                            $accountSetId,
                            $projectId,
                            $month,
                            'remove',
                            $removePersonnel,
                            $user->id
                        );
                        $errorMessages[] = "工资表中有" . $missingInExcel->count() . "个人员在Excel中不存在（需要减少）";
                    } catch (\Exception $e) {
                        $errorMessages[] = $e->getMessage();
                    }
                }
                
                // 拒绝导入，返回详细信息
                return response()->json([
                    'success' => false,
                    'message' => "人员信息不匹配，请先维护人员档案！\n\n" . implode("\n", $errorMessages) . "\n\n人员变动记录已保存到【人员汇总申请】模块，请前往查看并提交审批。",
                    'data' => [
                        'add_personnel' => !empty($missingInSalary) ? array_values(array_map(function($person) {
                            return [
                                'id_card' => $person['id_card'],
                                'name' => $person['name'],
                                'reason' => $person['reason'] ?? '',
                                'row_index' => $person['row_index'] ?? null
                            ];
                        }, $missingInSalary)) : [],
                        'remove_personnel' => $missingInExcel->isNotEmpty() ? array_values($missingInExcel->map(function($salary) {
                            return [
                                'id_card' => $salary->id_card,
                                'name' => $salary ? $salary->employee_name : ''
                            ];
                        })->toArray()) : []
                    ]
                ], 422);
            }
            
            // 人员完全匹配，开始导入
            $successCount = 0;
            $updatesBySalaryId = [];
            foreach ($matchedRows as $matchedRow) {
                $updatesBySalaryId[$matchedRow['salary']->id] = $matchedRow;
            }

            foreach ($updatesBySalaryId as $matchedRow) {
                /** @var Salary $salary */
                $salary = $matchedRow['salary'];
                $grossSalary = round(floatval($matchedRow['gross_salary']), 2);
                $salary->gross_salary = $grossSalary;

                $updateData = [
                    'gross_salary' => $grossSalary,
                ];
                $updateData = array_merge($updateData, $this->buildSalaryTaxFields($salary, $month, $accountSetId));

                if (Schema::hasColumn('salaries', 'import_extra_columns')) {
                    $updateData['import_extra_columns'] = $matchedRow['import_extra_columns'] ?? [];
                }

                $salary->update($updateData);
                
                $successCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "导入成功：已更新 {$successCount} 条工资记录",
                'data' => [
                    'success_count' => $successCount
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('导入应发工资失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '导入失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 解析应发工资导入表。优先支持新工资模板（第4/5行表头，第6行数据），兼容旧模板（第2行表头，第3行数据）。
     */
    private function parseGrossSalaryImportRows($sheet, array $data)
    {
        $templateResult = $this->buildGrossSalaryRowsFromHeader($sheet, $data, 3, 4, 5);
        if ($templateResult['success']) {
            return $templateResult;
        }

        return $this->buildGrossSalaryRowsFromHeader($sheet, $data, 1, null, 2);
    }

    private function buildGrossSalaryRowsFromHeader($sheet, array $data, $primaryHeaderRowIndex, $secondaryHeaderRowIndex, $dataStartRowIndex)
    {
        if (count($data) <= $primaryHeaderRowIndex) {
            return [
                'success' => false,
                'message' => 'Excel文件为空或数据不足'
            ];
        }

        $headers = $this->buildImportHeaderColumns($sheet, $primaryHeaderRowIndex, $secondaryHeaderRowIndex);
        $idCardIndex = null;
        $nameIndex = null;
        $grossSalaryIndex = null;

        foreach ($headers as $header) {
            $cleanLabel = $this->cleanImportHeader($header['label']);
            $cleanFullLabel = $this->cleanImportHeader($header['full_label']);

            if (in_array($cleanLabel, ['身份证', '身份证号', '身份证号码', '证件号', '证件号码', 'ID', 'id'], true)) {
                $idCardIndex = $header['index'];
            }

            if (in_array($cleanLabel, ['姓名', '名字', '员工姓名', '员工名称', '人员姓名'], true)) {
                $nameIndex = $header['index'];
            }

            if (in_array($cleanLabel, ['应发工资', '应发', '工资', '金额', '应发金额'], true)
                || in_array($cleanFullLabel, ['应发工资', '应发', '工资', '金额', '应发金额'], true)) {
                $grossSalaryIndex = $header['index'];
            }
        }

        if ($nameIndex === null && $secondaryHeaderRowIndex === null && isset($headers[0])) {
            $nameIndex = 0;
        }

        if ($idCardIndex === null && $nameIndex === null) {
            return [
                'success' => false,
                'message' => '未找到身份证列或姓名列'
            ];
        }

        if ($grossSalaryIndex === null) {
            return [
                'success' => false,
                'message' => '未找到应发工资列'
            ];
        }

        $personnel = [];
        for ($i = $dataStartRowIndex; $i < count($data); $i++) {
            $row = $data[$i];
            $idCard = $idCardIndex !== null && isset($row[$idCardIndex]) ? trim((string) $row[$idCardIndex]) : '';
            $name = $nameIndex !== null && isset($row[$nameIndex]) ? trim((string) $row[$nameIndex]) : '';

            if ($idCard === '' && $name === '') {
                continue;
            }

            if ($this->cleanImportHeader($name) === '合计') {
                continue;
            }

            $extraColumns = [];
            foreach ($headers as $header) {
                if ($this->isImportExcludedColumn($header)) {
                    continue;
                }

                $value = $row[$header['index']] ?? null;
                $extraColumns[] = [
                    'key' => 'col_' . $header['column'],
                    'label' => $header['full_label'],
                    'short_label' => $header['label'],
                    'group' => $header['group'],
                    'column' => $header['column'],
                    'value' => $value,
                ];
            }

            $personnel[] = [
                'id_card' => $idCard,
                'name' => $name,
                'gross_salary' => $this->parseImportedAmount($row[$grossSalaryIndex] ?? 0),
                'import_extra_columns' => $extraColumns,
                'row_index' => $i + 1
            ];
        }

        if (empty($personnel)) {
            return [
                'success' => false,
                'message' => 'Excel中未读取到可用人员数据（身份证和姓名至少要有一个）'
            ];
        }

        \Log::info('Excel导入 - 解析工资模板成功', [
            'primary_header_row' => $primaryHeaderRowIndex + 1,
            'secondary_header_row' => $secondaryHeaderRowIndex !== null ? $secondaryHeaderRowIndex + 1 : null,
            'data_start_row' => $dataStartRowIndex + 1,
            'employee_count' => count($personnel),
            'extra_column_count' => isset($personnel[0]) ? count($personnel[0]['import_extra_columns']) : 0,
        ]);

        return [
            'success' => true,
            'personnel' => $personnel,
        ];
    }

    private function buildImportHeaderColumns($sheet, $primaryHeaderRowIndex, $secondaryHeaderRowIndex = null)
    {
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $columns = [];

        for ($index = 0; $index < $highestColumn; $index++) {
            $primary = trim((string) $this->readMergedCellValue($sheet, $primaryHeaderRowIndex, $index));
            $secondary = $secondaryHeaderRowIndex !== null
                ? trim((string) $this->readMergedCellValue($sheet, $secondaryHeaderRowIndex, $index))
                : '';

            $label = $secondary !== '' ? $secondary : $primary;
            $group = ($secondary !== '' && $primary !== $secondary) ? $primary : '';
            $fullLabel = $group !== '' ? $group . '-' . $label : $label;

            if ($this->cleanImportHeader($label) === '') {
                continue;
            }

            $columns[] = [
                'index' => $index,
                'column' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1),
                'label' => preg_replace('/\s+/u', '', $label),
                'group' => preg_replace('/\s+/u', '', $group),
                'full_label' => preg_replace('/\s+/u', '', $fullLabel),
            ];
        }

        return $columns;
    }

    private function readMergedCellValue($sheet, $rowIndex, $columnIndex)
    {
        $row = $rowIndex + 1;
        $column = $columnIndex + 1;
        $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row;
        $value = $sheet->getCell($coordinate)->getValue();

        if ($value !== null && $value !== '') {
            return $value;
        }

        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
            if ($column >= $start[0] && $column <= $end[0] && $row >= $start[1] && $row <= $end[1]) {
                $startCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($start[0]) . $start[1];
                return $sheet->getCell($startCoordinate)->getValue();
            }
        }

        return '';
    }

    private function cleanImportHeader($value)
    {
        return str_replace([' ', '　', "\r", "\n", "\t"], '', trim((string) $value));
    }

    /**
     * 兼容导入金额格式：例如 4,242.00 / ￥4,242 / (4,242.00)
     */
    private function parseImportedAmount($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        $isNegativeByBracket = false;
        if (preg_match('/^\((.*)\)$/', $raw, $matches)) {
            $isNegativeByBracket = true;
            $raw = $matches[1];
        }

        // 去除常见货币符号、千分位和空白字符
        $normalized = str_replace(
            ['￥', '¥', ',', '，', ' ', "\t", "\r", "\n", "\u{00A0}", "\u{3000}"],
            '',
            $raw
        );

        $amount = 0.0;
        if (preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            $amount = (float) $normalized;
        } elseif (preg_match('/-?\d[\d,，]*(\.\d+)?/', $raw, $matches)) {
            // 兜底：提取数字片段后再去掉千分位
            $candidate = str_replace([',', '，'], '', $matches[0]);
            $amount = (float) $candidate;
        }

        if ($isNegativeByBracket) {
            $amount *= -1;
        }

        return round($amount, 2);
    }

    private function isImportExcludedColumn(array $header)
    {
        $label = $this->cleanImportHeader($header['label']);
        $group = $this->cleanImportHeader($header['group']);
        $fullLabel = $this->cleanImportHeader($header['full_label']);
        $excluded = [
            '序号', '姓名', '身份证', '身份证号', '身份证号码', '证件号', '证件号码', 'ID', 'id', '员工UserID', '员工编号',
            '应发工资', '缴费基数', '养老保险16%', '医疗保险缴费基数', '医疗保险7%', '工伤失业缴费基数',
            '工伤保险0.4%', '失业保险0.5%', '大额医疗保险', '社保差额调整', '公积金基数', '公积金',
            'Administrator上一年度平均工资', '养老保险', '医疗保险', '失业保险', '大额医疗',
            '单位合计', '个人合计', '累计收入', '累计减除费用', '累计专项扣除', '累计专项附加扣除（6项扣除）',
            '累计其他应纳税项（合并扣税）', '累计应纳税所得额', '税率', '速算扣除数', '累计应扣缴税额',
            '已扣缴税额', '本期个税', '个税调整', '应补（退）税额', '实发工资'
        ];
        $excludedGroups = [
            '个人所得税专项附加扣除',
        ];

        return in_array($label, $excluded, true)
            || in_array($fullLabel, $excluded, true)
            || in_array($group, $excludedGroups, true);
    }

    private function normalizeImportExtraColumns($columns)
    {
        if (is_string($columns)) {
            $decoded = json_decode($columns, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($columns) ? $columns : [];
    }
    
    /**
     * 从Excel中的身份证/姓名检测实际项目
     */
    private function detectProjectFromExcel($excelPersonnel, $accountSetId)
    {
        foreach ($excelPersonnel as $person) {
            $employee = null;

            // 优先根据身份证号查找员工
            if (!empty($person['id_card'])) {
                $employee = Employee::where('id_number', $person['id_card'])
                    ->where('account_set_id', $accountSetId)
                    ->first();
            }

            // 身份证找不到时，根据姓名查找（需唯一）
            if (!$employee && !empty($person['name'])) {
                $nameMatches = Employee::where('name', $person['name'])
                    ->where('account_set_id', $accountSetId)
                    ->limit(2)
                    ->get();

                if ($nameMatches->count() === 1) {
                    $employee = $nameMatches->first();
                }
            }

            if ($employee && !empty($employee->project_ids)) {
                // 找到员工，返回第一个项目ID
                $projectIds = is_array($employee->project_ids) ? $employee->project_ids : json_decode($employee->project_ids, true);
                if (!empty($projectIds) && is_array($projectIds)) {
                    \Log::info('Excel导入 - 通过身份证/姓名识别到项目', [
                        'id_card' => $person['id_card'] ?? '',
                        'name' => $person['name'] ?? '',
                        'employee_name' => $employee->name,
                        'project_ids' => $projectIds,
                        'selected_project_id' => $projectIds[0]
                    ]);
                    return $projectIds[0];
                }
            }
        }
        
        \Log::warning('Excel导入 - 未能识别项目', [
            'excel_personnel_count' => count($excelPersonnel),
            'account_set_id' => $accountSetId
        ]);
        
        return null;
    }
    
    /**
     * 创建或更新人员变动申请记录
     */
    private function createOrUpdatePersonnelChangeRequest($accountSetId, $projectId, $month, $changeType, $personnelList, $userId)
    {
        try {
            // 查找是否存在相同项目、月份、类型的待审批记录
            $existing = PersonnelChangeRequest::where('project_id', $projectId)
                ->where('month', $month)
                ->where('change_type', $changeType)
                ->whereIn('status', ['pending', 'in_approval'])
                ->first();
            
            if ($existing) {
                // 检查是否已发起审批流程
                if ($existing->status === 'in_approval' && $existing->approval_flow_id) {
                    \Log::warning('人员变动记录已发起流程，无法覆盖', [
                        'id' => $existing->id,
                        'project_id' => $projectId,
                        'month' => $month,
                        'change_type' => $changeType
                    ]);
                    throw new \Exception("该项目{$month}月份的{$this->getChangeTypeName($changeType)}记录已发起流程申请，请先驳回后再导入");
                }
                
                // 更新现有记录
                $existing->update([
                    'personnel_list' => $personnelList,
                    'status' => 'pending',
                    'approval_flow_id' => null
                ]);
                
                \Log::info('人员变动记录已更新', [
                    'id' => $existing->id,
                    'project_id' => $projectId,
                    'month' => $month,
                    'change_type' => $changeType,
                    'personnel_count' => count($personnelList)
                ]);
            } else {
                // 创建新记录
                $changeRequest = PersonnelChangeRequest::create([
                    'account_set_id' => $accountSetId,
                    'project_id' => $projectId,
                    'month' => $month,
                    'change_type' => $changeType,
                    'personnel_list' => $personnelList,
                    'status' => 'pending',
                    'created_by' => $userId
                ]);
                
                \Log::info('人员变动记录已创建', [
                    'id' => $changeRequest->id,
                    'project_id' => $projectId,
                    'month' => $month,
                    'change_type' => $changeType,
                    'personnel_count' => count($personnelList)
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('创建/更新人员变动记录失败', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'month' => $month,
                'change_type' => $changeType
            ]);
            
            // 如果是流程已发起的异常，重新抛出
            if (strpos($e->getMessage(), '已发起流程申请') !== false) {
                throw $e;
            }
        }
    }
    
    /**
     * 获取变动类型名称
     */
    private function getChangeTypeName($changeType)
    {
        return $changeType === 'add' ? '人员新增' : '人员减少';
    }
}
