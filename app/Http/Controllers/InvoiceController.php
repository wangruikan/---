<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['project', 'applicant', 'approver']);
        
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
        
        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }
        
        $perPage = $request->get('per_page', 20);
        $invoices = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        // 格式化返回数据，添加前端需要的字段
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->project_name = $invoice->project ? $invoice->project->name : '-';
            return $invoice;
        });
        
        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'total' => $invoices->total(),
            'current_page' => $invoices->currentPage(),
            'per_page' => $invoices->perPage(),
            'last_page' => $invoices->lastPage()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'invoice_number' => 'required|string|unique:invoices,invoice_number',
            'amount' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'type' => 'required|in:vat_special,vat_ordinary,ordinary',
            'issue_date' => 'nullable|date',
            'content' => 'nullable|string',
            'notes' => 'nullable|string',
            'deduction_details' => 'nullable|array',
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
        
        $invoice = Invoice::create([
            'project_id' => $request->project_id,
            'invoice_number' => $request->invoice_number,
            'amount' => $request->amount,
            'account_set_id' => $currentAccountSetId,
            'tax_rate' => $request->tax_rate ?? 0,
            'tax_amount' => $request->tax_amount ?? 0,
            'total_amount' => $request->total_amount,
            'type' => $request->type,
            'issue_date' => $request->issue_date,
            'content' => $request->content,
            'notes' => $request->notes,
            'deduction_details' => $request->deduction_details,
            'status' => 'draft',
            'applicant_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => '发票创建成功',
            'data' => $invoice
        ]);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        
        if ($invoice->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能修改草稿状态的发票'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'sometimes|required|exists:projects,id',
            'invoice_number' => 'sometimes|required|string|unique:invoices,invoice_number,' . $id,
            'amount' => 'sometimes|required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'sometimes|required|numeric|min:0',
            'type' => 'sometimes|required|in:vat_special,vat_ordinary,ordinary',
            'issue_date' => 'nullable|date',
            'content' => 'nullable|string',
            'notes' => 'nullable|string',
            'deduction_details' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $invoice->update($request->only([
            'project_id',
            'invoice_number',
            'amount',
            'tax_rate',
            'tax_amount',
            'total_amount',
            'type',
            'issue_date',
            'content',
            'notes',
            'deduction_details'
        ]));

        return response()->json([
            'success' => true,
            'message' => '发票更新成功',
            'data' => $invoice
        ]);
    }

    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        
        if ($invoice->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能删除草稿状态的发票'
            ], 422);
        }
        
        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => '发票删除成功'
        ]);
    }

    public function submit(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        
        if ($invoice->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能提交草稿状态的发票'
            ], 422);
        }

        $invoice->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '发票提交成功'
        ]);
    }

    public function approve(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        
        if (!$invoice->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => '该发票不能被审批'
            ], 422);
        }

        $invoice->approve($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => '发票审批成功'
        ]);
    }

    public function issue(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        
        if (!$invoice->canBeIssued()) {
            return response()->json([
                'success' => false,
                'message' => '该发票不能被开具'
            ], 422);
        }

        $invoice->issue();

        return response()->json([
            'success' => true,
            'message' => '发票开具成功'
        ]);
    }

    public function getSummary(Request $request)
    {
        $query = Invoice::query();
        
        // 可以添加日期范围筛选
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $summary = [
            'total_count' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'total_tax_amount' => $query->sum('tax_amount'),
            'by_status' => $query->groupBy('status')
                ->selectRaw('status, sum(amount) as total_amount, count(*) as count')
                ->get(),
            'by_type' => $query->groupBy('type')
                ->selectRaw('type, sum(amount) as total_amount, count(*) as count')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}

