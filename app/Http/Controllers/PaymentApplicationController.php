<?php

namespace App\Http\Controllers;

use App\Models\PaymentApplication;
use App\Models\PaymentAttachment;
use App\Models\ProcessApproval;
use App\Models\ApprovalAttachment;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\ChecksPermission;

class PaymentApplicationController extends Controller
{
    use ChecksPermission;
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * 从汇总申请创建付款申请
     */
    public function createFromProcessApproval(Request $request, $processApprovalId)
    {
        try {
            $user = $request->user();
            $accountSetId = (int)$request->input('current_account_set_id');

            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 获取汇总申请
            $processApproval = ProcessApproval::with(['attachments'])->find($processApprovalId);
            
            if (!$processApproval) {
                return response()->json([
                    'success' => false,
                    'message' => '汇总申请不存在'
                ], 404);
            }

            // 检查汇总申请是否已通过
            if ($processApproval->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => '只能为已通过的汇总申请发起付款申请'
                ], 400);
            }

            // 检查是否已创建过付款申请
            $existingPayment = PaymentApplication::where('process_approval_id', $processApprovalId)->first();
            if ($existingPayment) {
                return response()->json([
                    'success' => false,
                    'message' => '该汇总申请已创建过付款申请',
                    'payment_application_id' => $existingPayment->id
                ], 400);
            }

            DB::beginTransaction();

            // 创建付款申请（继承汇总申请的数据）
            $paymentApplication = PaymentApplication::create([
                'account_set_id' => $accountSetId,
                'process_approval_id' => $processApproval->id,
                'title' => $processApproval->title . ' - 付款申请',
                'month' => $processApproval->month,
                'project_ids' => $processApproval->project_ids,
                'description' => $processApproval->description,
                'initiator_id' => $user->id,
                'status' => 'draft',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '付款申请创建成功',
                'data' => $paymentApplication->load(['initiator', 'processApproval'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('创建付款申请失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '付款申请创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取付款申请列表（同时查询新旧两套系统的数据）
     */
    public function index(Request $request)
    {
        // 付款申请查看权限
        if ($response = $this->checkPermission('payment_applications.view')) {
            return $response;
        }

        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 1. 查询旧的 PaymentApplication（保险汇总付款申请）
            // 如果筛选了 payment_type 且不是 insurance，跳过旧系统查询
            $oldApplications = collect([]);
            $paymentType = $request->input('payment_type');
            
            if (!$paymentType || $paymentType === 'insurance') {
                $oldQuery = PaymentApplication::with([
                    'initiator', 
                    'processApproval', 
                    'attachments'
                ])->where('account_set_id', $accountSetId);

                // 筛选条件
                if ($request->filled('status')) {
                    $oldQuery->where('status', $request->input('status'));
                }
                if ($request->filled('month')) {
                    $oldQuery->where('month', $request->input('month'));
                }

                $oldApplications = $oldQuery->orderBy('created_at', 'desc')->get();
            }
            
            // 转换为统一格式
            $oldApplications = $oldApplications->map(function($app) {
                return [
                    'id' => $app->id,
                    'payment_type' => 'insurance',
                    'type_name' => '保险汇总付款申请',
                    'title' => $app->title,
                    'month' => $app->month,
                    'amount' => 0, // 旧系统没有金额字段
                    'status' => $app->status,
                    'initiator' => $app->initiator,
                    'attachments' => $app->attachments,
                    'approval_instance' => $app->approvalInstance ?? null,
                    'process_approval_id' => $app->process_approval_id,
                    'created_at' => $app->created_at,
                    'submitted_at' => $app->created_at,
                ];
            });

            // 2. 查询新的 PaymentRequest（工资、保险和报销付款申请）
            $newQuery = \App\Models\PaymentRequest::with([
                'submitter',
                'salaryApproval.project',
                'insuranceSummary',
                'reimbursement',
                'attachments',
                'invoiceAttachments',
                'approvalInstance.records.approver'
            ])->where('account_set_id', $accountSetId);

            // 筛选条件
            if ($request->filled('payment_type')) {
                $paymentTypeFilter = $request->input('payment_type');
                // 如果筛选的是 'reimbursement'，需要查询所有报销相关的类型
                if ($paymentTypeFilter === 'reimbursement') {
                    $reimbursementTypes = ['reimbursement', '报销', '差旅', '采购', '项目', '其他'];
                    $newQuery->whereIn('payment_type', $reimbursementTypes);
                } else {
                    $newQuery->where('payment_type', $paymentTypeFilter);
                }
            }
            if ($request->filled('status')) {
                $newQuery->where('status', $request->input('status'));
            }

            $newRequests = $newQuery->orderBy('created_at', 'desc')->get();
            
            // 转换为统一格式
            $newRequests = $newRequests->map(function($req) {
                $title = '';
                $month = '';
                $typeName = '';
                $insuranceCategory = null;
                
                // 根据 payment_type 和关联数据判断类型
                if ($req->payment_type === 'salary') {
                    if ($req->salaryApproval) {
                    $projectName = $req->salaryApproval->project ? $req->salaryApproval->project->name : '未知项目';
                    $title = "工资付款申请 - {$projectName} {$req->salaryApproval->month}";
                    $month = $req->salaryApproval->month;
                    } else {
                        $title = "工资付款申请";
                    }
                    $typeName = '工资付款申请';
                } elseif ($req->payment_type === 'insurance') {
                    if ($req->insuranceSummary) {
                        $insuranceCategory = $req->insuranceSummary->category ?? 'social_insurance';
                        $categoryName = $insuranceCategory === 'housing_fund' ? '公积金' : '社保';
                        $title = "{$categoryName}汇总付款申请 - {$req->insuranceSummary->title}";
                        $month = $req->insuranceSummary->month ?? '';
                        $typeName = $categoryName . '汇总付款申请';
                    } else {
                        $title = "保险汇总付款申请";
                        $typeName = '保险汇总付款申请';
                    }
                } elseif ($req->reimbursement) {
                    // 处理报销付款申请，payment_type 可能是报销的类目（报销、差旅、采购、项目、其他等）
                    $category = $req->payment_type ?: '报销';
                    // 根据类目生成付款类型名称
                    if ($category === '报销') {
                        $typeName = '报销付款';
                    } elseif (in_array($category, ['差旅', '采购', '项目', '其他'])) {
                        $typeName = $category . '报销付款';
                    } else {
                        // 如果是 'reimbursement' 或其他值，默认显示为"报销付款"
                        $typeName = '报销付款';
                    }
                    
                    $title = "{$typeName}申请 - {$req->reimbursement->applicant}";
                    $month = $req->reimbursement->created_at ? $req->reimbursement->created_at->format('Y-m') : '';
                } else {
                    // 如果都不匹配，根据 payment_type 推断类型
                    $paymentType = $req->payment_type ?: '';
                    if (in_array($paymentType, ['报销', '差旅', '采购', '项目', '其他', 'reimbursement'])) {
                        // 可能是报销类型但关联数据缺失
                        $category = ($paymentType === 'reimbursement') ? '报销' : $paymentType;
                        if ($category === '报销') {
                            $typeName = '报销付款';
                        } elseif (in_array($category, ['差旅', '采购', '项目', '其他'])) {
                            $typeName = $category . '报销付款';
                        } else {
                            $typeName = '报销付款';
                        }
                        $title = "{$typeName}申请";
                    } elseif ($paymentType === 'salary') {
                        $typeName = '工资付款申请';
                        $title = "工资付款申请";
                    } elseif ($paymentType === 'insurance') {
                        $typeName = '保险汇总付款申请';
                        $title = "保险汇总付款申请";
                    } else {
                        // 未知类型，使用默认值
                        $typeName = '付款申请';
                        $title = "付款申请";
                    }
                }
                
                // 调试日志：统计附件数量
                $attachmentTypeCount = $req->attachments->where('attachment_type', 'attachment')->count();
                $attachmentNullCount = $req->attachments->whereNull('attachment_type')->count();
                $invoiceTypeCount = $req->attachments->where('attachment_type', 'invoice')->count();
                $invoiceAttachmentsCount = $req->invoiceAttachments->count();
                
                \Log::info('【付款申请列表】附件统计', [
                    'payment_request_id' => $req->id,
                    'payment_type' => $req->payment_type,
                    'attachments_total' => $req->attachments->count(),
                    'attachment_type_attachment' => $attachmentTypeCount,
                    'attachment_type_null' => $attachmentNullCount,
                    'attachment_type_invoice' => $invoiceTypeCount,
                    'invoice_attachments_table' => $invoiceAttachmentsCount,
                    'attachments_detail' => $req->attachments->map(function($a) {
                        return ['id' => $a->id, 'filename' => $a->filename, 'attachment_type' => $a->attachment_type];
                    })->toArray(),
                ]);
                
                $result = [
                    'id' => $req->id,
                    'payment_type' => $req->payment_type,
                    'type_name' => $typeName,
                    'insurance_category' => $insuranceCategory, // 保险汇总类型：social_insurance=社保, housing_fund=公积金
                    'insurance_type' => $insuranceCategory, // 兼容前端
                    'title' => $title,
                    'month' => $month,
                    'amount' => $req->amount,
                    'status' => $req->status,
                    'invoice_status' => $req->invoice_status, // 发票状态
                    'upload_later' => $req->upload_later, // 稍后上传标识
                    'initiator' => $req->submitter,
                    'attachments' => $req->attachments,
                    'attachments_count' => $attachmentTypeCount + $attachmentNullCount, // 普通附件数量（包括没有设置类型的旧数据）
                    // 发票数量：如果发票审批已完成，只统计attachments表中的发票；否则还要加上invoiceAttachments表中的
                    'invoice_attachments_count' => $req->invoice_status === 'invoice_approved' 
                        ? $invoiceTypeCount 
                        : ($invoiceTypeCount + $invoiceAttachmentsCount),
                    'approval_instance' => $req->approvalInstance ?? null,
                    'process_approval_id' => null,
                    'salary_approval_id' => $req->salary_approval_id,
                    'insurance_summary_id' => $req->insurance_summary_id,
                    'reimbursement_id' => $req->reimbursement_id,
                    'created_at' => $req->created_at,
                    'submitted_at' => $req->submitted_at,
                    // 付款表单字段（直接放到顶层方便列表显示）
                    'apply_date' => $req->apply_date,
                    'unit_name' => $req->unit_name,
                    'invoice_number' => $req->invoice_number,
                    'payment_date' => $req->payment_date,
                    'summary' => $req->summary,
                    'invoice_amount' => $req->invoice_amount,
                    'invoice_type' => $req->invoice_type,
                    'reimburser' => $req->reimburser,
                    'tax_amount' => $req->tax_amount,
                    'tax_rate' => $req->tax_rate,
                    'deduction_amount' => $req->deduction_amount,
                    'amount_excluding_tax' => $req->amount_excluding_tax,
                ];
                
                // 检查是否可以上传发票（仅保险付款申请）
                if ($req->payment_type === 'insurance' && $req->needsInvoiceUpload()) {
                    // 需要根据当前用户判断是否有权限上传
                    $result['can_upload_invoice'] = $req->canUploadInvoice(request()->user());
                } else {
                    $result['can_upload_invoice'] = false;
                }
                
                // 检查是否可以补传附件（勾选了稍后上传且还未上传）
                $result['can_supplement_attachment'] = $req->canSupplementAttachment(request()->user());
                
                // 如果有报销表单信息（所有付款申请类型都可能包含），添加报销表单信息
                if ($req->project || $req->apply_date || $req->unit_name) {
                    $result['reimbursement_form'] = [
                        'project' => $req->project,
                        'apply_date' => $req->apply_date,
                        'unit_name' => $req->unit_name,
                        'invoice_number' => $req->invoice_number,
                        'verified' => $req->verified,
                        'payment_date' => $req->payment_date,
                        'expenditure_amount' => $req->expenditure_amount,
                        'project_name' => $req->project_name,
                        'summary' => $req->summary,
                        'invoice_received' => $req->invoice_received,
                        'invoice_type' => $req->invoice_type,
                        'invoice_amount' => $req->invoice_amount,
                        'tax_rate' => $req->tax_rate,
                        'deduction_amount' => $req->deduction_amount,
                        'amount_excluding_tax' => $req->amount_excluding_tax,
                        'tax_amount' => $req->tax_amount,
                        'is_consistent' => $req->is_consistent,
                        'status_checked' => $req->status_checked,
                        'selected_month' => $req->selected_month,
                        'reimburser' => $req->reimburser,
                        'invoice_date' => $req->invoice_date,
                        'accounted' => $req->accounted,
                        'company' => $req->company,
                    ];
                }
                
                return $result;
            });

            // 3. 合并两套数据
            $allApplications = $oldApplications->concat($newRequests)
                                              ->sortByDesc('created_at')
                                              ->values();

            // 4. 手动分页
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 15);
            $total = $allApplications->count();
            $data = $allApplications->forPage($page, $perPage)->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $data,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取付款申请列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取付款申请详情
     */
    public function show(Request $request, $id)
    {
        try {
            // 先查询旧表 payment_applications
            $application = PaymentApplication::with([
                'initiator', 
                'processApproval', 
                'approvalInstance.records.approver',
                'attachments'
            ])->find($id);

            if ($application) {
                // 添加类型信息
                $application->payment_type = 'insurance';
                $application->type_name = '保险汇总付款申请';
                
                return response()->json([
                    'success' => true,
                    'data' => $application
                ]);
            }

            // 如果旧表没有，查询新表 payment_requests
            $paymentRequest = \App\Models\PaymentRequest::with([
                'submitter', 
                'salaryApproval.project', 
                'insuranceSummary', 
                'reimbursement',
                'attachments', 
                'approvalInstance.records.approver'
            ])->find($id);

            if ($paymentRequest) {
                // 转换附件字段名（新表用 file_path/file_size，前端期望 path/size）
                $attachments = $paymentRequest->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'filename' => $attachment->filename,
                        'path' => $attachment->file_path,        // 统一为 path
                        'size' => $attachment->file_size,        // 统一为 size
                        'mime_type' => $attachment->mime_type,
                        'attachment_type' => $attachment->attachment_type ?? 'attachment', // 附件类型：invoice=发票, attachment=普通附件
                        'uploaded_by' => $attachment->uploaded_by,
                        'created_at' => $attachment->created_at,
                        'updated_at' => $attachment->updated_at,
                    ];
                });

                // 转换为统一格式
                $typeName = '付款申请';
                $insuranceCategory = null;
                if ($paymentRequest->payment_type === 'salary') {
                    $typeName = '工资付款申请';
                } elseif ($paymentRequest->payment_type === 'insurance') {
                    if ($paymentRequest->insuranceSummary) {
                        $insuranceCategory = $paymentRequest->insuranceSummary->category ?? 'social_insurance';
                        $categoryName = $insuranceCategory === 'housing_fund' ? '公积金' : '社保';
                        $typeName = $categoryName . '汇总付款申请';
                    } else {
                        $typeName = '保险汇总付款申请';
                    }
                } elseif ($paymentRequest->reimbursement) {
                    // 处理报销付款申请，payment_type 可能是报销的类目
                    $category = $paymentRequest->payment_type ?: '报销';
                    if ($category === '报销') {
                        $typeName = '报销付款';
                    } elseif (in_array($category, ['差旅', '采购', '项目', '其他'])) {
                        $typeName = $category . '报销付款';
                    } else {
                        $typeName = '报销付款';
                    }
                }
                
                // 获取关联项目ID
                $projectIds = [];
                // 优先使用付款申请自身保存的项目ID
                if ($paymentRequest->project_ids) {
                    $projectIds = is_array($paymentRequest->project_ids) 
                        ? $paymentRequest->project_ids 
                        : json_decode($paymentRequest->project_ids, true) ?? [];
                }
                // 如果没有，尝试从关联的工资审批获取
                if (empty($projectIds) && $paymentRequest->salaryApproval && $paymentRequest->salaryApproval->project) {
                    $projectIds[] = $paymentRequest->salaryApproval->project->id;
                }
                // 如果没有，尝试从关联的报销获取
                if (empty($projectIds) && $paymentRequest->reimbursement && $paymentRequest->reimbursement->project_id) {
                    $projectIds[] = $paymentRequest->reimbursement->project_id;
                }

                $data = [
                    'id' => $paymentRequest->id,
                    'payment_type' => $paymentRequest->payment_type,
                    'type_name' => $typeName,
                    'insurance_category' => $insuranceCategory, // 保险汇总类型：social_insurance=社保, housing_fund=公积金
                    'title' => $this->generateTitle($paymentRequest),
                    'month' => $this->getMonth($paymentRequest),
                    'amount' => $paymentRequest->amount,
                    'status' => $paymentRequest->status,
                    'initiator' => $paymentRequest->submitter,
                    'attachments' => $attachments,              // 使用转换后的附件
                    'approval_instance' => $paymentRequest->approvalInstance,
                    'salary_approval_id' => $paymentRequest->salary_approval_id,
                    'insurance_summary_id' => $paymentRequest->insurance_summary_id,
                    'reimbursement_id' => $paymentRequest->reimbursement_id,
                    'created_at' => $paymentRequest->created_at,
                    'project_ids' => $projectIds,
                    'description' => $paymentRequest->remarks,
                ];
                
                // 如果有报销表单信息（所有付款申请类型都可能包含），添加报销表单信息
                if ($paymentRequest->project || $paymentRequest->apply_date || $paymentRequest->unit_name) {
                    $data['reimbursement_form'] = [
                        'project' => $paymentRequest->project,
                        'apply_date' => $paymentRequest->apply_date,
                        'unit_name' => $paymentRequest->unit_name,
                        'invoice_number' => $paymentRequest->invoice_number,
                        'verified' => $paymentRequest->verified,
                        'payment_date' => $paymentRequest->payment_date,
                        'expenditure_amount' => $paymentRequest->expenditure_amount,
                        'project_name' => $paymentRequest->project_name,
                        'summary' => $paymentRequest->summary,
                        'invoice_received' => $paymentRequest->invoice_received,
                        'invoice_type' => $paymentRequest->invoice_type,
                        'invoice_amount' => $paymentRequest->invoice_amount,
                        'tax_rate' => $paymentRequest->tax_rate,
                        'deduction_amount' => $paymentRequest->deduction_amount,
                        'amount_excluding_tax' => $paymentRequest->amount_excluding_tax,
                        'tax_amount' => $paymentRequest->tax_amount,
                        'is_consistent' => $paymentRequest->is_consistent,
                        'status_checked' => $paymentRequest->status_checked,
                        'selected_month' => $paymentRequest->selected_month,
                        'reimburser' => $paymentRequest->reimburser,
                        'invoice_date' => $paymentRequest->invoice_date,
                        'accounted' => $paymentRequest->accounted,
                        'company' => $paymentRequest->company,
                ];
                }

                return response()->json([
                    'success' => true,
                    'data' => $data
                ]);
            }

            // 两个表都没有找到
            return response()->json([
                'success' => false,
                'message' => '付款申请不存在'
            ], 404);

        } catch (\Exception $e) {
            Log::error('获取付款申请详情失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 生成付款申请标题
     */
    private function generateTitle($paymentRequest)
    {
        if ($paymentRequest->payment_type === 'salary' && $paymentRequest->salaryApproval) {
            $project = $paymentRequest->salaryApproval->project;
            $month = date('Y年m月', strtotime($paymentRequest->salaryApproval->month));
            return ($project ? $project->name : '未知项目') . ' - ' . $month . ' 工资付款申请';
        } elseif ($paymentRequest->payment_type === 'insurance' && $paymentRequest->insuranceSummary) {
            $month = date('Y年m月', strtotime($paymentRequest->insuranceSummary->month));
            $insuranceCategory = $paymentRequest->insuranceSummary->category ?? 'social_insurance';
            $categoryName = $insuranceCategory === 'housing_fund' ? '公积金' : '社保';
            return $month . ' ' . $categoryName . '汇总付款申请';
        } elseif ($paymentRequest->reimbursement) {
            // 处理报销付款申请，payment_type 可能是报销的类目
            $category = $paymentRequest->payment_type ?: '报销';
            $typeName = ($category === '报销') ? '报销付款' : ($category . '报销付款');
            return $typeName . '申请 - ' . $paymentRequest->reimbursement->applicant;
        }
        return '付款申请';
    }

    /**
     * 获取月份
     */
    private function getMonth($paymentRequest)
    {
        if ($paymentRequest->payment_type === 'salary' && $paymentRequest->salaryApproval) {
            return $paymentRequest->salaryApproval->month;
        } elseif ($paymentRequest->payment_type === 'insurance' && $paymentRequest->insuranceSummary) {
            return $paymentRequest->insuranceSummary->month;
        } elseif ($paymentRequest->reimbursement) {
            // 处理报销付款申请，payment_type 可能是报销的类目
            return $paymentRequest->reimbursement->created_at ? $paymentRequest->reimbursement->created_at->format('Y-m') : null;
        }
        return null;
    }

    /**
     * 上传附件
     */
    public function uploadAttachment(Request $request, $id)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240',
            ]);

            $application = PaymentApplication::find($id);
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // 在移动文件之前获取文件信息
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            
            // 保存文件到 public 目录
            $directory = public_path('payment_applications/' . $id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'payment_applications/' . $id . '/' . $filename;

            // 保存附件记录
            $attachment = PaymentAttachment::create([
                'payment_application_id' => $id,
                'filename' => $filename,
                'path' => $path,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => $attachment
            ]);

        } catch (\Exception $e) {
            Log::error('附件上传失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '附件上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除附件
     */
    public function deleteAttachment(Request $request, $id, $attachmentId)
    {
        try {
            $attachment = PaymentAttachment::where('payment_application_id', $id)
                ->where('id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => '附件不存在'
                ], 404);
            }

            // 删除物理文件
            $filePath = public_path($attachment->path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // 删除记录
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '附件删除成功'
            ]);

        } catch (\Exception $e) {
            Log::error('附件删除失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '附件删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交付款申请（发起审批流程）
     */
    public function submit(Request $request, $id)
    {
        // 付款申请创建权限（提交视为创建业务）
        if ($response = $this->checkPermission('payment_applications.create')) {
            return $response;
        }

        try {
            $user = $request->user();
            $accountSetId = (int)$request->input('current_account_set_id');

            $application = PaymentApplication::find($id);
            
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            if ($application->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => '该申请已提交，无法重复提交'
                ], 400);
            }

            DB::beginTransaction();

            // 获取盖章方式，默认线上
            $stampMethod = $request->input('stamp_method', 'online');

            // 创建审批实例
            $approvalInstance = $this->approvalService->createApprovalInstance(
                $accountSetId,
                '付款申请',
                $application->id,
                $user->id,
                [], // attachments - 空数组，稍后单独同步
                true, // skipInitiator = true
                $stampMethod // 盖章方式
            );

            if (!$approvalInstance) {
                throw new \Exception('创建审批流程失败');
            }

            // 同步附件到审批附件表
            foreach ($application->attachments as $attachment) {
                ApprovalAttachment::create([
                    'instance_id' => $approvalInstance->id,
                    'file_name' => $attachment->filename,
                    'file_path' => $attachment->path,
                    'file_size' => $attachment->size,
                    'mime_type' => $attachment->mime_type,
                    'uploaded_by' => $attachment->uploaded_by,
                ]);
            }

            // 更新付款申请状态
            $application->update([
                'approval_instance_id' => $approvalInstance->id,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '付款申请已提交审批',
                'data' => $application->load(['approvalInstance.records'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('付款申请提交失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '付款申请提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 重新申请（用于被驳回的付款申请）
     */
    public function resubmit(Request $request, $id)
    {
        try {
            $user = $request->user();
            $accountSetId = (int)$request->input('current_account_set_id');

            // 先尝试查找旧表 PaymentApplication
            $application = PaymentApplication::with(['approvalInstance'])->find($id);
            $isOldModel = true;
            
            // 如果旧表没有，查找新表 PaymentRequest
            if (!$application) {
                $application = \App\Models\PaymentRequest::with(['approvalInstance'])->find($id);
                $isOldModel = false;
            }
            
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            // 检查审批状态是否为已驳回
            if (!$application->approvalInstance || $application->approvalInstance->status !== 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => '只有被驳回的申请才能重新申请'
                ], 400);
            }

            // 验证请求数据
            $validated = $request->validate([
                'title' => 'nullable|string',
                'month' => 'nullable|string',
                'project_ids' => 'nullable|array',
                'description' => 'nullable|string',
                'stamp_method' => 'required|in:online,offline',
                'reimbursement_form' => 'nullable|array', // 报销表单数据
            ]);

            DB::beginTransaction();

            if ($isOldModel) {
                // 旧模型：PaymentApplication
                $updateData = [
                    'title' => $validated['title'] ?? $application->title,
                    'month' => $validated['month'] ?? $application->month,
                    'project_ids' => $validated['project_ids'] ?? $application->project_ids,
                    'description' => $validated['description'] ?? $application->description,
                    'status' => 'pending',
                ];

                if (isset($validated['reimbursement_form'])) {
                    $updateData['reimbursement_form'] = $validated['reimbursement_form'];
                }

                $application->update($updateData);
            } else {
                // 新模型：PaymentRequest
                $updateData = [
                    'project_ids' => $validated['project_ids'] ?? $application->project_ids,
                    'remarks' => $validated['description'] ?? $application->remarks,
                    'status' => 'pending',
                ];

                // 如果有报销表单数据，更新所有报销表单字段
                if (isset($validated['reimbursement_form'])) {
                    $form = $validated['reimbursement_form'];
                    $updateData = array_merge($updateData, [
                        'project' => $form['project'] ?? null,
                        'apply_date' => $form['apply_date'] ?? null,
                        'unit_name' => $form['unit_name'] ?? null,
                        'invoice_number' => $form['invoice_number'] ?? null,
                        'verified' => $form['verified'] ?? false,
                        'payment_date' => $form['payment_date'] ?? null,
                        'expenditure_amount' => $form['expenditure_amount'] ?? null,
                        'project_name' => $form['project_name'] ?? null,
                        'summary' => $form['summary'] ?? null,
                        'invoice_received' => $form['invoice_received'] ?? false,
                        'invoice_type' => $form['invoice_type'] ?? null,
                        'invoice_amount' => $form['invoice_amount'] ?? null,
                        'tax_rate' => $form['tax_rate'] ?? null,
                        'deduction_amount' => $form['deduction_amount'] ?? null,
                        'amount_excluding_tax' => $form['amount_excluding_tax'] ?? null,
                        'tax_amount' => $form['tax_amount'] ?? null,
                        'is_consistent' => $form['is_consistent'] ?? false,
                        'status_checked' => $form['status_checked'] ?? false,
                        'selected_month' => $form['selected_month'] ?? null,
                        'reimburser' => $form['reimburser'] ?? null,
                        'invoice_date' => $form['invoice_date'] ?? null,
                        'accounted' => $form['accounted'] ?? false,
                        'company' => $form['company'] ?? null,
                    ]);
                }

                $application->update($updateData);
            }

            // 创建新的审批实例
            $approvalInstance = $this->approvalService->createApprovalInstance(
                $accountSetId,
                '付款申请',
                $application->id,
                $user->id,
                [], // attachments - 空数组，稍后单独同步
                true, // skipInitiator = true
                $validated['stamp_method']
            );

            if (!$approvalInstance) {
                throw new \Exception('创建审批流程失败');
            }

            // 同步附件到新的审批附件表
            foreach ($application->attachments as $attachment) {
                ApprovalAttachment::create([
                    'instance_id' => $approvalInstance->id,
                    'file_name' => $attachment->filename,
                    'file_path' => $isOldModel ? $attachment->path : $attachment->file_path,
                    'file_size' => $isOldModel ? $attachment->size : $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                    'uploaded_by' => $attachment->uploaded_by,
                ]);
            }

            // 更新付款申请的审批实例ID
            $application->update([
                'approval_instance_id' => $approvalInstance->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '重新申请已提交审批',
                'data' => $application->load(['approvalInstance.records'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('重新申请提交失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '重新申请提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 补传附件完成（更新upload_later标识）
     */
    public function supplementAttachment(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            // 查找付款申请（只在新表 payment_requests 中查找）
            $paymentRequest = \App\Models\PaymentRequest::find($id);
            
            if (!$paymentRequest) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            // 检查是否可以补传附件
            if (!$paymentRequest->canSupplementAttachment($user)) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限补传附件或该申请不需要补传附件'
                ], 403);
            }

            // 检查是否已上传补传类型的附件
            $attachmentCount = \App\Models\PaymentRequestAttachment::where('payment_request_id', $id)
                ->where('attachment_type', 'supplement')
                ->count();
            if ($attachmentCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '请先上传至少一个附件'
                ], 400);
            }

            // 更新 upload_later 标识为 0
            $paymentRequest->update(['upload_later' => 0]);

            return response()->json([
                'success' => true,
                'message' => '附件补传完成'
            ]);

        } catch (\Exception $e) {
            Log::error('补传附件失败', [
                'payment_request_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '补传附件失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
