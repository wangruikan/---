<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Invoice extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '发票';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'invoice_number' => '发票号码',
        'amount' => '金额',
        'tax_rate' => '税率',
        'tax_amount' => '税额',
        'total_amount' => '总金额',
        'type' => '类型',
        'issue_date' => '开票日期',
        'content' => '发票内容',
        'status' => '状态',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->invoice_number ?: "ID:{$this->id}";
    }

    protected $fillable = [
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
        'deduction_details',
        'status',
        'applicant_id',
        'approver_id',
        'submitted_at',
        'approved_at',
        'issued_at',
        'rejection_reason',
        'account_set_id',  // 【账套关联】
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'issue_date' => 'date',
        'deduction_details' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'issued_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function canBeApproved()
    {
        return $this->status === 'submitted';
    }

    public function canBeIssued()
    {
        return $this->status === 'approved';
    }

    public function approve($approverId)
    {
        $this->update([
            'status' => 'approved',
            'approver_id' => $approverId,
            'approved_at' => now(),
        ]);
    }

    public function issue()
    {
        $this->update([
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }
}
