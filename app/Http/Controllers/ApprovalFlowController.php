<?php

namespace App\Http\Controllers;

use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApprovalFlowController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * 创建审批流程
     */
    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'business_type' => 'required|string',
            'business_id' => 'required|integer',
            'employee_id' => 'integer|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请选择账套'
                ], 400);
            }

            // 获取业务数据的附件信息（自动关联业务文件）
            $attachments = [];
            
            if ($request->business_type === 'employee_contract') {
                $contract = \App\Models\EmployeeContract::find($request->business_id);
                if ($contract && $contract->contract_file) {
                    $attachments[] = [
                        'path' => $contract->contract_file,
                        'name' => $contract->original_filename,
                        'size' => null,
                        'type' => 'application/pdf',
                    ];
                }
            }

            $stampMethod = $request->input('stamp_method', 'online'); // 盖章方式
            $instance = $this->approvalService->createApprovalInstance(
                $accountSetId,
                $request->business_type,
                $request->business_id,
                $request->user()->id,
                $attachments,
                $request->input('skip_initiator', false),
                $stampMethod // 盖章方式
            );

            return response()->json([
                'success' => true,
                'message' => '审批流程创建成功',
                'data' => $instance->load('records')
            ]);

        } catch (\Exception $e) {
            Log::error('创建审批流程失败', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 我的待办
     */
    public function myTasks(Request $request)
    {
        $user = $request->user();
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');

        \Log::info('我的待办查询', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'account_set_id' => $accountSetId,
            'header_account_set_id' => $request->header('X-Account-Set-Id'),
            'param_account_set_id' => $request->input('current_account_set_id'),
        ]);

        $query = ApprovalRecord::with(['instance.creator', 'instance.attachments'])
            ->where('approver_id', $user->id)
            ->where('status', 'pending');

        if ($accountSetId) {
            $query->whereHas('instance', function ($q) use ($accountSetId) {
                $q->where('account_set_id', $accountSetId);
            });
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(20);

        \Log::info('我的待办查询结果', [
            'total' => $tasks->total(),
            'count' => $tasks->count(),
            'business_types' => $tasks->pluck('instance.business_type')->toArray(),
        ]);

        // 附加业务数据
        foreach ($tasks as $task) {
            $task->business_data = $task->instance->getBusinessData();
        }

        return response()->json([
            'success' => true,
            'data' => $tasks->items(),
            'total' => $tasks->total(),
            'current_page' => $tasks->currentPage(),
            'per_page' => $tasks->perPage(),
        ]);
    }

    /**
     * 我审批的（我已经处理过的审批记录）
     */
    public function myApproved(Request $request)
    {
        $user = $request->user();
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');

        $query = ApprovalRecord::with(['instance.creator', 'instance.attachments'])
            ->where('approver_id', $user->id)
            ->whereIn('status', ['approved', 'rejected', 'returned']); // 添加 'returned' 状态

        if ($accountSetId) {
            $query->whereHas('instance', function ($q) use ($accountSetId) {
                $q->where('account_set_id', $accountSetId);
            });
        }

        $records = $query->orderBy('approved_at', 'desc')->paginate(20);

        // 附加业务数据
        foreach ($records as $record) {
            $record->business_data = $record->instance->getBusinessData();
        }

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'total' => $records->total(),
            'current_page' => $records->currentPage(),
            'per_page' => $records->perPage(),
        ]);
    }

    /**
     * 我发起的
     */
    public function myInitiated(Request $request)
    {
        $user = $request->user();
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');

        $query = ApprovalInstance::with(['records', 'attachments', 'creator'])
            ->where('created_by', $user->id);

        if ($accountSetId) {
            $query->where('account_set_id', $accountSetId);
        }

        $instances = $query->orderBy('created_at', 'desc')->paginate(20);

        // 附加业务数据
        foreach ($instances as $instance) {
            $instance->business_data = $instance->getBusinessData();
        }

        return response()->json([
            'success' => true,
            'data' => $instances->items(),
            'total' => $instances->total(),
            'current_page' => $instances->currentPage(),
            'per_page' => $instances->perPage(),
        ]);
    }

    /**
     * 抄送给我的审批
     */
    public function ccToMe(Request $request)
    {
        $user = $request->user();
        $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');

        // 通过抄送表查找抄送给我的审批流程
        $query = ApprovalInstance::with(['records', 'attachments', 'creator'])
            ->whereHas('ccUsers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        if ($accountSetId) {
            $query->where('account_set_id', $accountSetId);
        }

        $instances = $query->orderBy('created_at', 'desc')->paginate(20);

        // 附加业务数据
        foreach ($instances as $instance) {
            $instance->business_data = $instance->getBusinessData();
        }

        return response()->json([
            'success' => true,
            'data' => $instances->items(),
            'total' => $instances->total(),
            'current_page' => $instances->currentPage(),
            'per_page' => $instances->perPage(),
        ]);
    }

    /**
     * 审批详情
     */
    public function show($id)
    {
        $instance = ApprovalInstance::with(['records', 'creator', 'ccUsers', 'attachments'])->findOrFail($id);
        $instance->business_data = $instance->getBusinessData();

        return response()->json([
            'success' => true,
            'data' => $instance
        ]);
    }

    /**
     * 审批通过
     */
    public function approve(Request $request, $recordId)
    {
        $validator = \Validator::make($request->all(), [
            'comment' => 'string|nullable',
            'cc_users' => 'array|nullable',
            'signature_id' => 'integer|nullable',
            'seal_id' => 'integer|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $instance = $this->approvalService->approve(
                $recordId,
                $request->user()->id,
                $request->comment,
                $request->cc_users ?? [],
                $request->signature_id,
                $request->seal_id
            );

            return response()->json([
                'success' => true,
                'message' => '审批成功',
                'data' => $instance->load('records')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 上传合成后的PDF
     */
    public function uploadSignedPDF(Request $request, $recordId)
    {
        $validator = \Validator::make($request->all(), [
            'signed_pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $record = ApprovalRecord::findOrFail($recordId);
            $instance = $record->instance;
            
            // 处理员工合同：覆盖原PDF
            if ($instance->business_type === 'employee_contract') {
                $contract = \App\Models\EmployeeContract::find($instance->business_id);
                if (!$contract) {
                    return response()->json([
                        'success' => false,
                        'message' => '合同不存在'
                    ], 404);
                }
                
                // 删除旧PDF
                if ($contract->contract_file) {
                    $oldPath = storage_path('app/public/' . $contract->contract_file);
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
                
                // 保存新PDF（覆盖）
                $file = $request->file('signed_pdf');
                $filename = $contract->contract_file ?: ('contracts/' . uniqid() . '.pdf');
                $file->storeAs('public/' . dirname($filename), basename($filename));
                
                Log::info('员工合同PDF已更新', [
                    'contract_id' => $contract->id,
                    'record_id' => $recordId,
                    'step_order' => $record->step_order
                ]);
            } 
            // 处理其他类型：覆盖原始PDF文件
            else {
                $file = $request->file('signed_pdf');
                $attachmentId = $request->input('attachment_id'); // 接收前端传来的附件ID
                
                $pdfAttachment = null;
                
                // 优先使用前端指定的附件ID
                if ($attachmentId) {
                    $pdfAttachment = $instance->attachments()
                        ->where('id', $attachmentId)
                        ->first();
                    
                    Log::info('使用前端指定的附件ID', [
                        'attachment_id' => $attachmentId,
                        'found' => !!$pdfAttachment
                    ]);
                }
                
                // 如果前端没有指定或找不到，则查找第一个PDF
                if (!$pdfAttachment) {
                    $pdfAttachment = $instance->attachments()
                        ->where('file_name', 'like', '%.pdf')
                        ->orderBy('created_at', 'asc')
                        ->first();
                    
                    Log::info('未指定附件ID，使用第一个PDF', [
                        'found' => !!$pdfAttachment,
                        'attachment_id' => $pdfAttachment ? $pdfAttachment->id : null
                    ]);
                }
                
                if ($pdfAttachment) {
                    // 找到了原始PDF，覆盖它
                    $oldPath = storage_path('app/public/' . $pdfAttachment->file_path);
                    
                    // 删除旧文件
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                        Log::info('已删除原PDF文件', ['path' => $pdfAttachment->file_path]);
                    }
                    
                    // 保存新PDF到原路径
                    $directory = dirname($pdfAttachment->file_path);
                    $fileName = basename($pdfAttachment->file_path);
                    $file->storeAs('public/' . $directory, $fileName);
                    
                    // 更新附件记录的文件大小
                    $pdfAttachment->update([
                        'file_size' => $file->getSize(),
                    ]);
                    
                    Log::info('签名PDF已覆盖原文件', [
                        'instance_id' => $instance->id,
                        'business_type' => $instance->business_type,
                        'record_id' => $recordId,
                        'attachment_id' => $pdfAttachment->id,
                        'file_path' => $pdfAttachment->file_path,
                        'from_frontend' => !!$attachmentId
                    ]);
                } else {
                    // 没有找到原始PDF，创建新附件
                    $fileName = 'signed_' . time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs('approvals/signed', $fileName, 'public');
                    
                    $attachment = $instance->attachments()->create([
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                    
                    Log::info('签名PDF已保存为新附件（未找到原PDF）', [
                        'instance_id' => $instance->id,
                        'business_type' => $instance->business_type,
                        'record_id' => $recordId,
                        'attachment_id' => $attachment->id
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'PDF处理成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('上传签名PDF失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 退回上一级
     */
    public function returnToPrevious(Request $request, $recordId)
    {
        $validator = \Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $instance = $this->approvalService->returnToPrevious(
                $recordId,
                $request->user()->id,
                $request->comment
            );

            return response()->json([
                'success' => true,
                'message' => '已退回上一级',
                'data' => $instance->load('records')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 驳回（拒绝整个流程）
     */
    public function reject(Request $request, $recordId)
    {
        $validator = \Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $instance = $this->approvalService->reject(
                $recordId,
                $request->user()->id,
                $request->comment
            );

            return response()->json([
                'success' => true,
                'message' => '审批已驳回',
                'data' => $instance->load('records')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 批量盖章
     */
    public function batchStamp(Request $request, $recordId)
    {
        $validator = \Validator::make($request->all(), [
            'files' => 'required|array|min:1',
            'files.*.file_path' => 'required|string',
            'files.*.position' => 'required|array',
            'files.*.position.x' => 'required|numeric',
            'files.*.position.y' => 'required|numeric',
            'files.*.position.page' => 'required|integer|min:1',
            'files.*.position.scale' => 'required|numeric|min:0.1|max:2',
            'signature_id' => 'required|integer|exists:signatures,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $signature = \App\Models\Signature::findOrFail($request->signature_id);
            $signatureImagePath = storage_path('app/public/' . $signature->image_path);
            
            if (!file_exists($signatureImagePath)) {
                throw new \Exception('印章图片不存在');
            }

            $results = [];
            
            foreach ($request->files as $fileData) {
                $filePath = $fileData['file_path'];
                $position = $fileData['position'];
                
                $fullPath = storage_path('app/public/' . $filePath);
                
                if (!file_exists($fullPath)) {
                    $results[] = [
                        'file_path' => $filePath,
                        'success' => false,
                        'message' => '文件不存在'
                    ];
                    continue;
                }

                try {
                    // 使用 FPDI 和 FPDF 进行PDF盖章
                    $pdf = new \setasign\Fpdi\Fpdi();
                    $pageCount = $pdf->setSourceFile($fullPath);
                    
                    // 获取印章图片信息
                    $signatureImage = $signatureImagePath;
                    
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($templateId);
                        
                        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $pdf->useTemplate($templateId);
                        
                        // 只在指定页面添加印章
                        if ($pageNo == $position['page']) {
                            $stampWidth = 40 * $position['scale']; // 基础宽度40mm
                            $stampHeight = 40 * $position['scale']; // 基础高度40mm
                            
                            // 计算位置（从百分比转换为实际坐标）
                            $x = ($position['x'] / 100) * $size['width'];
                            $y = ($position['y'] / 100) * $size['height'];
                            
                            $pdf->Image($signatureImage, $x, $y, $stampWidth, $stampHeight);
                        }
                    }
                    
                    // 保存覆盖原文件
                    $pdf->Output($fullPath, 'F');
                    
                    $results[] = [
                        'file_path' => $filePath,
                        'success' => true,
                        'message' => '盖章成功'
                    ];
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'file_path' => $filePath,
                        'success' => false,
                        'message' => '盖章失败: ' . $e->getMessage()
                    ];
                }
            }

            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failCount = count($results) - $successCount;

            return response()->json([
                'success' => true,
                'message' => "批量盖章完成：成功 {$successCount} 个，失败 {$failCount} 个",
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 替换附件（用于批量盖章后上传合成的PDF）
     */
    public function replaceAttachment(Request $request, $recordId)
    {
        $validator = \Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf|max:20480',
            'attachment_id' => 'required|integer',
            'file_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filePath = $request->file_path;
            $fullPath = storage_path('app/public/' . $filePath);
            
            // 确保目录存在
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // 保存上传的文件，覆盖原文件
            $uploadedFile = $request->file('file');
            $uploadedFile->move($directory, basename($fullPath));
            
            return response()->json([
                'success' => true,
                'message' => '文件替换成功',
                'data' => [
                    'file_path' => $filePath
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '文件替换失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 撤回审批（发起人撤回）
     * 规则：只有当流程在第二个审批人节点，且第二个审批人还未审批时，才能撤回
     */
    public function withdraw(Request $request, $instanceId)
    {
        try {
            $instance = $this->approvalService->withdraw(
                $instanceId,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => '审批已撤回',
                'data' => $instance->load('records')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 上传附件到审批实例
     */
    public function uploadAttachment(Request $request, $instanceId)
    {
        $validator = \Validator::make($request->all(), [
            'file' => 'required|file|max:51200', // 最大50MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $instance = ApprovalInstance::findOrFail($instanceId);
            
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $extension;
            
            // 获取文件信息
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            // 保存文件到 storage/app/public/approval_attachments/{instance_id}/ 目录
            $path = $file->storeAs(
                'approval_attachments/' . $instanceId,
                $filename,
                'public'
            );

            // 创建附件记录
            $attachment = \App\Models\ApprovalAttachment::create([
                'instance_id' => $instanceId,
                'file_path' => $path,
                'file_name' => $originalName,
                'file_size' => $fileSize,
                'file_type' => $mimeType,
                'uploaded_by' => $request->user()->id,
            ]);

            Log::info('审批实例附件上传成功', [
                'instance_id' => $instanceId,
                'attachment_id' => $attachment->id,
                'file_name' => $originalName,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => $attachment->load('uploader')
            ]);

        } catch (\Exception $e) {
            Log::error('上传审批实例附件失败', [
                'instance_id' => $instanceId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '附件上传失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除审批实例附件
     */
    public function deleteAttachment(Request $request, $instanceId, $attachmentId)
    {
        try {
            $attachment = \App\Models\ApprovalAttachment::where('instance_id', $instanceId)
                ->where('id', $attachmentId)
                ->firstOrFail();

            // 删除文件
            $filePath = storage_path('app/public/' . $attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // 删除记录
            $attachment->delete();

            Log::info('审批实例附件删除成功', [
                'instance_id' => $instanceId,
                'attachment_id' => $attachmentId,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件删除成功'
            ]);

        } catch (\Exception $e) {
            Log::error('删除审批实例附件失败', [
                'instance_id' => $instanceId,
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '附件删除失败：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 重新发起审批（驳回后重新提交）
     * 
     * 适用于所有业务类型的驳回后重新发起
     * 逻辑：删除旧审批实例，创建新的审批流程
     */
    public function resubmit(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'business_type' => 'required|string',
            'business_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $businessType = $request->input('business_type');
            $businessId = $request->input('business_id');
            $user = $request->user();
            
            // 获取业务数据
            $businessModel = $this->getBusinessModel($businessType, $businessId);
            
            if (!$businessModel) {
                return response()->json([
                    'success' => false,
                    'message' => '业务数据不存在'
                ], 404);
            }
            
            // 验证是否是发起人
            $creatorField = $this->getCreatorField($businessType);
            if ($businessModel->$creatorField != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => '只有发起人才能重新发起'
                ], 403);
            }
            
            // 验证是否可以重新发起
            if (!$businessModel->canResubmit()) {
                return response()->json([
                    'success' => false,
                    'message' => '当前状态不允许重新发起'
                ], 400);
            }
            
            // 删除旧的审批实例（如果存在）
            $oldInstanceId = $this->getBusinessApprovalInstanceId($businessModel);
            if ($oldInstanceId) {
                $oldInstance = ApprovalInstance::find($oldInstanceId);
                if ($oldInstance) {
                    // 删除审批记录
                    ApprovalRecord::where('instance_id', $oldInstance->id)->delete();
                    // 删除抄送记录
                    \App\Models\ApprovalCCUser::where('instance_id', $oldInstance->id)->delete();
                    // 删除附件记录
                    \App\Models\ApprovalAttachment::where('instance_id', $oldInstance->id)->delete();
                    // 删除审批实例
                    $oldInstance->delete();
                }
            }
            
            // 重置业务数据状态（会设置 status 和清空 approval_instance_id）
            $businessModel->resetForResubmit();
            
            // 刷新模型以获取最新数据
            $businessModel->refresh();
            
            // 获取账套ID
            $accountSetId = $businessModel->account_set_id ?? $request->header('X-Account-Set-Id');
            
            if (!$accountSetId) {
                throw new \Exception('无法获取账套ID');
            }
            
            // 获取业务附件
            $attachments = $this->getBusinessAttachments($businessType, $businessModel);
            
            // 创建新的审批流程
            $instance = $this->approvalService->createApprovalInstance(
                $accountSetId,
                $businessType,
                $businessId,
                $user->id,
                $attachments
            );
            
            // 注意：createApprovalInstance 内部已经调用了 updateBusinessStatus
            // 会自动更新 approval_instance_id 和 status='in_approval'
            // 所以这里不需要再次更新
            
            DB::commit();
            
            Log::info('重新发起审批成功', [
                'business_type' => $businessType,
                'business_id' => $businessId,
                'new_instance_id' => $instance->id,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '重新发起成功',
                'data' => [
                    'instance_id' => $instance->id
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('重新发起审批失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getBusinessApprovalInstanceId($businessModel): ?int
    {
        if (!$businessModel) {
            return null;
        }

        if (isset($businessModel->approval_instance_id) && $businessModel->approval_instance_id) {
            return (int) $businessModel->approval_instance_id;
        }

        if (isset($businessModel->approval_flow_id) && $businessModel->approval_flow_id) {
            return (int) $businessModel->approval_flow_id;
        }

        return null;
    }

    /**
     * 根据业务类型获取业务模型
     */
    private function getBusinessModel($businessType, $businessId)
    {
        $modelMap = [
            'employee_contract' => \App\Models\EmployeeContract::class,
            '工资表审批' => \App\Models\SalaryApproval::class,
            '工资付款申请' => \App\Models\PaymentRequest::class,
            '报销付款申请' => \App\Models\PaymentRequest::class,
            '保险汇总付款申请' => \App\Models\PaymentRequest::class,
            '发票申请' => \App\Models\InvoiceApplication::class,
            '发票申请（重新提交）' => \App\Models\InvoiceApplication::class,
            '保险汇总' => \App\Models\ProcessApproval::class,
            '考勤表审批' => \App\Models\AttendanceSheet::class,
            '报销申请' => \App\Models\Reimbursement::class,
        ];
        
        $modelClass = $modelMap[$businessType] ?? null;
        
        if (!$modelClass) {
            return null;
        }
        
        return $modelClass::find($businessId);
    }
    
    /**
     * 根据业务类型获取创建人字段名
     */
    private function getCreatorField($businessType)
    {
        $fieldMap = [
            'employee_contract' => 'created_by',
            '工资表审批' => 'submitted_by',
            '工资付款申请' => 'submitted_by',
            '报销付款申请' => 'submitted_by',
            '保险汇总付款申请' => 'submitted_by',
            '发票申请' => 'created_by',
            '发票申请（重新提交）' => 'created_by',
            '保险汇总' => 'initiator_id',
            '考勤表审批' => 'created_by',
            '报销申请' => 'created_by',
        ];
        
        return $fieldMap[$businessType] ?? 'created_by';
    }
    
    /**
     * 获取业务附件
     */
    private function getBusinessAttachments($businessType, $businessModel)
    {
        $attachments = [];
        
        try {
            switch ($businessType) {
                case 'employee_contract':
                    if ($businessModel->contract_file) {
                        $attachments[] = [
                            'path' => $businessModel->contract_file,
                            'name' => $businessModel->original_filename ?? '合同文件',
                            'size' => null,
                            'type' => 'application/pdf'
                        ];
                    }
                    break;
                    
                case '工资表审批':
                    // 工资表审批的附件
                    if ($businessModel->attachments) {
                        foreach ($businessModel->attachments as $att) {
                            $attachments[] = [
                                'path' => $att->file_path,
                                'name' => $att->file_name,
                                'size' => $att->file_size,
                                'type' => $att->file_type ?? $att->mime_type
                            ];
                        }
                    }
                    break;
                    
                case '发票申请':
                case '发票申请（重新提交）':
                    // 发票申请的附件（attachments 是数组）
                    if (!empty($businessModel->attachments) && is_array($businessModel->attachments)) {
                        foreach ($businessModel->attachments as $att) {
                            $attachments[] = [
                                'path' => $att,
                                'name' => basename($att),
                                'size' => null,
                                'type' => null
                            ];
                        }
                    }
                    break;
                    
                case '报销申请':
                    // 报销申请的附件
                    if ($businessModel->attachments) {
                        foreach ($businessModel->attachments as $att) {
                            $attachments[] = [
                                'path' => $att->file_path,
                                'name' => $att->file_name,
                                'size' => $att->file_size,
                                'type' => $att->file_type ?? $att->mime_type
                            ];
                        }
                    }
                    break;
                    
                case '保险汇总':
                    // 保险汇总的附件
                    if ($businessModel->attachments) {
                        foreach ($businessModel->attachments as $att) {
                            $attachments[] = [
                                'path' => $att->file_path,
                                'name' => $att->file_name,
                                'size' => $att->file_size,
                                'type' => $att->mime_type
                            ];
                        }
                    }
                    break;
                    
                case '工资付款申请':
                case '报销付款申请':
                case '保险汇总付款申请':
                    // 付款申请的附件
                    if ($businessModel->attachments) {
                        foreach ($businessModel->attachments as $att) {
                            $attachments[] = [
                                'path' => $att->file_path,
                                'name' => $att->filename ?? $att->file_name,
                                'size' => $att->file_size,
                                'type' => $att->mime_type
                            ];
                        }
                    }
                    break;
                    
                case '考勤表审批':
                    // 考勤表的附件（attachments 是 JSON 数组）
                    if (!empty($businessModel->attachments)) {
                        $atts = is_array($businessModel->attachments) 
                            ? $businessModel->attachments 
                            : json_decode($businessModel->attachments, true);
                        
                        if (is_array($atts)) {
                            foreach ($atts as $att) {
                                if (is_string($att)) {
                                    // 如果是字符串路径
                                    $attachments[] = [
                                        'path' => $att,
                                        'name' => basename($att),
                                        'size' => null,
                                        'type' => null
                                    ];
                                } elseif (is_array($att)) {
                                    // 如果是数组对象
                                    $attachments[] = [
                                        'path' => $att['path'] ?? $att['file_path'] ?? '',
                                        'name' => $att['name'] ?? $att['file_name'] ?? basename($att['path'] ?? ''),
                                        'size' => $att['size'] ?? $att['file_size'] ?? null,
                                        'type' => $att['type'] ?? $att['mime_type'] ?? null
                                    ];
                                }
                            }
                        }
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::warning('获取业务附件失败', [
                'business_type' => $businessType,
                'error' => $e->getMessage()
            ]);
        }
        
        return $attachments;
    }
}
