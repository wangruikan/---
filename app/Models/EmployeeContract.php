<?php

namespace App\Models;

use App\Traits\HasApprovalResubmit;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeContract extends Model
{
    use HasFactory, HasApprovalResubmit, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '员工合同';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'contract_type' => '合同类型',
        'contract_file' => '合同文件',
        'original_filename' => '原始文件名',
        'status' => '状态',
        'uploaded_at' => '上传时间',
        'employee_signed_at' => '员工签署时间',
        'completed_at' => '完成时间',
        'notes' => '备注',
        'signature_image' => '签名图片',
        'employee_reject_reason' => '员工拒绝原因',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        $employee = $this->employee;
        return $employee ? "{$employee->name} {$this->contract_type}" : "ID:{$this->id}";
    }

    protected $fillable = [
        'employee_id',
        'account_set_id',
        'contract_type',
        'contract_file',
        'original_filename',
        'status',
        'approval_instance_id',
        'created_by',
        'uploaded_at',
        'employee_signed_at',
        'completed_at',
        'notes',
        'signature_image',
        'sign_x_percent',
        'sign_y_percent',
        'sign_page_index',
        'sign_ip',
        'sign_device',
        'employee_reject_reason',
        'signature_positions',  // 预设的多个签名位置（JSON格式）
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'employee_signed_at' => 'datetime',
        'completed_at' => 'datetime',
        'signature_positions' => 'array',  // 自动转换为数组
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
        return $this->belongsTo(\App\Models\ApprovalInstance::class, 'approval_instance_id');
    }

    /**
     * 获取合同类型文本
     */
    public function getContractTypeTextAttribute()
    {
        $types = [
            'labor' => '劳动合同',
            'termination' => '解除协议合同',
            'retirement' => '退休解除协议合同',
        ];
        return $types[$this->contract_type] ?? $this->contract_type;
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statuses = [
            'draft' => '草稿',
            'pending_sign' => '签署中',
            'employee_signed' => '乙方已签署',
            'in_approval' => '审批中',
            'completed' => '已完成',
            'rejected' => '已驳回',
        ];
        return $statuses[$this->status] ?? $this->status;
    }
}

