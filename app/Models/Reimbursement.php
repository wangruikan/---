<?php

namespace App\Models;

use App\Traits\HasApprovalResubmit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reimbursement extends Model
{
    use HasFactory, SoftDeletes, HasApprovalResubmit, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '报销申请';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'company_name' => '公司名称',
        'invoice_number' => '发票号码',
        'payment_date' => '付款日期',
        'applicant' => '申请人',
        'amount' => '金额',
        'category' => '类别',
        'project' => '项目',
        'received_invoice' => '是否收到发票',
        'invoice_type' => '发票类型',
        'reason' => '报销原因',
        'invoice_amount' => '发票金额',
        'tax_rate' => '税率',
        'tax_deduction' => '税额扣除',
        'amount_excluding_tax' => '不含税金额',
        'tax_amount' => '税额',
        'invoice_date' => '发票日期',
        'record_status' => '记录状态',
        'accounting_status' => '记账状态',
        'remarks' => '备注',
        'status' => '状态',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->applicant} {$this->invoice_number}";
    }

    protected $fillable = [
        'account_set_id',
        'company_name',
        'invoice_number',
        'payment_date',
        'applicant',
        'amount',
        'category',
        'project',
        'received_invoice',
        'invoice_type',
        'reason',
        'invoice_amount',
        'tax_rate',
        'tax_deduction',
        'amount_excluding_tax',
        'tax_amount',
        'invoice_date',
        'record_status',
        'accounting_status',
        'remarks',
        'status',
        'created_by',
        'approval_flow_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联附件
     */
    public function attachments()
    {
        return $this->hasMany(ReimbursementAttachment::class);
    }

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 关联审批实例
     */
    public function approvalInstance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_flow_id');
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联付款申请
     */
    public function paymentRequest()
    {
        return $this->hasOne(PaymentRequest::class, 'reimbursement_id');
    }
}

