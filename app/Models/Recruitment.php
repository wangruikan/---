<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recruitment extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '招聘';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'position' => '职位',
        'department' => '部门',
        'required_count' => '需求人数',
        'requirements' => '任职要求',
        'salary_min' => '最低薪资',
        'salary_max' => '最高薪资',
        'salary_range' => '薪资范围',
        'work_location' => '工作地点',
        'education' => '学历要求',
        'experience' => '经验要求',
        'description' => '职位描述',
        'status' => '状态',
        'progress_notes' => '进度备注',
        'hired_count' => '已招聘人数',
        'deadline' => '截止日期',
        'start_date' => '开始日期',
        'end_date' => '结束日期',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->position;
    }

    protected $fillable = [
        'project_id',
        'position',
        'required_count',
        'requirements',
        'salary_min',
        'salary_max',
        'status',
        'assigned_to',
        'progress_notes',
        'candidates',
        'hired_count',
        'deadline',
        // 添加前端表单中的其他字段
        'department',
        'salary_range',
        'work_location',
        'education',
        'experience',
        'description',
        'start_date',
        'end_date',
        'account_set_id',  // 【账套关联】
    ];

    protected $casts = [
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
        'candidates' => 'array',
        'deadline' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isOverdue()
    {
        if ($this->status === 'completed' || $this->status === 'cancelled') {
            return false;
        }
        
        return $this->deadline && now()->gt($this->deadline);
    }

    public function assignTo($userId)
    {
        $this->update([
            'status' => 'assigned',
            'assigned_to' => $userId,
        ]);
    }

    public function updateProgress($notes, $appliedCount = null, $interviewedCount = null, $hiredCount = null)
    {
        $updateData = [
            'status' => 'active', // 保持进行中状态
            'progress_notes' => $notes,
        ];
        
        // 只更新提供的字段
        if ($appliedCount !== null) {
            $updateData['applied_count'] = $appliedCount;
        }
        if ($interviewedCount !== null) {
            $updateData['interviewed_count'] = $interviewedCount;
        }
        if ($hiredCount !== null) {
            $updateData['hired_count'] = $hiredCount;
        }
        
        $this->update($updateData);
    }

    public function complete($hiredCount, $candidates = [])
    {
        $this->update([
            'status' => 'completed',
            'hired_count' => $hiredCount,
            'candidates' => $candidates,
        ]);
    }
}
