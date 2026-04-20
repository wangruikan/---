<?php

namespace App\Models;

use App\Traits\HasApprovalResubmit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    use HasFactory, HasApprovalResubmit, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '付款申请';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'payment_type' => '付款类型',
        'category' => '类目',
        'amount' => '金额',
        'status' => '状态',
        'invoice_status' => '发票状态',
        'remarks' => '备注',
        'project' => '项目',
        'apply_date' => '申请日期',
        'unit_name' => '单位名称',
        'invoice_number' => '发票号码',
        'verified' => '是否核实',
        'payment_date' => '付款日期',
        'expenditure_amount' => '支出金额',
        'project_name' => '项目名称',
        'summary' => '摘要',
        'invoice_received' => '是否收到发票',
        'invoice_type' => '发票类型',
        'invoice_amount' => '发票金额',
        'tax_rate' => '税率',
        'deduction_amount' => '扣除额',
        'amount_excluding_tax' => '不含税金额',
        'tax_amount' => '税额',
        'is_consistent' => '是否一致',
        'status_checked' => '状态已核对',
        'selected_month' => '选择月份',
        'reimburser' => '报销人',
        'invoice_date' => '发票日期',
        'accounted' => '是否已记账',
        'company' => '公司',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->payment_type} ¥{$this->amount}";
    }

    protected $fillable = [
        'payment_type',           // 付款类型：salary/insurance/reimbursement
        'category',               // 类目：报销/差旅/采购/项目/其他
        'account_set_id',
        'insurance_summary_id',   // 保险汇总ID（当payment_type=insurance时使用）
        'salary_approval_id',     // 工资表审批ID（当payment_type=salary时使用）
        'reimbursement_id',       // 报销申请ID（当payment_type=reimbursement时使用）
        'approval_instance_id',   // 审批实例ID
        'invoice_approval_instance_id', // 发票审批流程实例ID
        'invoice_status',         // 发票状态
        'invoice_uploaded_at',    // 发票上传时间
        'invoice_uploaded_by',    // 发票上传人
        'upload_later',           // 是否稍后上传附件
        'project_ids',            // 关联项目ID列表
        'amount',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'rejection_reason',
        'remarks',
        // 报销表单字段
        'project',
        'apply_date',
        'unit_name',
        'invoice_number',
        'verified',
        'payment_date',
        'expenditure_amount',
        'project_name',
        'summary',
        'invoice_received',
        'invoice_type',
        'invoice_amount',
        'tax_rate',
        'deduction_amount',
        'amount_excluding_tax',
        'tax_amount',
        'is_consistent',
        'status_checked',
        'selected_month',
        'reimburser',
        'invoice_date',
        'accounted',
        'company',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'project_ids' => 'array',
        'upload_later' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        // 报销表单字段
        'apply_date' => 'date',
        'payment_date' => 'date',
        'invoice_date' => 'date',
        'verified' => 'boolean',
        'invoice_received' => 'boolean',
        'is_consistent' => 'boolean',
        'status_checked' => 'boolean',
        'accounted' => 'boolean',
        'expenditure_amount' => 'decimal:2',
        'invoice_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'amount_excluding_tax' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    // 关联保险汇总（保险付款）
    public function insuranceSummary()
    {
        return $this->belongsTo(ProcessApproval::class, 'insurance_summary_id');
    }

    // 关联工资表审批（工资付款）
    public function salaryApproval()
    {
        return $this->belongsTo(SalaryApproval::class);
    }

    // 关联报销申请（报销付款）
    public function reimbursement()
    {
        return $this->belongsTo(Reimbursement::class);
    }

    // 关联提交人
    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    // 关联审批人
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // 关联付款人
    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // 关联审批实例
    public function approvalInstance()
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    // 关联附件
    public function attachments()
    {
        return $this->hasMany(PaymentRequestAttachment::class);
    }

    // 关联发票附件
    public function invoiceAttachments()
    {
        return $this->hasMany(PaymentRequestInvoiceAttachment::class);
    }

    // 关联发票审批实例
    public function invoiceApprovalInstance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'invoice_approval_instance_id');
    }

    // 关联发票上传人
    public function invoiceUploader()
    {
        return $this->belongsTo(User::class, 'invoice_uploaded_by');
    }

    /**
     * 判断是否需要上传发票（第一次审批通过后，且发票审批还未提交）
     */
    public function needsInvoiceUpload()
    {
        // pending_invoice: 待上传发票
        // invoice_uploaded: 发票已上传但还未提交审批，仍可继续上传
        return $this->status === 'approved' && 
               in_array($this->invoice_status, ['pending_invoice', 'invoice_uploaded']);
    }

    /**
     * 判断当前用户是否可以上传发票
     * 社保类型：只有财务角色可以上传
     * 公积金类型：只有发起人可以上传
     */
    public function canUploadInvoice($user)
    {
        if (!$this->needsInvoiceUpload()) {
            return false;
        }

        // 获取保险汇总类型
        $insuranceType = $this->getInsuranceType();

        if ($insuranceType === 'social_security') {
            // 社保：只有财务角色可以上传(不包括管理员)
            // 检查用户角色是否包含 finance 或 财务
            $role = $user->role ?? '';
            return in_array($role, ['finance']) || 
                   str_contains($role, '财务');
        } elseif ($insuranceType === 'housing_fund') {
            // 公积金：只有发起人可以上传
            return $user->id === $this->submitted_by;
        }

        return false;
    }

    /**
     * 获取保险汇总类型（社保/公积金）
     */
    public function getInsuranceType()
    {
        if ($this->payment_type !== 'insurance' || !$this->insuranceSummary) {
            return null;
        }

        // 优先根据 category 字段判断
        $category = $this->insuranceSummary->category ?? null;
        if ($category === 'social_insurance' || $category === 'social_security') {
            return 'social_security';
        } elseif ($category === 'housing_fund') {
            return 'housing_fund';
        }

        // 其次根据 insurance_type 字段判断
        $insuranceType = $this->insuranceSummary->insurance_type ?? null;
        if ($insuranceType === 'social_security' || $insuranceType === 'social_insurance') {
            return 'social_security';
        } elseif ($insuranceType === 'housing_fund') {
            return 'housing_fund';
        }

        // 最后根据标题判断
        $title = $this->insuranceSummary->title ?? '';
        if (str_contains($title, '社保') || str_contains($title, '社会保险')) {
            return 'social_security';
        } elseif (str_contains($title, '公积金') || str_contains($title, '住房公积金')) {
            return 'housing_fund';
        }

        return null;
    }

    /**
     * 判断是否需要补传附件（勾选了稍后上传）
     */
    public function needsSupplementAttachment()
    {
        // 只要勾选了稍后上传，就需要显示补传按钮
        // 直到用户点击"确认上传"，upload_later 变为 0
        return $this->upload_later == 1;
    }

    /**
     * 判断当前用户是否可以补传附件（只有发起人可以）
     */
    public function canSupplementAttachment($user)
    {
        if (!$this->needsSupplementAttachment()) {
            return false;
        }

        // 只有发起人可以补传
        return $user->id === $this->submitted_by;
    }
}

