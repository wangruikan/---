<?php

namespace App\Http\Controllers;

use App\Models\InvoiceApplication;
use App\Models\InvoiceItem;
use App\Models\InvoiceProject;
use App\Models\ProcessApproval;
use App\Models\ApprovalNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Traits\ChecksPermission;

/**
 * 发票申请控制器
 */
class InvoiceApplicationController extends Controller
{
    use ChecksPermission;
    /**
     * 获取申请列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('invoice_applications.view')) {
            return $response;
        }
        
        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        $query = InvoiceApplication::where('account_set_id', $accountSetId)
            ->with(['submitter:id,name', 'creator:id,name', 'items', 'approvalInstance']);

        // 按年份筛选
        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }

        // 按月份筛选
        if ($request->has('month')) {
            $query->where('month', $request->input('month'));
        }

        // 按状态筛选
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // 搜索
        if ($request->has('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('application_no', 'like', "%{$keyword}%");
            });
        }

        // 排序
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // 分页
        $perPage = $request->input('per_page', 15);
        $applications = $query->paginate($perPage);

        // 添加总金额和审批状态
        $applications->getCollection()->transform(function ($application) {
            $application->total_amount = $application->items->sum('amount');
            
            // 添加审批状态
            if ($application->approvalInstance) {
                $application->approval_status = $application->approvalInstance->status;
            } else {
                $application->approval_status = null;
            }
            
            // 确保 can_resubmit 字段被包含（通过访问 accessor 触发计算）
            $application->makeVisible(['can_resubmit']);
            
            return $application;
        });

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * 创建申请
     */
    public function store(Request $request)
    {
        if ($response = $this->checkPermission('invoice_applications.create')) {
            return $response;
        }
        
        $validator = Validator::make($request->all(), [
            'task_name' => 'required|string|max:100',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'project_id' => 'required|exists:projects,id',
        ], [
            'task_name.required' => '任务名称不能为空',
            'task_name.max' => '任务名称不能超过100个字符',
            'year.required' => '年份不能为空',
            'month.required' => '月份不能为空',
            'project_id.required' => '请选择项目',
            'project_id.exists' => '项目不存在',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $accountSetId = $request->input('current_account_set_id', $user->account_set_id);

        // 获取项目名称
        $project = \App\Models\Project::find($request->input('project_id'));
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => '项目不存在'
            ], 404);
        }

        // 生成申请单号
        $applicationNo = InvoiceApplication::generateApplicationNo();

        $application = InvoiceApplication::create([
            'account_set_id' => $accountSetId,
            'application_no' => $applicationNo,
            'task_name' => $request->input('task_name'),
            'year' => $request->input('year'),
            'month' => $request->input('month'),
            'project_name' => $project->name,  // 保存项目名称
            'status' => InvoiceApplication::STATUS_NORMAL,  // 业务状态：正常
            'approval_status' => null,  // 审批状态：未提交
            'submitter_id' => $user->id,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => '创建成功',
            'data' => $application->load('items')
        ]);
    }

    /**
     * 获取申请详情
     */
    public function show($id)
    {
        $application = InvoiceApplication::with([
            'submitter:id,name',
            'creator:id,name',
            'items.invoiceProject',
            'approvalInstance'
        ])->find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        // 计算总金额
        $application->total_amount = $application->items->sum('amount');

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * 添加明细项
     */
    public function addItem(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'invoice_project_id' => 'required|exists:invoice_projects,id',
            'amount' => 'required|numeric|min:0',
            'remark' => 'nullable|string|max:500',
        ], [
            'invoice_project_id.required' => '请选择项目',
            'invoice_project_id.exists' => '项目不存在',
            'amount.required' => '金额不能为空',
            'amount.numeric' => '金额必须是数字',
            'amount.min' => '金额不能小于0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $application = InvoiceApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        if (!$application->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许编辑'
            ], 400);
        }

        // 获取项目名称
        $project = InvoiceProject::find($request->input('invoice_project_id'));
        
        // 获取当前最大序号
        $maxSequence = $application->items()->max('sequence') ?? 0;

        // 使用项目名称作为 item_name
        $item = InvoiceItem::create([
            'application_id' => $application->id,
            'invoice_project_id' => $request->input('invoice_project_id'),
            'project_name' => $project->project_name,
            'sequence' => $maxSequence + 1,
            'item_name' => $project->project_name, // 自动使用项目名称
            'amount' => $request->input('amount'),
            'remark' => $request->input('remark'),
        ]);

        return response()->json([
            'success' => true,
            'message' => '添加成功',
            'data' => $item->load('invoiceProject')
        ]);
    }

    /**
     * 更新明细项
     */
    public function updateItem(Request $request, $id, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'invoice_project_id' => 'required|exists:invoice_projects,id',
            'amount' => 'required|numeric|min:0',
            'remark' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $application = InvoiceApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        if (!$application->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许编辑'
            ], 400);
        }

        $item = InvoiceItem::where('application_id', $id)->find($itemId);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => '明细不存在'
            ], 404);
        }

        // 获取项目名称
        $project = InvoiceProject::find($request->input('invoice_project_id'));

        // 使用项目名称作为 item_name
        $item->update([
            'invoice_project_id' => $request->input('invoice_project_id'),
            'project_name' => $project->project_name,
            'item_name' => $project->project_name, // 自动使用项目名称
            'amount' => $request->input('amount'),
            'remark' => $request->input('remark'),
        ]);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $item->load('invoiceProject')
        ]);
    }

    /**
     * 删除明细项
     */
    public function deleteItem($id, $itemId)
    {
        $application = InvoiceApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        if (!$application->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许编辑'
            ], 400);
        }

        $item = InvoiceItem::where('application_id', $id)->find($itemId);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => '明细不存在'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    /**
     * 生成Excel扣除明细表
     */
    public function generateExcel($id)
    {
        $application = InvoiceApplication::with(['items' => function ($query) {
            $query->orderBy('sequence');
        }])->find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        if ($application->items->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => '请先添加明细项'
            ], 400);
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 设置列宽
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getColumnDimension('D')->setWidth(30);

            // 标题
            $sheet->setCellValue('A1', '扣除明细表');
            $sheet->mergeCells('A1:D1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // 项目和单位信息
            $sheet->setCellValue('A2', '项目：');
            $sheet->setCellValueExplicit('B2', $application->project_name ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->setCellValue('A3', '单位：元');

            // 表头
            $sheet->setCellValue('A4', '序号');
            $sheet->setCellValue('B4', '名称');
            $sheet->setCellValue('C4', '金额');
            $sheet->setCellValue('D4', '备注');

            // 设置表头样式
            $headerStyle = [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0'],
                ],
            ];
            $sheet->getStyle('A4:D4')->applyFromArray($headerStyle);

            // 填充数据
            $row = 5;
            $totalAmount = 0;
            foreach ($application->items as $item) {
                $sheet->setCellValue('A' . $row, $item->sequence);
                $sheet->setCellValue('B' . $row, $item->item_name);
                $sheet->setCellValue('C' . $row, number_format($item->amount, 2));
                $sheet->setCellValue('D' . $row, $item->remark ?? '');
                
                $totalAmount += $item->amount;
                $row++;
            }

            // 合计行
            $sheet->setCellValue('A' . $row, '');
            $sheet->setCellValue('B' . $row, '合计');
            $sheet->setCellValue('C' . $row, number_format($totalAmount, 2));
            $sheet->setCellValue('D' . $row, '');

            // 设置数据区域样式
            $dataStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('A4:D' . $row)->applyFromArray($dataStyle);

            // 合计行加粗
            $sheet->getStyle('B' . $row)->getFont()->setBold(true);
            $sheet->getStyle('C' . $row)->getFont()->setBold(true);

            // 保存文件到 public 磁盘
            $filename = '扣除明细表_' . $application->application_no . '_' . date('YmdHis') . '.xlsx';
            $filepath = 'invoice_attachments/' . $filename;
            
            $writer = new Xlsx($spreadsheet);
            
            // 确保目录存在（使用 public 磁盘）
            Storage::disk('public')->makeDirectory('invoice_attachments');
            
            // 保存到 storage/app/public/ 目录
            $fullPath = storage_path('app/public/' . $filepath);
            $writer->save($fullPath);

            // 获取文件大小
            $fileSize = filesize($fullPath);

            // 自动添加到附件列表
            $attachments = $application->attachments ?? [];
            $attachments[] = [
                'filename' => $filename,
                'path' => $filepath,
                'url' => Storage::disk('public')->url($filepath),
                'size' => $fileSize,
                'uploaded_at' => now()->toDateTimeString(),
            ];

            $application->update(['attachments' => $attachments]);

            return response()->json([
                'success' => true,
                'message' => '生成成功，已自动添加到附件列表',
                'data' => [
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'url' => Storage::disk('public')->url($filepath),
                    'size' => $fileSize
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('生成Excel失败', [
                'application_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '生成失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB
        ], [
            'file.required' => '请选择文件',
            'file.max' => '文件大小不能超过10MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $application = InvoiceApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        if (!$application->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许编辑'
            ], 400);
        }

        try {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            // 使用 public 磁盘保存
            $path = $file->storeAs('invoice_attachments', $filename, 'public');

            $attachments = $application->attachments ?? [];
            $attachments[] = [
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'size' => $file->getSize(),
                'uploaded_at' => now()->toDateTimeString(),
            ];

            $application->update(['attachments' => $attachments]);

            return response()->json([
                'success' => true,
                'message' => '上传成功',
                'data' => end($attachments)
            ]);

        } catch (\Exception $e) {
            \Log::error('上传附件失败', [
                'application_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除附件
     */
    public function deleteAttachment(Request $request, $id)
    {
        $application = InvoiceApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        if (!$application->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许编辑'
            ], 400);
        }

        $path = $request->input('path');
        $attachments = $application->attachments ?? [];
        
        $attachments = array_filter($attachments, function ($attachment) use ($path) {
            return $attachment['path'] !== $path;
        });

        // 删除文件（使用 public 磁盘）
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $application->update(['attachments' => array_values($attachments)]);

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    /**
     * 提交审批
     */
    public function submit(Request $request, $id)
    {
        $application = InvoiceApplication::with('items')->find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        if (!$application->canSubmit()) {
            return response()->json([
                'success' => false,
                'message' => '请先添加明细项和上传附件'
            ], 400);
        }

        // 验证项目名称
        if (empty($application->project_name)) {
            return response()->json([
                'success' => false,
                'message' => '请先填写项目名称'
            ], 400);
        }

        $user = Auth::user();
        $accountSetId = $application->account_set_id;
        
        // 获取盖章方式，默认线上
        $stampMethod = $request->input('stamp_method', 'online');

        DB::beginTransaction();
        try {
            // 获取审批人配置（跳过经办，从第二个审批节点开始）
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
                'business_type' => '发票申请',
                'business_id' => $application->id,
                'current_step' => 2, // 从第二个审批节点开始
                'total_steps' => $approvers->count() + 1, // 包括经办
                'status' => 'pending',
                'created_by' => $user->id,
                'stamp_method' => $stampMethod, // 保存盖章方式
            ]);

            // 添加发票附件到审批实例
            if ($application->attachments) {
                $attachments = is_array($application->attachments) ? $application->attachments : json_decode($application->attachments, true);
                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        \App\Models\ApprovalAttachment::create([
                            'instance_id' => $instance->id,
                            'file_path' => $attachment['path'] ?? '',
                            'file_name' => $attachment['filename'] ?? '发票附件',
                            'file_size' => $attachment['size'] ?? 0,
                            'file_type' => pathinfo($attachment['filename'] ?? '', PATHINFO_EXTENSION),
                        ]);
                    }
                }
            }

            // 创建经办节点记录（自动通过）
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

            // 更新审批状态为审批中，业务状态保持不变
            $application->update([
                'approval_status' => InvoiceApplication::APPROVAL_STATUS_PENDING,
                'approval_instance_id' => $instance->id,
                'submitted_at' => now(),
                // status 业务状态保持不变（正常或红冲）
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '提交成功',
                'data' => $application->fresh(['approvalInstance'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('提交审批失败', [
                'application_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '提交失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 重新发起（红冲后）
     */
    public function resubmit($id)
    {
        $application = InvoiceApplication::with('items')->find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        if (!$application->canResubmit()) {
            return response()->json([
                'success' => false,
                'message' => '只有红冲状态的申请才能重新发起'
            ], 400);
        }

        if ($application->items->count() === 0 || empty($application->attachments)) {
            return response()->json([
                'success' => false,
                'message' => '请先添加明细项和上传附件'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // 获取审批人配置（跳过经办，从第二个审批节点开始）
            $user = Auth::user();
            $accountSetId = $application->account_set_id;
            $approvers = DB::table('account_set_users')
                ->where('account_set_id', $accountSetId)
                ->where('approval_level', '>', 1) // 跳过经办（级别1）
                ->orderBy('approval_level')
                ->get();

            if ($approvers->isEmpty()) {
                throw new \Exception('未找到审批人员配置');
            }

            // 创建新的审批实例
            $instance = \App\Models\ApprovalInstance::create([
                'account_set_id' => $accountSetId,
                'business_type' => '发票申请（重新提交）',
                'business_id' => $application->id,
                'current_step' => 2,
                'total_steps' => $approvers->count() + 1,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            // 添加附件
            if ($application->attachments) {
                $attachments = is_array($application->attachments) ? $application->attachments : json_decode($application->attachments, true);
                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        \App\Models\ApprovalAttachment::create([
                            'instance_id' => $instance->id,
                            'file_path' => $attachment['path'] ?? '',
                            'file_name' => $attachment['filename'] ?? '发票附件',
                            'file_size' => $attachment['size'] ?? 0,
                            'file_type' => pathinfo($attachment['filename'] ?? '', PATHINFO_EXTENSION),
                        ]);
                    }
                }
            }

            // 创建经办节点记录（自动通过）
            \App\Models\ApprovalRecord::create([
                'instance_id' => $instance->id,
                'step_order' => 1,
                'step_name' => '经办',
                'approver_id' => $user->id,
                'approver_name' => $user->name,
                'status' => 'approved',
                'comment' => '经办重新提交，自动通过',
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

            // 更新原申请：审批状态改为审批中，关联新审批实例
            // 业务状态保持红冲不变
            $application->update([
                'approval_status' => InvoiceApplication::APPROVAL_STATUS_PENDING,  // 审批状态：审批中
                'approval_instance_id' => $instance->id,
                'submitted_at' => now(),
                // status 业务状态保持 red_flushed 不变
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '重新发起成功，已提交审批',
                'data' => $application->load(['items', 'approvalInstance'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('重新提交失败', [
                'application_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '重新提交失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除申请
     */
    public function destroy($id)
    {
        if ($response = $this->checkPermission('invoice_applications.delete')) {
            return $response;
        }
        
        $application = InvoiceApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        // 只能删除未提交审批的申请
        if ($application->approval_status !== null) {
            return response()->json([
                'success' => false,
                'message' => '已提交审批的申请不能删除'
            ], 400);
        }

        // 删除附件文件（使用 public 磁盘）
        if (!empty($application->attachments)) {
            foreach ($application->attachments as $attachment) {
                if (isset($attachment['path']) && Storage::disk('public')->exists($attachment['path'])) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }
        }

        // 删除明细项
        $application->items()->delete();

        // 删除申请
        $application->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    /**
     * 更新项目名称
     */
    public function updateProject($id, Request $request)
    {
        $application = InvoiceApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        // 只有草稿或已驳回状态可以修改
        if (!$application->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许修改'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|max:100',
        ], [
            'project_name.required' => '项目名称不能为空',
            'project_name.max' => '项目名称不能超过100个字符',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $application->update([
            'project_name' => $request->input('project_name')
        ]);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $application
        ]);
    }

    /**
     * 更新开票详细信息
     */
    public function updateInvoiceDetails($id, Request $request)
    {
        $application = InvoiceApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => '申请不存在'
            ], 404);
        }

        // 只有可编辑状态才能修改
        if (!$application->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许修改'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'period_year' => 'nullable|integer|min:2000|max:2100',
            'period_month' => 'nullable|integer|min:1|max:12',
            'company_name' => 'nullable|string|max:200',
            'application_date' => 'nullable|date',
            'invoice_method' => 'nullable|string|max:20',
            'invoice_type' => 'nullable|string|max:50',
            'deduction_amount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'amount_excluding_tax' => 'nullable|numeric|min:0',
            'invoice_tax_amount' => 'nullable|numeric|min:0',
            'invoice_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'invoice_date' => 'nullable|date',
            'is_completed' => 'nullable|boolean',
            'invoicer' => 'nullable|string|max:100',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_remark' => 'nullable|string',
        ], [
            'period_year.integer' => '所属期年份必须是整数',
            'period_year.min' => '所属期年份不能小于2000',
            'period_year.max' => '所属期年份不能大于2100',
            'period_month.integer' => '所属期月份必须是整数',
            'period_month.min' => '所属期月份必须在1-12之间',
            'period_month.max' => '所属期月份必须在1-12之间',
            'company_name.max' => '单位名称不能超过200个字符',
            'invoice_method.in' => '开票方式只能是：全额、差额、无',
            'tax_rate.min' => '税率不能为负',
            'tax_rate.max' => '税率不能超过100%',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'period_year',
            'period_month',
            'company_name',
            'application_date',
            'invoice_method',
            'invoice_type',
            'deduction_amount',
            'tax_rate',
            'amount_excluding_tax',
            'invoice_tax_amount',
            'invoice_amount',
            'tax_amount',
            'invoice_date',
            'is_completed',
            'invoicer',
            'invoice_number',
            'invoice_remark',
        ]);

        if (array_key_exists('invoice_method', $updateData)) {
            $rawInvoiceMethod = trim((string)($updateData['invoice_method'] ?? ''));
            $normalizedKey = mb_strtolower($rawInvoiceMethod);
            $invoiceMethodMap = [
                'full' => 'full',
                '全额' => 'full',
                'diff' => 'diff',
                '差额' => 'diff',
                'partial' => 'diff',
                '缺额' => 'diff',
                'none' => 'none',
                '无' => 'none',
            ];

            if ($rawInvoiceMethod === '') {
                $updateData['invoice_method'] = null;
            } elseif (isset($invoiceMethodMap[$normalizedKey])) {
                $updateData['invoice_method'] = $invoiceMethodMap[$normalizedKey];
            } else {
                $updateData['invoice_method'] = $rawInvoiceMethod;
            }
        }

        $application->update($updateData);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $application
        ]);
    }

    /**
     * 检查创建发票申请的权限
     * 第2、3、4审批节点的审批人可以创建
     */
    public function checkCreatePermission(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('account_set_id', $user->account_set_id);

        if (!$accountSetId) {
            return response()->json([
                'success' => true,
                'has_access' => false,
                'message' => '请选择账套'
            ]);
        }

        // 管理员始终有权限
        if (in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => true,
                'has_access' => true,
                'role' => $user->role
            ]);
        }

        // 检查用户是否是该账套的第2、3、4审批节点的审批人
        $approver = \DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('user_id', $user->id)
            ->whereIn('approval_level', [2, 3, 4])
            ->first();

        $hasAccess = !is_null($approver);

        return response()->json([
            'success' => true,
            'has_access' => $hasAccess,
            'role' => $user->role,
            'approval_level' => $approver ? $approver->approval_level : null
        ]);
    }

    /**
     * 导出发票记录Excel表格
     */
    public function exportInvoiceRecords(Request $request)
    {
        $user = Auth::user();
        $accountSetId = $request->input('account_set_id', $user->account_set_id);
        $year = $request->input('year');
        $month = $request->input('month');

        if (!$year || !$month) {
            return response()->json([
                'success' => false,
                'message' => '请选择年份和月份'
            ], 400);
        }

        // 查询数据
        $query = InvoiceApplication::where('account_set_id', $accountSetId)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('created_at', 'asc');

        $applications = $query->get();

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 设置标题行（第1行）
            $accountSet = \App\Models\AccountSet::find($accountSetId);
            $companyName = $accountSet ? $accountSet->name : '汇邦人力';
            $title = $companyName . $year . '年' . $month . '月开票登记表';
            
            $sheet->setCellValue('A1', $title);
            $sheet->mergeCells('A1:S1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(30);

            // 设置表头（第2行）
            $headers = [
                'A2' => '序号',
                'B2' => '所属期',
                'C2' => '单位名称',
                'D2' => '申请日期',
                'E2' => '开票方式',
                'F2' => '开票种类',
                'G2' => '状态',
                'H2' => '项目名称',
                'I2' => '开票金额',
                'J2' => '扣除额',
                'K2' => '税率',
                'L2' => '不含税金额',
                'M2' => '开票税额',
                'N2' => '税金',
                'O2' => '开票日期',
                'P2' => '是否完成',
                'Q2' => '开票人',
                'R2' => '发票号码',
                'S2' => '备注'
            ];

            foreach ($headers as $cell => $header) {
                $sheet->setCellValue($cell, $header);
            }

            // 设置表头样式
            $headerStyle = [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ];
            $sheet->getStyle('A2:S2')->applyFromArray($headerStyle);
            $sheet->getRowDimension(2)->setRowHeight(25);

            // 填充数据（从第3行开始）
            $row = 3;
            $index = 1;
            foreach ($applications as $application) {
                // 开票方式映射
                $invoiceMethodMap = [
                    'full' => '全额',
                    'partial' => '缺额',
                    'none' => '无'
                ];

                $sheet->setCellValue('A' . $row, $index++);
                $sheet->setCellValue('B' . $row, ($application->period_year ?? $application->year) . '-' . str_pad($application->period_month ?? $application->month, 2, '0', STR_PAD_LEFT));
                $sheet->setCellValue('C' . $row, $application->company_name ?? '');
                $sheet->setCellValue('D' . $row, $application->application_date ?? '');
                $sheet->setCellValue('E' . $row, $invoiceMethodMap[$application->invoice_method] ?? '');
                $sheet->setCellValue('F' . $row, $application->invoice_type ?? '普票');
                $sheet->setCellValue('G' . $row, $application->status_text ?? '');
                $sheet->setCellValue('H' . $row, $application->project_name ?? '');
                $sheet->setCellValue('I' . $row, $application->amount_excluding_tax ?? 0);
                $sheet->setCellValue('J' . $row, $application->deduction_amount ?? 0);
                $sheet->setCellValue('K' . $row, $application->tax_rate ?? 0);
                $sheet->setCellValue('L' . $row, $application->amount_excluding_tax ?? 0);
                $sheet->setCellValue('M' . $row, $application->invoice_tax_amount ?? 0);
                $sheet->setCellValue('N' . $row, $application->tax_amount ?? 0);
                $sheet->setCellValue('O' . $row, $application->invoice_date ?? '');
                $sheet->setCellValue('P' . $row, $application->is_completed ? '是' : '否');
                $sheet->setCellValue('Q' . $row, $application->invoicer ?? '');
                $sheet->setCellValue('R' . $row, $application->invoice_number ?? '');
                $sheet->setCellValue('S' . $row, $application->invoice_remark ?? '');

                $row++;
            }

            // 设置数据区域样式
            if ($row > 3) {
                $dataRange = 'A3:S' . ($row - 1);
                $dataStyle = [
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ];
                $sheet->getStyle($dataRange)->applyFromArray($dataStyle);

                // 数字列右对齐
                $numberColumns = ['I', 'J', 'K', 'L', 'M', 'N'];
                foreach ($numberColumns as $col) {
                    $sheet->getStyle($col . '3:' . $col . ($row - 1))
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }

            // 设置列宽
            $columnWidths = [
                'A' => 8,   // 序号
                'B' => 12,  // 所属期
                'C' => 25,  // 单位名称
                'D' => 12,  // 申请日期
                'E' => 10,  // 开票方式
                'F' => 10,  // 开票种类
                'G' => 10,  // 状态
                'H' => 20,  // 项目名称
                'I' => 12,  // 开票金额
                'J' => 12,  // 扣除额
                'K' => 10,  // 税率
                'L' => 12,  // 不含税金额
                'M' => 12,  // 开票税额
                'N' => 12,  // 税金
                'O' => 12,  // 开票日期
                'P' => 10,  // 是否完成
                'Q' => 12,  // 开票人
                'R' => 18,  // 发票号码
                'S' => 20   // 备注
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            // 生成文件
            $filename = $companyName . $year . '年' . $month . '月开票登记表.xlsx';
            $writer = new Xlsx($spreadsheet);
            
            // 输出到浏览器
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . urlencode($filename) . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '导出失败：' . $e->getMessage()
            ], 500);
        }
    }
}
