<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class InsuranceCompensationRecord extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '理赔记录';

    protected $auditableFields = [
        'type' => '类型',
        'employee_name' => '员工姓名',
        'project_name' => '项目名称',
        'policy_name' => '保单名称',
        'incident_date' => '事故发生日期',
        'incident_description' => '事故描述',
        'status' => '状态',
        'current_step' => '当前步骤',
        'recognition_result' => '认定结果',
        'medical_expense_claimed' => '医药费申报',
        'disability_claimed' => '伤残认定',
    ];

    protected $fillable = [
        'account_set_id',
        'type',
        'employee_id',
        'employee_name',
        'project_id',
        'project_name',
        'policy_id',
        'policy_name',
        'incident_date',
        'incident_description',
        'status',
        'current_step',
        'registration_date',
        'recognition_result',
        'recognition_date',
        'medical_expense_claimed',
        'disability_claimed',
        'material_submitted_date',
        'claim_received_date',
        'completed_date',
        'created_by',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'registration_date' => 'datetime',
        'recognition_date' => 'datetime',
        'material_submitted_date' => 'datetime',
        'claim_received_date' => 'datetime',
        'completed_date' => 'datetime',
        'medical_expense_claimed' => 'boolean',
        'disability_claimed' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier(): string
    {
        $typeText = $this->type === 'work_injury' ? '工伤' : '商业险';
        return "{$typeText}理赔 - {$this->employee_name}";
    }

    /**
     * 账套关联
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 员工关联
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 项目关联
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 保单关联（商业险）
     */
    public function policy()
    {
        return $this->belongsTo(OtherInsurancePolicy::class, 'policy_id');
    }

    /**
     * 创建人关联
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 附件关联
     */
    public function attachments()
    {
        return $this->hasMany(InsuranceCompensationAttachment::class, 'compensation_record_id');
    }

    /**
     * 获取指定步骤的附件
     */
    public function getStepAttachments($step)
    {
        return $this->attachments()->where('step', $step)->get();
    }
}
