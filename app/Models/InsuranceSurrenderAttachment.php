<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceSurrenderAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'surrender_request_id',
        'type', // policy_page | payment_receipt
        'file_path',
        'filename',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function surrenderRequest()
    {
        return $this->belongsTo(InsuranceSurrenderRequest::class, 'surrender_request_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

