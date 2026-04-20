<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_approval_id',
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    /**
     * 获取所属流程
     */
    public function processApproval()
    {
        return $this->belongsTo(ProcessApproval::class);
    }

    /**
     * 获取上传人
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

