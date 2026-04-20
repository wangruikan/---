<?php

namespace App\Http\Controllers;

use App\Models\InsuranceRecord;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InsuranceController extends Controller
{
    public function index(Request $request)
    {
        $query = InsuranceRecord::with(['employee:id,name', 'project:id,name']);
        
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
        
        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->has('insurance_type') && $request->insurance_type) {
            $query->where('insurance_type', $request->insurance_type);
        }
        
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        $records = $query->orderBy('created_at', 'desc')->paginate(50);
        
        // 添加 employee_name 和 project_name 字段
        $records->getCollection()->transform(function ($item) {
            $item->employee_name = $item->employee ? $item->employee->name : null;
            $item->project_name = $item->project ? $item->project->name : null;
            return $item;
        });
        
        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'project_id' => 'required|exists:projects,id',
            'insurance_type' => 'required|string|max:50',
            // 'action' => 'required|in:add,remove,replace', // 数据库中不存在
            // 'effective_date' => 'required|date', // 数据库中不存在
            'base_amount' => 'required|numeric|min:0',
            'company_rate' => 'required|numeric|min:0|max:1',
            'personal_rate' => 'required|numeric|min:0|max:1',
            'payment_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 计算缴费金额
        $baseAmount = $request->base_amount;
        $companyAmount = $baseAmount * $request->company_rate;
        $personalAmount = $baseAmount * $request->personal_rate;
        $totalAmount = $companyAmount + $personalAmount;

        // 【账套关联】
        $currentAccountSetId = $request->input('current_account_set_id');
        
        $record = InsuranceRecord::create([
            'employee_id' => $request->employee_id,
            'project_id' => $request->project_id,
            'insurance_type' => $request->insurance_type,
            'account_set_id' => $currentAccountSetId,
            'base_amount' => $baseAmount,
            'company_rate' => $request->company_rate,
            'personal_rate' => $request->personal_rate,
            'company_amount' => $companyAmount,
            'personal_amount' => $personalAmount,
            'total_amount' => $totalAmount,
            'payment_date' => $request->payment_date,
            'due_date' => $request->due_date,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => '保险记录创建成功',
            'data' => $record->load(['employee', 'project'])
        ]);
    }

    public function update(Request $request, $id)
    {
        $record = InsuranceRecord::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'employee_id' => 'sometimes|required|exists:employees,id', // 允许修改员工
            'project_id' => 'sometimes|required|exists:projects,id', // 允许修改项目
            'insurance_type' => 'sometimes|required|string|max:50', // 允许修改保险类型
            // 'effective_date' => 'sometimes|required|date', // 数据库中不存在
            'base_amount' => 'sometimes|required|numeric|min:0',
            'company_rate' => 'sometimes|required|numeric|min:0|max:1',
            'personal_rate' => 'sometimes|required|numeric|min:0|max:1',
            'payment_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            // 'attachment' => 'nullable|string', // 数据库中不存在
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'employee_id', 'project_id', 'insurance_type', // 允许修改这些字段
            'base_amount', 'company_rate', 'personal_rate', 'payment_date', 'due_date', 'notes'
        ]);
        
        // 重新计算缴费金额
        if ($request->has(['base_amount', 'company_rate', 'personal_rate'])) {
            $baseAmount = $request->base_amount ?? $record->base_amount;
            $companyRate = $request->company_rate ?? $record->company_rate;
            $personalRate = $request->personal_rate ?? $record->personal_rate;
            
            $updateData['company_amount'] = $baseAmount * $companyRate;
            $updateData['personal_amount'] = $baseAmount * $personalRate;
            $updateData['total_amount'] = $updateData['company_amount'] + $updateData['personal_amount'];
        }

        $record->update($updateData);

        return response()->json([
            'success' => true,
            'message' => '保险记录更新成功',
            'data' => $record->load(['employee', 'project'])
        ]);
    }

    public function destroy($id)
    {
        $record = InsuranceRecord::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => '保险记录删除成功'
        ]);
    }

    public function markAsCompleted(Request $request, $id)
    {
        $record = InsuranceRecord::findOrFail($id);
        
        if ($record->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => '该记录已支付'
            ], 422);
        }

        // 数据库枚举值: pending, paid, overdue
        $record->update([
            'status' => 'paid', // 使用 paid 代替 completed
            // 'completion_date' => now(), // 数据库中不存在
            // 'processed_by' => $request->user()->id, // 数据库中不存在
        ]);

        return response()->json([
            'success' => true,
            'message' => '保险记录标记为已支付'
        ]);
    }

    public function getOverdue()
    {
        // effective_date 字段不存在，使用 due_date 代替
        $overdue = InsuranceRecord::with(['employee', 'project'])
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $overdue
        ]);
    }

    public function getSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'nullable|exists:projects,id',
            'month' => 'nullable|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = InsuranceRecord::query();
        
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->month) {
            $query->whereMonth('created_at', substr($request->month, 5, 2))
                  ->whereYear('created_at', substr($request->month, 0, 4));
        }

        $summary = [
            'total_records' => $query->count(),
            'pending' => $query->where('status', 'pending')->count(),
            'paid' => $query->where('status', 'paid')->count(), // 使用 paid 代替 completed
            'overdue' => $query->where('status', 'overdue')->count(),
            'by_type' => $query->groupBy('insurance_type')
                ->selectRaw('insurance_type, count(*) as count')
                ->pluck('count', 'insurance_type'),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    public function batchAdd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'insurance_types' => 'required|array',
            'insurance_types.*' => 'string',
            // 'effective_date' => 'required|date', // 数据库中不存在
            'base_amount' => 'required|numeric|min:0',
            'company_rate' => 'required|numeric|min:0|max:1',
            'personal_rate' => 'required|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $created = [];
        $baseAmount = $request->base_amount;
        $companyRate = $request->company_rate;
        $personalRate = $request->personal_rate;
        $companyAmount = $baseAmount * $companyRate;
        $personalAmount = $baseAmount * $personalRate;
        $totalAmount = $companyAmount + $personalAmount;
        
        foreach ($request->employee_ids as $employeeId) {
            foreach ($request->insurance_types as $insuranceType) {
                $record = InsuranceRecord::create([
                    'employee_id' => $employeeId,
                    'project_id' => $request->project_id,
                    'insurance_type' => $insuranceType,
                    // 'action' => 'add', // 数据库中不存在
                    // 'effective_date' => $request->effective_date, // 数据库中不存在
                    'base_amount' => $baseAmount,
                    'company_rate' => $companyRate,
                    'personal_rate' => $personalRate,
                    'company_amount' => $companyAmount,
                    'personal_amount' => $personalAmount,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                ]);
                
                $created[] = $record;
            }
        }

        return response()->json([
            'success' => true,
            'message' => '批量添加保险记录成功',
            'data' => $created
        ]);
    }

    public function batchRemove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'insurance_types' => 'required|array',
            'insurance_types.*' => 'string',
            // 'effective_date' => 'required|date', // 数据库中不存在
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $created = [];
        
        foreach ($request->employee_ids as $employeeId) {
            foreach ($request->insurance_types as $insuranceType) {
                $record = InsuranceRecord::create([
                    'employee_id' => $employeeId,
                    'project_id' => $request->project_id,
                    'insurance_type' => $insuranceType,
                    // 'action' => 'remove', // 数据库中不存在
                    // 'effective_date' => $request->effective_date, // 数据库中不存在
                    'base_amount' => 0,
                    'company_rate' => 0,
                    'personal_rate' => 0,
                    'company_amount' => 0,
                    'personal_amount' => 0,
                    'total_amount' => 0,
                    'status' => 'pending',
                ]);
                
                $created[] = $record;
            }
        }

        return response()->json([
            'success' => true,
            'message' => '批量减员保险记录成功',
            'data' => $created
        ]);
    }
}
