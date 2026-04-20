<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequestInvoiceAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_request_id',
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
