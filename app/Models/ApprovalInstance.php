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
        'stamp_selection_mode',
        'stamp_company',
        'stamp_type',
        'stamp_id',
        'old_basic_salary',
        'old_salary_items',
        'new_basic_salary',
        'new_salary_items',
        'salary_adjustment_reason',
    ];

    protected $casts = [
        'completed_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'old_salary_items' => 'array',
        'new_salary_items' => 'array',
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

    public function selectedStamp()
    {
        return $this->belongsTo(\App\Models\UserBankStamp::class, 'stamp_id');
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
                $contract = \App\Models\EmployeeContract::with(['employee.projects', 'contractTemplate'])->find($this->business_id);
                if (!$contract || $contract->contractTemplate || $contract->contract_template_id) {
                    return $contract;
                }

                $projectId = collect($contract->employee?->project_ids ?? [])->filter()->first();
                if (!$projectId || !$contract->original_filename) {
                    return $contract;
                }

                $templates = \App\Models\ContractTemplate::query()
                    ->where('project_id', $projectId)
                    ->where('contract_type', $contract->contract_type)
                    ->whereHas('sharedFile', function ($query) use ($contract) {
                        $query->where('name', $contract->original_filename);
                    })
                    ->limit(2)
                    ->get();

                if ($templates->count() === 1) {
                    $contract->setRelation('contractTemplate', $templates->first());
                }

                return $contract;
            case 'offline_onboarding':
                return \App\Models\Employee::with(['projects'])->find($this->business_id);
            case '保险汇总':
            case '文件盖章':
                $process = \App\Models\ProcessApproval::with(['initiator', 'attachments'])
                    ->find($this->business_id);

                if ($process && $process->category === 'social_detail_edit') {
                    $editData = json_decode((string) $process->description, true);
                    $process->setAttribute(
                        'social_detail_edit_data',
                        is_array($editData) ? $editData : null
                    );
                }

                return $process;
            case '付款申请':
                return \App\Models\PaymentRequest::with(['submitter', 'attachments'])
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
            case 'employee_registration_form_update':
                return \App\Models\EmployeeFormUpdateRequest::with(['employee.projects', 'creator'])
                    ->find($this->business_id);
            case 'employee_salary_adjustment':
                $employee = \App\Models\Employee::with(['projects'])->find($this->business_id);
                if (!$employee) {
                    return null;
                }
                return [
                    'employee' => $employee,
                    'old_basic_salary' => $this->old_basic_salary,
                    'old_salary_items' => $this->old_salary_items,
                    'new_basic_salary' => $this->new_basic_salary,
                    'new_salary_items' => $this->new_salary_items,
                    'salary_adjustment_reason' => $this->salary_adjustment_reason,
                ];
            // 后续可以添加其他业务类型
            default:
                return null;
        }
    }
}
