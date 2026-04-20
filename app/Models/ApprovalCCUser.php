<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalCCUser extends Model
{
    protected $table = 'approval_cc_users';
    
    public $timestamps = true; // 允许timestamps
    const UPDATED_AT = null; // 但不使用updated_at
    
    protected $fillable = [
        'instance_id',
        'user_id',
        'user_name',
        'added_by',
        'added_at_step',
        'has_read',
        'read_at',
    ];

    protected $casts = [
        'has_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * 审批实例
     */
    public function instance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    /**
     * 抄送用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
