<?php

namespace App\Models;

use App\Traits\HasApprovalResubmit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessApproval extends Model
{
    use HasFactory, SoftDeletes, HasApprovalResubmit, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '流程审批';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'title' => '标题',
        'category' => '类别',
        'month' => '月份',
        'description' => '描述',
        'status' => '状态',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->title;
    }

    protected $fillable = [
        'account_set_id',
        'approval_instance_id',
        'initiator_id',
        'title',
        'category',  // 汇总类型：social_insurance=社保, housing_fund=公积金
        'month',
        'project_ids',
        'description',
        'status',
    ];

    protected $casts = [
        'project_ids' => 'array',
    ];

    /**
     * 获取所属账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 获取发起人
     */
    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    /**
     * 获取关联的审批实例
     */
    public function approvalInstance()
    {
        return $this->belongsTo(\App\Models\ApprovalInstance::class, 'approval_instance_id');
    }

    /**
     * 获取所有附件
     */
    public function attachments()
    {
        return $this->hasMany(ProcessAttachment::class);
    }
}

