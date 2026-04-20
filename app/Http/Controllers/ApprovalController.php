<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

class ApprovalController extends Controller
{
    use ChecksPermission;
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('approvals.view')) {
            return $response;
        }
        
        $query = Approval::with(['applicant', 'approver']);
        
        // 【账套过滤】
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }
        
        // 只有当参数有值时才进行过滤
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('applicant_id')) {
            $query->where('applicant_id', $request->applicant_id);
        }
        
        if ($request->filled('approver_id')) {
            $query->where('approver_id', $request->approver_id);
        }
        
        if ($request->filled('applicant_name')) {
            $query->whereHas('applicant', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->applicant_name . '%');
            });
        }
        
        // 处理日期范围搜索
        if ($request->filled('date_range') && is_array($request->date_range) && count($request->date_range) === 2) {
            $query->whereBetween('created_at', [$request->date_range[0], $request->date_range[1]]);
        }
        
        $perPage = $request->get('per_page', 20);
        $approvals = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        // 格式化返回数据，添加前端需要的字段
        $approvals->getCollection()->transform(function ($approval) {
            $approval->applicant_name = $approval->applicant ? $approval->applicant->name : '';
            $approval->project_name = $approval->project_id ? '项目-' . $approval->project_id : '-';
            return $approval;
        });
        
        return response()->json([
            'success' => true,
            'data' => $approvals->items(), // 返回数据项
            'total' => $approvals->total(), // 返回总数
            'current_page' => $approvals->currentPage(),
            'per_page' => $approvals->perPage(),
            'last_page' => $approvals->lastPage()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'related_id' => 'nullable|integer', // 暂时改为可选，等前端准备好后再改为required
            'project_id' => 'nullable|integer', // 添加project_id验证
            'title' => 'nullable|string', // 添加title验证
            'reason' => 'nullable|string',
            'expected_completion_date' => 'nullable|date',
            'stamp_method' => 'nullable|in:online,offline', // 盖章方式
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
        
        $approval = Approval::create([
            'type' => $request->type,
            'related_id' => $request->related_id,
            'applicant_id' => $request->user()->id,
            'reason' => $request->reason,
            'expected_completion_date' => $request->expected_completion_date,
            'stamp_method' => $request->stamp_method ?? 'online', // 默认线上盖章
            'submitted_at' => now(),
            'account_set_id' => $currentAccountSetId,
        ]);

        return response()->json([
            'success' => true,
            'message' => '审批申请创建成功',
            'data' => $approval->load(['applicant'])
        ]);
    }

    public function show($id)
    {
        $approval = Approval::with(['applicant', 'approver'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $approval
        ]);
    }

    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'nullable|string',
            'signature' => 'nullable|string',
            'seal' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $approval = Approval::findOrFail($id);
        
        if ($approval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '该审批申请不是待审批状态'
            ], 422);
        }

        $approval->approve(
            $request->user()->id, // 当前登录用户ID
            $request->comment,
            $request->signature,
            $request->seal
        );

        return response()->json([
            'success' => true,
            'message' => '审批通过'
        ]);
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $approval = Approval::findOrFail($id);
        
        if ($approval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '该审批申请不是待审批状态'
            ], 422);
        }

        $approval->reject($request->user()->id, $request->comment);

        return response()->json([
            'success' => true,
            'message' => '审批已拒绝'
        ]);
    }

    public function returnToApplicant(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $approval = Approval::findOrFail($id);
        
        if ($approval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => '该审批申请不是待审批状态'
            ], 422);
        }

        $approval->returnToApplicant($request->user()->id, $request->comment);

        return response()->json([
            'success' => true,
            'message' => '已退回给申请人'
        ]);
    }

    public function getPending(Request $request)
    {
        $query = Approval::with(['applicant', 'approver'])
            ->where('status', 'pending');
        
        if ($request->has('approver_id')) {
            $query->where('approver_id', $request->approver_id);
        }
        
        $approvals = $query->orderBy('created_at', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $approvals
        ]);
    }

    public function getOverdue()
    {
        $overdue = Approval::with(['applicant', 'approver'])
            ->where('status', 'pending')
            ->where('expected_completion_date', '<', now())
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $overdue
        ]);
    }
}