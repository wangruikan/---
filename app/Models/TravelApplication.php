<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TravelApplication extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '差旅申请';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'department' => '部门',
        'apply_date' => '申请日期',
        'applicant' => '申请人',
        'destination' => '目的地',
        'reason' => '出差事由',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'days' => '天数',
        'advance_amount' => '预支金额',
        'payment_date' => '付款日期',
        'remarks' => '备注',
        'status' => '状态',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->applicant} {$this->destination}";
    }

    protected $fillable = [
        'account_set_id',
        'department',
        'apply_date',
        'applicant',
        'destination',
        'reason',
        'start_time',
        'end_time',
        'days',
        'advance_amount',
        'payment_date',
        'remarks',
        'status',
        'created_by',
        'approval_flow_id',
    ];

    protected $casts = [
        'advance_amount' => 'decimal:2',
        'days' => 'integer',
        'start_time' => 'datetime:Y-m-d H:i:s',
        'end_time' => 'datetime:Y-m-d H:i:s',
        'apply_date' => 'date:Y-m-d',
        'payment_date' => 'date:Y-m-d',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联附件
     */
    public function attachments()
    {
        return $this->hasMany(TravelApplicationAttachment::class);
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
}

