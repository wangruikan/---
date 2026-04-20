<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasApprovalResubmit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentApplication extends Model
{
    use HasFactory, HasApprovalResubmit, Auditable;

    protected $auditName = '付款申请';
    
    protected $auditableFields = [
        'title' => '标题',
        'month' => '月份',
        'description' => '说明',
        'status' => '状态'
    ];

    protected $fillable = [
        'account_set_id',
        'process_approval_id',
        'title',
        'month',
        'project_ids',
        'description',
        'initiator_id',
        'approval_instance_id',
        'status',
    ];

    protected $casts = [
        'project_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->title ?: "ID:{$this->id}";
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联汇总申请
     */
    public function processApproval()
    {
        return $this->belongsTo(ProcessApproval::class);
    }

    /**
     * 关联发起人
     */
    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    /**
     * 关联审批实例
     */
    public function approvalInstance()
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    /**
     * 关联附件
     */
    public function attachments()
    {
        return $this->hasMany(PaymentAttachment::class);
    }

    /**
     * 关联项目（多对多，通过JSON字段模拟）
     */
    public function projects()
    {
        if (empty($this->project_ids)) {
            return collect([]);
        }
        return Project::whereIn('id', $this->project_ids)->get();
    }
}

