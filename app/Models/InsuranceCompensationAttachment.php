<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceCompensationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'compensation_record_id',
        'step',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 理赔记录关联
     */
    public function compensationRecord()
    {
        return $this->belongsTo(InsuranceCompensationRecord::class, 'compensation_record_id');
    }

    /**
     * 上传人关联
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
