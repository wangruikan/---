<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentDeliveryReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'delivery_id',
        'reminder_type',
        'recipient_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    // 关联交付记录
    public function delivery()
    {
        return $this->belongsTo(DocumentDelivery::class, 'delivery_id');
    }

    // 关联接收人
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}

