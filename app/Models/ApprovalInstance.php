<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalInstance extends Model
{
    protected $fillable = [
        'account_set_id',
        'business_type',
        'business_id',
        'current_step',
        'total_steps',
        'status',
        'created_by',
        'completed_at',
        'stamp_method',
    ];

    protected $casts = [
        'completed_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 所属账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    /**
     * 创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 审批记录
     */
    public function records()
    {
        return $this->hasMany(ApprovalRecord::class, 'instance_id')->orderBy('step_order');
    }

    /**
     * 抄送人
     */
    public function ccUsers()
    {
        return $this->hasMany(ApprovalCCUser::class, 'instance_id');
    }

    /**
     * 附件
     */
    public function attachments()
    {
        return $this->hasMany(\App\Models\ApprovalAttachment::class, 'instance_id');
    }

    /**
     * 获取当前待审批记录
     */
    public function getCurrentPendingRecord()
    {
        return $this->records()
            ->where('status', 'pending')
            ->where('step_order', $this->current_step)
            ->first();
    }

    /**
     * 获取业务关联数据
     */
    public function getBusinessData()
    {
        switch ($this->business_type) {
            case 'employee_contract':
                return \App\Models\EmployeeContract::find($this->business_id);
            case 'offline_onboarding':
                return \App\Models\Employee::with(['projects'])->find($this->business_id);
            case '保险汇总':
                return \App\Models\ProcessApproval::with(['initiator', 'attachments'])
                    ->find($this->business_id);
            case '付款申请':
                return \App\Models\PaymentApplication::with(['initiator', 'attachments', 'processApproval'])
                    ->find($this->business_id);
            case '考勤申请':
                return \App\Models\AttendanceSheet::with(['project', 'creator'])
                    ->find($this->business_id);
            case '工资表审批':
                return \App\Models\SalaryApproval::with(['project', 'submitter', 'attachments'])
                    ->find($this->business_id);
            case '工资付款申请':
                return \App\Models\PaymentRequest::with(['salaryApproval.project', 'submitter', 'attachments'])
                    ->find($this->business_id);
            case '保险汇总付款申请':
                return \App\Models\PaymentRequest::with(['insuranceSummary', 'submitter', 'attachments'])
                    ->find($this->business_id);
            case '报销付款申请':
                return \App\Models\PaymentRequest::with(['reimbursement', 'submitter', 'attachments'])
                    ->find($this->business_id);
            case '报销申请':
                return \App\Models\Reimbursement::with(['creator', 'attachments'])
                    ->find($this->business_id);
            case 'material_request':
                return \App\Models\MaterialRequest::with(['applicant', 'items.material'])
                    ->find($this->business_id);
            case 'employee_deletion':
                return \App\Models\Employee::with(['projects'])->find($this->business_id);
            // 后续可以添加其他业务类型
            default:
                return null;
        }
    }
}
