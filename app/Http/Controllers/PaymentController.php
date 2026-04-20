<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['project', 'applicant', 'approver']);
        
        // 【账套过滤】
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }
        
        // 只有当参数有值时才进行过滤
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }
        
        $perPage = $request->get('per_page', 20);
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        // 格式化返回数据，添加前端需要的字段
        $payments->getCollection()->transform(function ($payment) {
            $payment->project_name = $payment->project ? $payment->project->name : '-';
            return $payment;
        });
        
        return response()->json([
            'success' => true,
            'data' => $payments->items(), // 返回数据项
            'total' => $payments->total(), // 返回总数
            'current_page' => $payments->currentPage(),
            'per_page' => $payments->perPage(),
            'last_page' => $payments->lastPage()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'type' => 'required|in:salary,social_security,commercial_insurance,reimbursement',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string',
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
        
        $payment = Payment::create([
            'project_id' => $request->project_id,
            'type' => $request->type,
            'amount' => $request->amount,
            'description' => $request->description,
            'attachments' => $request->attachments ?? [],
            'applicant_id' => $request->user()->id,
            'status' => 'draft',
            'account_set_id' => $currentAccountSetId,
        ]);

        return response()->json([
            'success' => true,
            'message' => '付款申请创建成功',
            'data' => $payment->load(['project', 'applicant'])
        ]);
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        
        if ($payment->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能修改草稿状态的付款申请'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'project_id' => 'sometimes|required|exists:projects,id',
            'type' => 'sometimes|required|in:salary,social_security,commercial_insurance,reimbursement',
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'sometimes|required|string',
            'payment_date' => 'nullable|date',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $payment->update($request->only(['project_id', 'type', 'amount', 'description', 'payment_date', 'attachments']));

        return response()->json([
            'success' => true,
            'message' => '付款申请更新成功',
            'data' => $payment->load(['project', 'applicant'])
        ]);
    }

    public function destroy($id)
    {
        $payment = Payment::findOrFail($id);
        
        if ($payment->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能删除草稿状态的付款申请'
            ], 422);
        }
        
        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => '付款申请删除成功'
        ]);
    }

    public function submit(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        
        if ($payment->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能提交草稿状态的付款申请'
            ], 422);
        }

        $payment->update([
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '付款申请提交成功'
        ]);
    }

    public function approve(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        
        if (!$payment->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => '该付款申请不能被审批'
            ], 422);
        }

        $payment->approve($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => '付款申请审批通过'
        ]);
    }

    public function pay(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        
        if (!$payment->canBePaid()) {
            return response()->json([
                'success' => false,
                'message' => '该付款申请不能被支付'
            ], 422);
        }

        $payment->pay();

        return response()->json([
            'success' => true,
            'message' => '付款完成'
        ]);
    }

    public function record(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        
        if ($payment->is_recorded) {
            return response()->json([
                'success' => false,
                'message' => '该付款申请已入账'
            ], 422);
        }

        $payment->record();

        return response()->json([
            'success' => true,
            'message' => '付款申请已入账'
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

        $query = Payment::query();
        
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->month) {
            $query->whereMonth('created_at', substr($request->month, 5, 2))
                  ->whereYear('created_at', substr($request->month, 0, 4));
        }

        $summary = [
            'total_amount' => $query->sum('amount'),
            'total_count' => $query->count(),
            'by_type' => $query->groupBy('type')
                ->selectRaw('type, sum(amount) as total_amount, count(*) as count')
                ->get(),
            'by_status' => $query->groupBy('status')
                ->selectRaw('status, sum(amount) as total_amount, count(*) as count')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}
