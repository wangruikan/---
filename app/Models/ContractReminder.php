<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractReminder extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '合同提醒';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'reminder_type' => '提醒类型',
        'employee_name' => '员工姓名',
        'contract_start_date' => '合同开始日期',
        'contract_end_date' => '合同结束日期',
        'termination_date' => '离职日期',
        'retirement_date' => '退休日期',
        'contract_upload_deadline' => '合同上传截止日期',
        'offline_onboarding_date' => '线下入职日期',
        'status' => '状态',
        'description' => '描述',
        'handler_name' => '处理人',
        'reminder_date' => '提醒日期',
        'escalation_date' => '升级日期',
        'is_escalated' => '是否已升级',
        'remark' => '备注',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->employee_name} {$this->reminder_type}";
    }

    protected $fillable = [
        'account_set_id',
        'employee_id',
        'reminder_type',
        'employee_name',
        'contract_start_date',
        'contract_end_date',
        'termination_date',
        'retirement_date',
        'contract_upload_deadline',
        'offline_onboarding_date',
        'status',
        'description',
        'handler_id',
        'handler_name',
        'reminder_date',
        'escalation_date',
        'is_escalated',
        'assessment_record_id',
        'remark'
    ];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'termination_date' => 'date',
        'retirement_date' => 'date',
        'contract_upload_deadline' => 'date',
        'offline_onboarding_date' => 'date',
        'reminder_date' => 'date',
        'escalation_date' => 'date',
        'is_escalated' => 'boolean',
    ];

    /**
     * 关联员工
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联考核记录
     */
    public function assessmentRecord()
    {
        return $this->belongsTo(AssessmentRecord::class);
    }

    /**
     * 获取提醒类型文本
     */
    public function getReminderTypeTextAttribute()
    {
        $types = [
            'labor_contract' => '劳动合同签订',
            'termination_agreement' => '解除协议合同',
            'retirement_agreement' => '退休解除协议合同',
            'probation_period' => '试用期到期提醒',
            'offline_onboarding_contract' => '线下入职合同上传',
        ];
        return $types[$this->reminder_type] ?? $this->reminder_type;
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statuses = [
            'pending' => '待处理',
            'resolved' => '已解决',
            'escalated' => '已升级',
        ];
        return $statuses[$this->status] ?? $this->status;
    }
}
