<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonnelChangeRequest extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '人员变动申请';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'month' => '月份',
        'change_type' => '变动类型',
        'remark' => '备注',
        'status' => '状态',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->change_type} {$this->month}";
    }

    protected $fillable = [
        'account_set_id',
        'project_id',
        'month',
        'change_type',
        'personnel_list',
        'remark',
        'status',
        'created_by',
        'approval_flow_id',
    ];

    protected $casts = [
        'personnel_list' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联附件
     */
    public function attachments()
    {
        return $this->hasMany(PersonnelChangeRequestAttachment::class);
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
     * 关联项目
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}

