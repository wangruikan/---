<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequestAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_request_id',
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'attachment_type',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
    ];

    /**
     * 所属付款申请
     */
    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    /**
     * 上传者
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

