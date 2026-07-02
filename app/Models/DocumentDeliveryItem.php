<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentDeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'document_name',
        'sort_order',
        'status',
        'submitted_documents',
        'express_number',
        'express_date',
        'submitted_by',
        'submitted_at',
        'completed_by',
        'completed_at',
        'remarks',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'express_date' => 'date',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function delivery()
    {
        return $this->belongsTo(DocumentDelivery::class, 'delivery_id');
    }

    public function attachments()
    {
        return $this->hasMany(DocumentDeliveryAttachment::class, 'delivery_item_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
