<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'task_type',
        'title',
        'description',
        'related_id',
        'related_type',
        'handler_id',
        'handler_name',
        'status',
        'route_name',
        'route_params',
        'completed_at',
    ];

    protected $casts = [
        'route_params' => 'array',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    // 关联处理人
    public function handler()
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    // 关联付款申请
    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class, 'related_id')
            ->where('related_type', 'PaymentRequest');
    }

    // 关联员工
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'related_id')
            ->where('related_type', 'Employee');
    }

    /**
     * 标记任务为已完成
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * 检查任务是否已完成
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * 检查任务是否待处理
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }
}
