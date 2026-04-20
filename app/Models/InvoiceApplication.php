<?php

namespace App\Models;

use App\Traits\HasApprovalResubmit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 发票申请模型
 */
class InvoiceApplication extends Model
{
    use HasFactory, HasApprovalResubmit, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '发票申请';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'application_no' => '申请编号',
        'task_name' => '任务名称',
        'year' => '年份',
        'month' => '月份',
        'project_name' => '项目名称',
        'remark' => '备注',
        'status' => '业务状态',
        'approval_status' => '审批状态',
        'period_year' => '期间年份',
        'period_month' => '期间月份',
        'company_name' => '公司名称',
        'application_date' => '申请日期',
        'invoice_method' => '开票方式',
        'invoice_type' => '发票类型',
        'deduction_amount' => '扣除额',
        'tax_rate' => '税率',
        'amount_excluding_tax' => '不含税金额',
        'invoice_tax_amount' => '发票税额',
        'invoice_amount' => '发票金额',
        'tax_amount' => '税额',
        'invoice_date' => '开票日期',
        'is_completed' => '是否完成',
        'invoicer' => '开票人',
        'invoice_number' => '发票号码',
        'invoice_remark' => '发票备注',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->application_no ?: "ID:{$this->id}";
    }

    protected $table = 'invoice_applications';

    // 业务状态常量（只有2种）
    const STATUS_NORMAL = 'normal';            // 正常
    const STATUS_RED_FLUSHED = 'red_flushed';  // 红冲
    
    // 审批状态常量
    const APPROVAL_STATUS_PENDING = 'pending';    // 审批中
    const APPROVAL_STATUS_APPROVED = 'approved';  // 已通过
    const APPROVAL_STATUS_REJECTED = 'rejected';  // 已驳回
    
    // 兼容旧状态
    const STATUS_DRAFT = 'draft';              // 草稿（兼容）

    protected $fillable = [
        'account_set_id',
        'application_no',
        'task_name',
        'year',
        'month',
        'project_name',
        'remark',
        'status',
        'approval_status',
        'approval_instance_id',
        'submitter_id',
        'submitted_at',
        'rejection_reason',
        'attachments',
        'created_by',
        // 开票详细信息
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
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'application_date' => 'date',
        'deduction_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'amount_excluding_tax' => 'decimal:2',
        'invoice_tax_amount' => 'decimal:2',
        'invoice_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'is_completed' => 'boolean',
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'status_text',
        'approval_status_text',
        'can_resubmit',
    ];

    /**
     * 关联提交人
     */
    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    /**
     * 关联审批实例
     */
    public function approvalInstance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    /**
     * 关联发票明细
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'application_id');
    }

    /**
     * 关联发票汇总
     */
    public function invoiceSummary()
    {
        return $this->hasOne(InvoiceSummary::class, 'invoice_application_id');
    }

    /**
     * 生成申请单号
     */
    public static function generateApplicationNo()
    {
        $prefix = 'INV';
        $date = date('Ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $date . $random;
    }

    /**
     * 计算总金额
     */
    public function getTotalAmountAttribute()
    {
        return $this->items()->sum('amount');
    }

    /**
     * 获取业务状态文本
     */
    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_NORMAL => '正常',
            self::STATUS_RED_FLUSHED => '红冲',
        ];

        return $statusMap[$this->status] ?? $this->status;
    }

    /**
     * 获取审批状态文本
     */
    public function getApprovalStatusTextAttribute()
    {
        if (!$this->approval_status) {
            return '-';
        }
        
        $statusMap = [
            self::APPROVAL_STATUS_PENDING => '审批中',
            self::APPROVAL_STATUS_APPROVED => '已通过',
            self::APPROVAL_STATUS_REJECTED => '已驳回',
        ];

        return $statusMap[$this->approval_status] ?? $this->approval_status;
    }

    /**
     * 是否可以编辑（基于审批状态判断）
     */
    public function canEdit()
    {
        // 没有审批状态（未提交）或已驳回时可以编辑
        return !$this->approval_status || $this->approval_status === self::APPROVAL_STATUS_REJECTED;
    }

    /**
     * 是否可以提交审批（基于审批状态判断）
     */
    public function canSubmit()
    {
        return (!$this->approval_status || $this->approval_status === self::APPROVAL_STATUS_REJECTED) && 
               $this->items()->count() > 0 &&
               !empty($this->attachments);
    }

    /**
     * 是否可以重新提交（重新发起）
     */
    public function canResubmit()
    {
        // 业务状态是红冲 且 审批状态是已驳回
        return $this->status === self::STATUS_RED_FLUSHED && 
               $this->approval_status === self::APPROVAL_STATUS_REJECTED;
    }

    /**
     * 获取是否可以重新提交（用于API返回）
     */
    public function getCanResubmitAttribute()
    {
        return $this->canResubmit();
    }
}


