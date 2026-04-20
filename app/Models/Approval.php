<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'related_id',
        'applicant_id',
        'approver_id',
        'status',
        'reason',
        'approval_comment',
        'expected_completion_date',
        'actual_completion_date',
        'signature',
        'seal',
        'stamp_method',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'account_set_id',  // 【账套关联】
    ];

    protected $casts = [
        'expected_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * 获取盖章方式的中文显示
     */
    public function getStampMethodTextAttribute()
    {
        return $this->stamp_method === 'online' ? '线上盖章' : '线下盖章';
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isOverdue()
    {
        if ($this->status !== 'pending') {
            return false;
        }
        
        return $this->expected_completion_date && now()->gt($this->expected_completion_date);
    }

    public function approve($approverId, $comment = null, $signature = null, $seal = null)
    {
        $this->update([
            'status' => 'approved',
            'approver_id' => $approverId,
            'approval_comment' => $comment,
            'signature' => $signature,
            'seal' => $seal,
            'approved_at' => now(),
            'actual_completion_date' => now(),
        ]);
    }

    public function reject($approverId, $comment)
    {
        $this->update([
            'status' => 'rejected',
            'approver_id' => $approverId,
            'approval_comment' => $comment,
            'rejected_at' => now(),
        ]);
    }

    public function returnToApplicant($approverId, $comment)
    {
        $this->update([
            'status' => 'returned',
            'approver_id' => $approverId,
            'approval_comment' => $comment,
        ]);
    }
}
