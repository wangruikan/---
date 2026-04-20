<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class InsuranceRecord extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '保险记录';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'insurance_type' => '保险类型',
        'base_amount' => '基数',
        'company_rate' => '单位比例',
        'personal_rate' => '个人比例',
        'company_amount' => '单位金额',
        'personal_amount' => '个人金额',
        'total_amount' => '总金额',
        'payment_date' => '缴费日期',
        'due_date' => '到期日期',
        'status' => '状态',
    ];

    protected $fillable = [
        'employee_id',
        'project_id',
        'insurance_type',
        // 'action', // 数据库中不存在
        // 'effective_date', // 数据库中不存在
        // 'completion_date', // 数据库中不存在
        'base_amount',
        'company_rate',
        'personal_rate',
        'company_amount',
        'personal_amount',
        'total_amount',
        'payment_date',
        'due_date',
        'status',
        'notes',
        // 'attachment', // 数据库中不存在
        // 'processed_by', // 数据库中不存在
        'account_set_id',  // 【账套关联】
    ];

    protected $casts = [
        // 'effective_date' => 'date', // 数据库中不存在
        // 'completion_date' => 'date', // 数据库中不存在
        'base_amount' => 'decimal:2',
        'company_rate' => 'decimal:4',
        'personal_rate' => 'decimal:4',
        'company_amount' => 'decimal:2',
        'personal_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_date' => 'date',
        'due_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // 数据库中不存在 processed_by 字段，已注释
    // public function processedBy()
    // {
    //     return $this->belongsTo(User::class, 'processed_by');
    // }

    // 以下方法因相关字段不存在已注释
    // public function isOverdue()
    // {
    //     if ($this->status === 'completed') {
    //         return false;
    //     }
    //     
    //     return now()->gt($this->effective_date);
    // }

    // public function markAsCompleted($userId)
    // {
    //     $this->update([
    //         'status' => 'completed',
    //         'completion_date' => now(),
    //         'processed_by' => $userId,
    //     ]);
    // }

    // public function markAsOverdue()
    // {
    //     if ($this->isOverdue()) {
    //         $this->update(['status' => 'overdue']);
    //     }
    // }
}
