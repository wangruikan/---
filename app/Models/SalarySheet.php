<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalarySheet extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '工资表';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'month' => '月份',
        'total_employees' => '总人数',
        'total_amount' => '总金额',
        'status' => '状态',
        'notes' => '备注',
        'submitted_at' => '提交时间',
        'approved_at' => '审批时间',
        'rejected_at' => '驳回时间',
        'rejection_reason' => '驳回原因',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        $project = $this->project;
        return $project ? "{$project->name} {$this->month}" : "ID:{$this->id}";
    }

    protected $fillable = [
        'account_set_id',
        'project_id',
        'month',
        'attendance_sheet_id',
        'total_employees',
        'total_amount',
        'status',
        'notes',
        'created_by',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    /**
     * 关联项目
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 关联考勤表
     */
    public function attendanceSheet()
    {
        return $this->belongsTo(AttendanceSheet::class);
    }

    /**
     * 关联员工工资记录
     */
    public function employeeSalaries()
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statusMap = [
            'draft' => '草稿',
            'submitted' => '已提交',
            'approved' => '已审批',
            'rejected' => '已拒绝',
        ];

        return $statusMap[$this->status] ?? '未知';
    }

    /**
     * 获取状态类型
     */
    public function getStatusTypeAttribute()
    {
        $typeMap = [
            'draft' => 'info',
            'submitted' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
        ];

        return $typeMap[$this->status] ?? 'info';
    }
}
