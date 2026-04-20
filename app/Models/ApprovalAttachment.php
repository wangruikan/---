<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalAttachment extends Model
{
    protected $fillable = [
        'instance_id',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'uploaded_by',
    ];

    public $timestamps = true; // 允许timestamps
    const UPDATED_AT = null; // 但不使用updated_at

    /**
     * 审批实例
     */
    public function instance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    /**
     * 上传人
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
