<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRecord extends Model
{
    protected $fillable = [
        'instance_id',
        'step_order',
        'step_name',
        'approver_id',
        'approver_name',
        'status',
        'comment',
        'signature_image',
        'seal_image',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime:Y-m-d H:i:s',
        'returned_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 审批实例
     */
    public function instance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    /**
     * 审批人
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
