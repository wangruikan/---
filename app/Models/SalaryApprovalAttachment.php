<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryApprovalAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_approval_id',
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取所属工资表审批
     */
    public function salaryApproval()
    {
        return $this->belongsTo(SalaryApproval::class);
    }

    /**
     * 获取上传人
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

