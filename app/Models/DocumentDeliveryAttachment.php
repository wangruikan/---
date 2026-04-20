<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentDeliveryAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
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

    // 关联交付记录
    public function delivery()
    {
        return $this->belongsTo(DocumentDelivery::class, 'delivery_id');
    }

    // 关联上传人
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

