<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\PaymentRequestAttachment;
use App\Models\Project;
use App\Services\ApprovalService;
use App\Services\PendingTaskService;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentApplicationController extends Controller
{
    use ChecksPermission;

    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Legacy endpoint is retired.
     */
    public function createFromProcessApproval(Request $request, $processApprovalId)
    {
        return response()->json([
            'success' => false,
            'message' => '该入口已下线，请使用新的付款申请提交流程'
        ], 410);
    }

    /**
     * List payment applications from new chain only (payment_requests).
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('payment_applications.view')) {
            return $response;
        }

        try {
            $accountSetId = (int)($request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id'));
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $query = PaymentRequest::with([
                'submitter',
                'salaryApproval.project',
                'insuranceSummary',
                'reimbursement',
                'attachments',
                'invoiceAttachments',
                'approvalInstance.records.approver'
            ])->where('account_set_id', $accountSetId);

            if ($request->filled('payment_type')) {
                $paymentTypeFilter = $request->input('payment_type');
                if ($paymentTypeFilter === 'reimbursement') {
                    $reimbursementTypes = ['reimbursement', '报销', '差旅', '采购', '项目', '其他'];
                    $query->whereIn('payment_type', $reimbursementTypes);
                } else {
                    $query->where('payment_type', $paymentTypeFilter);
                }
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('month')) {
                $monthFilter = $request->input('month');
                $parts = explode('-', $monthFilter);
                if (count($parts) === 2) {
                    $year = (int)$parts[0];
                    $month = (int)$parts[1];
                    $reimbursementTypes = ['reimbursement', '报销', '差旅', '采购', '项目', '其他'];
                    $knownTypes = array_merge(['salary', 'insurance'], $reimbursementTypes);

                    $query->where(function ($q) use ($monthFilter, $year, $month, $reimbursementTypes, $knownTypes) {
                        $q->where(function ($salaryQuery) use ($monthFilter) {
                            $salaryQuery->where('payment_type', 'salary')
                                ->whereHas('salaryApproval', function ($salaryApprovalQuery) use ($monthFilter) {
                                    $salaryApprovalQuery->where('month', $monthFilter);
                                });
                        })
                        ->orWhere(function ($insuranceQuery) use ($monthFilter) {
                            $insuranceQuery->where('payment_type', 'insurance')
                                ->whereHas('insuranceSummary', function ($insuranceSummaryQuery) use ($monthFilter) {
                                    $insuranceSummaryQuery->where('month', $monthFilter);
                                });
                        })
                        ->orWhere(function ($reimbursementQuery) use ($reimbursementTypes, $year, $month) {
                            $reimbursementQuery->whereIn('payment_type', $reimbursementTypes)
                                ->whereHas('reimbursement', function ($reimbursementRelationQuery) use ($year, $month) {
                                    $reimbursementRelationQuery->whereYear('created_at', $year)
                                        ->whereMonth('created_at', $month);
                                });
                        })
                        ->orWhere(function ($salaryFallbackQuery) use ($monthFilter, $year, $month) {
                            $salaryFallbackQuery->where('payment_type', 'salary')
                                ->doesntHave('salaryApproval')
                                ->where(function ($inner) use ($monthFilter, $year, $month) {
                                    $inner->where('selected_month', $monthFilter)
                                        ->orWhere(function ($submittedAtQuery) use ($year, $month) {
                                            $submittedAtQuery->whereYear('submitted_at', $year)
                                                ->whereMonth('submitted_at', $month);
                                        });
                                });
                        })
                        ->orWhere(function ($insuranceFallbackQuery) use ($monthFilter, $year, $month) {
                            $insuranceFallbackQuery->where('payment_type', 'insurance')
                                ->doesntHave('insuranceSummary')
                                ->where(function ($inner) use ($monthFilter, $year, $month) {
                                    $inner->where('selected_month', $monthFilter)
                                        ->orWhere(function ($submittedAtQuery) use ($year, $month) {
                                            $submittedAtQuery->whereYear('submitted_at', $year)
                                                ->whereMonth('submitted_at', $month);
                                        });
                                });
                        })
                        ->orWhere(function ($reimbursementFallbackQuery) use ($reimbursementTypes, $monthFilter, $year, $month) {
                            $reimbursementFallbackQuery->whereIn('payment_type', $reimbursementTypes)
                                ->doesntHave('reimbursement')
                                ->where(function ($inner) use ($monthFilter, $year, $month) {
                                    $inner->where('selected_month', $monthFilter)
                                        ->orWhere(function ($submittedAtQuery) use ($year, $month) {
                                            $submittedAtQuery->whereYear('submitted_at', $year)
                                                ->whereMonth('submitted_at', $month);
                                        });
                                });
                        })
                        ->orWhere(function ($genericFallbackQuery) use ($knownTypes, $monthFilter, $year, $month) {
                            $genericFallbackQuery->where(function ($unknownTypeQuery) use ($knownTypes) {
                                $unknownTypeQuery->whereNull('payment_type')
                                    ->orWhereNotIn('payment_type', $knownTypes);
                            })->where(function ($inner) use ($monthFilter, $year, $month) {
                                $inner->where('selected_month', $monthFilter)
                                    ->orWhere(function ($submittedAtQuery) use ($year, $month) {
                                        $submittedAtQuery->whereYear('submitted_at', $year)
                                            ->whereMonth('submitted_at', $month);
                                    });
                            });
                        });
                    });
                }
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            $requests = $requests->map(function ($req) {
                $title = '';
                $month = '';
                $typeName = '';
                $insuranceCategory = null;

                if ($req->payment_type === 'salary') {
                    if ($req->salaryApproval) {
                        $projectName = $req->salaryApproval->project ? $req->salaryApproval->project->name : '未知项目';
                        $title = "工资付款申请 - {$projectName} {$req->salaryApproval->month}";
                        $month = $req->salaryApproval->month;
                    } else {
                        $title = '工资付款申请';
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
                        $title = '保险汇总付款申请';
                        $typeName = '保险汇总付款申请';
                    }
                } elseif ($req->reimbursement) {
                    $category = $req->payment_type ?: '报销';
                    if ($category === '报销') {
                        $typeName = '报销付款';
                    } elseif (in_array($category, ['差旅', '采购', '项目', '其他'], true)) {
                        $typeName = $category . '报销付款';
                    } else {
                        $typeName = '报销付款';
                    }

                    $title = "{$typeName}申请 - {$req->reimbursement->applicant}";
                    $month = $req->reimbursement->created_at ? $req->reimbursement->created_at->format('Y-m') : '';
                } else {
                    $paymentType = $req->payment_type ?: '';
                    if (in_array($paymentType, ['报销', '差旅', '采购', '项目', '其他', 'reimbursement'], true)) {
                        $category = ($paymentType === 'reimbursement') ? '报销' : $paymentType;
                        if ($category === '报销') {
                            $typeName = '报销付款';
                        } elseif (in_array($category, ['差旅', '采购', '项目', '其他'], true)) {
                            $typeName = $category . '报销付款';
                        } else {
                            $typeName = '报销付款';
                        }
                        $title = "{$typeName}申请";
                    } elseif ($paymentType === 'salary') {
                        $typeName = '工资付款申请';
                        $title = '工资付款申请';
                    } elseif ($paymentType === 'insurance') {
                        $typeName = '保险汇总付款申请';
                        $title = '保险汇总付款申请';
                    } else {
                        $typeName = '付款申请';
                        $title = '付款申请';
                    }
                }

                $attachmentTypeCount = $req->attachments->where('attachment_type', 'attachment')->count();
                $attachmentNullCount = $req->attachments->whereNull('attachment_type')->count();
                $invoiceTypeCount = $req->attachments->where('attachment_type', 'invoice')->count();
                $invoiceAttachmentsCount = $req->invoiceAttachments->count();

                $result = [
                    'id' => $req->id,
                    'payment_type' => $req->payment_type,
                    'type_name' => $typeName,
                    'insurance_category' => $insuranceCategory,
                    'insurance_type' => $insuranceCategory,
                    'title' => $title,
                    'month' => $month,
                    'amount' => $req->amount,
                    'status' => $req->status,
                    'invoice_status' => $req->invoice_status,
                    'upload_later' => $req->upload_later,
                    'initiator' => $req->submitter,
                    'attachments' => $req->attachments,
                    'attachments_count' => $attachmentTypeCount + $attachmentNullCount,
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

                if ($req->payment_type === 'insurance' && $req->needsInvoiceUpload()) {
                    $result['can_upload_invoice'] = $req->canUploadInvoice(request()->user());
                } else {
                    $result['can_upload_invoice'] = false;
                }

                $result['can_supplement_attachment'] = $req->canSupplementAttachment(request()->user());
                $supplementDeadline = $req->getSupplementDeadlineAt();
                $result['supplement_deadline_at'] = $supplementDeadline ? $supplementDeadline->format('Y-m-d H:i:s') : null;
                $result['supplement_remaining_seconds'] = $req->getSupplementRemainingSeconds();

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

            $page = (int)$request->input('page', 1);
            $perPage = (int)$request->input('per_page', 15);
            $total = $requests->count();
            $data = $requests->forPage($page, $perPage)->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $data,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int)ceil($total / max($perPage, 1))
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
     * Get payment application detail from new chain only.
     */
    public function show(Request $request, $id)
    {
        try {
            $paymentRequest = PaymentRequest::with([
                'submitter',
                'salaryApproval.project',
                'insuranceSummary',
                'reimbursement',
                'attachments',
                'approvalInstance.records.approver'
            ])->find($id);

            if (!$paymentRequest) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            $attachments = $paymentRequest->attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'path' => $attachment->file_path,
                    'size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                    'attachment_type' => $attachment->attachment_type ?? 'attachment',
                    'uploaded_by' => $attachment->uploaded_by,
                    'created_at' => $attachment->created_at,
                    'updated_at' => $attachment->updated_at,
                ];
            });

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
                $category = $paymentRequest->payment_type ?: '报销';
                if ($category === '报销') {
                    $typeName = '报销付款';
                } elseif (in_array($category, ['差旅', '采购', '项目', '其他'], true)) {
                    $typeName = $category . '报销付款';
                } else {
                    $typeName = '报销付款';
                }
            }

            $projectIds = [];
            if ($paymentRequest->project_ids) {
                $projectIds = is_array($paymentRequest->project_ids)
                    ? $paymentRequest->project_ids
                    : json_decode($paymentRequest->project_ids, true) ?? [];
            }
            if (empty($projectIds) && $paymentRequest->salaryApproval && $paymentRequest->salaryApproval->project) {
                $projectIds[] = $paymentRequest->salaryApproval->project->id;
            }
            if (empty($projectIds) && $paymentRequest->insuranceSummary && !empty($paymentRequest->insuranceSummary->project_ids)) {
                $summaryProjectIds = $paymentRequest->insuranceSummary->project_ids;
                if (!is_array($summaryProjectIds)) {
                    $decodedProjectIds = json_decode($summaryProjectIds, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedProjectIds)) {
                        $summaryProjectIds = $decodedProjectIds;
                    } else {
                        $summaryProjectIds = array_filter(array_map('trim', explode(',', trim((string) $summaryProjectIds, '[]'))));
                    }
                }
                $projectIds = is_array($summaryProjectIds) ? $summaryProjectIds : [];
            }
            if (empty($projectIds) && $paymentRequest->reimbursement) {
                if (!empty($paymentRequest->reimbursement->project_id)) {
                    // 兼容历史数据中存在 project_id 的情况
                    $projectIds[] = (int) $paymentRequest->reimbursement->project_id;
                } elseif (!empty($paymentRequest->reimbursement->project)) {
                    // 现有报销结构仅保存项目名称，按账套+名称回查项目ID
                    $projectId = \App\Models\Project::query()
                        ->where('account_set_id', $paymentRequest->account_set_id)
                        ->where('name', $paymentRequest->reimbursement->project)
                        ->value('id');
                    if ($projectId) {
                        $projectIds[] = (int) $projectId;
                    }
                }
            }

            $projectIds = collect($projectIds)
                ->filter(fn($id) => is_numeric($id) && (int)$id > 0)
                ->map(fn($id) => (int)$id)
                ->unique()
                ->values()
                ->all();

            $resolvedProject = $paymentRequest->project;
            $resolvedProjectName = $paymentRequest->project_name;

            if ((empty($resolvedProject) || empty($resolvedProjectName)) && !empty($projectIds)) {
                $projectNames = Project::query()
                    ->whereIn('id', $projectIds)
                    ->pluck('name')
                    ->filter()
                    ->values();

                if ($projectNames->isNotEmpty()) {
                    $fallbackProjectName = $projectNames->implode('、');
                    $resolvedProject = $resolvedProject ?: $fallbackProjectName;
                    $resolvedProjectName = $resolvedProjectName ?: $fallbackProjectName;
                }
            }

            $data = [
                'id' => $paymentRequest->id,
                'payment_type' => $paymentRequest->payment_type,
                'type_name' => $typeName,
                'insurance_category' => $insuranceCategory,
                'title' => $this->generateTitle($paymentRequest),
                'month' => $this->getMonth($paymentRequest),
                'amount' => $paymentRequest->amount,
                'status' => $paymentRequest->status,
                'initiator' => $paymentRequest->submitter,
                'attachments' => $attachments,
                'approval_instance' => $paymentRequest->approvalInstance,
                'salary_approval_id' => $paymentRequest->salary_approval_id,
                'insurance_summary_id' => $paymentRequest->insurance_summary_id,
                'reimbursement_id' => $paymentRequest->reimbursement_id,
                'created_at' => $paymentRequest->created_at,
                'project_ids' => $projectIds,
                'description' => $paymentRequest->remarks,
            ];

            if ($resolvedProject || $resolvedProjectName || $paymentRequest->apply_date || $paymentRequest->unit_name) {
                $data['reimbursement_form'] = [
                    'project' => $resolvedProject,
                    'apply_date' => $paymentRequest->apply_date,
                    'unit_name' => $paymentRequest->unit_name,
                    'invoice_number' => $paymentRequest->invoice_number,
                    'verified' => $paymentRequest->verified,
                    'payment_date' => $paymentRequest->payment_date,
                    'expenditure_amount' => $paymentRequest->expenditure_amount,
                    'project_name' => $resolvedProjectName,
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

    private function generateTitle($paymentRequest)
    {
        if ($paymentRequest->payment_type === 'salary' && $paymentRequest->salaryApproval) {
            $project = $paymentRequest->salaryApproval->project;
            $month = date('Y年m月', strtotime($paymentRequest->salaryApproval->month));
            return ($project ? $project->name : '未知项目') . ' - ' . $month . ' 工资付款申请';
        }

        if ($paymentRequest->payment_type === 'insurance' && $paymentRequest->insuranceSummary) {
            $month = date('Y年m月', strtotime($paymentRequest->insuranceSummary->month));
            $insuranceCategory = $paymentRequest->insuranceSummary->category ?? 'social_insurance';
            $categoryName = $insuranceCategory === 'housing_fund' ? '公积金' : '社保';
            return $month . ' ' . $categoryName . '汇总付款申请';
        }

        if ($paymentRequest->reimbursement) {
            $category = $paymentRequest->payment_type ?: '报销';
            $typeName = ($category === '报销') ? '报销付款' : ($category . '报销付款');
            return $typeName . '申请 - ' . $paymentRequest->reimbursement->applicant;
        }

        return '付款申请';
    }

    private function getMonth($paymentRequest)
    {
        if ($paymentRequest->payment_type === 'salary' && $paymentRequest->salaryApproval) {
            return $paymentRequest->salaryApproval->month;
        }

        if ($paymentRequest->payment_type === 'insurance' && $paymentRequest->insuranceSummary) {
            return $paymentRequest->insuranceSummary->month;
        }

        if ($paymentRequest->reimbursement) {
            return $paymentRequest->reimbursement->created_at
                ? $paymentRequest->reimbursement->created_at->format('Y-m')
                : null;
        }

        return null;
    }

    /**
     * Upload regular attachment for payment_request.
     */
    public function uploadAttachment(Request $request, $id)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240',
            ]);

            $paymentRequest = PaymentRequest::find($id);
            if (!$paymentRequest) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $directory = public_path('payment_requests/' . $id);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            $path = 'payment_requests/' . $id . '/' . $filename;

            $attachment = PaymentRequestAttachment::create([
                'payment_request_id' => $id,
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'attachment_type' => 'attachment',
                'uploaded_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '附件上传成功',
                'data' => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'path' => $attachment->file_path,
                    'size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                    'attachment_type' => $attachment->attachment_type,
                    'uploaded_by' => $attachment->uploaded_by,
                    'created_at' => $attachment->created_at,
                    'updated_at' => $attachment->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('附件上传失败', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '附件上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete regular attachment for payment_request.
     */
    public function deleteAttachment(Request $request, $id, $attachmentId)
    {
        try {
            $attachment = PaymentRequestAttachment::where('payment_request_id', $id)
                ->where('id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => '附件不存在'
                ], 404);
            }

            $filePath = public_path($attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '附件删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('附件删除失败', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '附件删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit draft payment_request (fallback entry from payment-applications page).
     */
    public function submit(Request $request, $id)
    {
        if ($response = $this->checkPermission('payment_applications.create')) {
            return $response;
        }

        try {
            $user = $request->user();
            $accountSetId = (int)($request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id'));
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $application = PaymentRequest::with(['attachments'])->find($id);
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            if ($application->approval_instance_id) {
                return response()->json([
                    'success' => false,
                    'message' => '该申请已经创建了审批流程'
                ], 422);
            }

            if (!in_array($application->status, ['draft', 'pending'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => '当前状态不允许提交'
                ], 400);
            }

            $attachments = $application->attachments->map(function ($attachment) {
                return [
                    'path' => $attachment->file_path,
                    'name' => $attachment->filename,
                    'size' => $attachment->file_size,
                    'type' => $attachment->mime_type,
                ];
            })->toArray();

            if (empty($attachments)) {
                return response()->json([
                    'success' => false,
                    'message' => '请至少上传一个附件后再提交'
                ], 400);
            }

            DB::beginTransaction();

            $stampMethod = $request->input('stamp_method', 'online');
            $businessType = $this->resolveBusinessType($application);

            $approvalInstance = $this->approvalService->createApprovalInstance(
                $accountSetId,
                $businessType,
                $application->id,
                $application->submitted_by ?: $user->id,
                $attachments,
                true,
                $stampMethod
            );

            if (!$approvalInstance) {
                throw new \Exception('创建审批流程失败');
            }

            $application->update([
                'approval_instance_id' => $approvalInstance->id,
                'status' => 'pending',
                'submitted_by' => $application->submitted_by ?: $user->id,
                'submitted_at' => now(),
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
     * Resubmit rejected payment_request.
     */
    public function resubmit(Request $request, $id)
    {
        try {
            $user = $request->user();
            $accountSetId = (int)($request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id'));
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $application = PaymentRequest::with(['approvalInstance', 'attachments'])->find($id);
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            if (!$application->approvalInstance || $application->approvalInstance->status !== 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => '只有被驳回的申请才能重新申请'
                ], 400);
            }

            $validated = $request->validate([
                'title' => 'nullable|string',
                'month' => 'nullable|string',
                'project_ids' => 'nullable|array',
                'description' => 'nullable|string',
                'stamp_method' => 'required|in:online,offline',
                'reimbursement_form' => 'nullable|array',
            ]);

            DB::beginTransaction();

            $updateData = [
                'project_ids' => $validated['project_ids'] ?? $application->project_ids,
                'remarks' => $validated['description'] ?? $application->remarks,
                'status' => 'pending',
                'rejection_reason' => null,
                'submitted_by' => $application->submitted_by ?: $user->id,
                'submitted_at' => now(),
            ];

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

            $attachments = $application->attachments->map(function ($attachment) {
                return [
                    'path' => $attachment->file_path,
                    'name' => $attachment->filename,
                    'size' => $attachment->file_size,
                    'type' => $attachment->mime_type,
                ];
            })->toArray();

            if (empty($attachments)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => '请至少上传一个附件后再重新申请'
                ], 400);
            }

            $businessType = $this->resolveBusinessType($application);
            $approvalInstance = $this->approvalService->createApprovalInstance(
                $accountSetId,
                $businessType,
                $application->id,
                $application->submitted_by ?: $user->id,
                $attachments,
                true,
                $validated['stamp_method']
            );

            if (!$approvalInstance) {
                throw new \Exception('创建审批流程失败');
            }

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
     * Confirm supplement attachment completed.
     */
    public function supplementAttachment(Request $request, $id)
    {
        try {
            $user = $request->user();

            $paymentRequest = PaymentRequest::find($id);
            if (!$paymentRequest) {
                return response()->json([
                    'success' => false,
                    'message' => '付款申请不存在'
                ], 404);
            }

            if (!$paymentRequest->canSupplementAttachment($user)) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限补传附件或该申请不需要补传附件'
                ], 403);
            }

            $attachmentCount = PaymentRequestAttachment::where('payment_request_id', $id)
                ->where('attachment_type', 'supplement')
                ->count();
            if ($attachmentCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '请先上传至少一个附件'
                ], 400);
            }

            $paymentRequest->update(['upload_later' => 0]);
            PendingTaskService::checkAndCompletePaymentSupplementTask($paymentRequest);

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

    private function resolveBusinessType(PaymentRequest $application): string
    {
        if ($application->payment_type === 'salary') {
            return '工资付款申请';
        }

        if ($application->payment_type === 'insurance') {
            return '保险汇总付款申请';
        }

        return '报销付款申请';
    }
}
