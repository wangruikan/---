<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_application_id',
        'filename',
        'path',
        'size',
        'mime_type',
        'uploaded_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联付款申请
     */
    public function paymentApplication()
    {
        return $this->belongsTo(PaymentApplication::class);
    }

    /**
     * 关联上传人
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

