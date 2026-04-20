<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Payment extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '付款';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'type' => '付款类型',
        'amount' => '金额',
        'description' => '描述',
        'payment_date' => '付款日期',
        'status' => '状态',
        'is_recorded' => '是否已记账',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->type} ¥{$this->amount}";
    }

    protected $fillable = [
        'project_id',
        'type', // 改为 type
        'amount',
        'description',
        'payment_date', // 添加 payment_date
        'attachments',
        'status',
        'applicant_id',
        'approver_id',
        'submitted_at',
        'approved_at',
        'paid_at',
        'rejection_reason',
        'is_recorded',
        'account_set_id',  // 【账套关联】
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date', // 添加日期转换
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'is_recorded' => 'boolean',
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

    public function canBePaid()
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

    public function pay()
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function record()
    {
        $this->update(['is_recorded' => true]);
    }
}
