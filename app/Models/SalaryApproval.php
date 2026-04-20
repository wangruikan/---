<?php

namespace App\Models;

use App\Traits\HasApprovalResubmit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryApproval extends Model
{
    use HasFactory, HasApprovalResubmit;

    protected $fillable = [
        'account_set_id',
        'approval_instance_id',
        'project_id',
        'month',
        'approval_type',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'remarks',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    // 关联项目
    public function project()
    {
        return $this->belongsTo(Project::class);
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

    // 关联工资记录
    public function salaries()
    {
        return $this->hasMany(Salary::class, 'salary_approval_id');
    }

    // 关联付款申请
    public function paymentRequest()
    {
        return $this->hasOne(PaymentRequest::class, 'salary_approval_id');
    }

    // 关联附件
    public function attachments()
    {
        return $this->hasMany(SalaryApprovalAttachment::class, 'salary_approval_id');
    }

    /**
     * 审批流程实例
     */
    public function approvalInstance()
    {
        return $this->belongsTo(\App\Models\ApprovalInstance::class, 'approval_instance_id');
    }
}

