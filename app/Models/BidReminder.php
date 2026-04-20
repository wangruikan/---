<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidReminder extends Model
{
    protected $table = 'bid_reminders';

    protected $fillable = [
        'bid_project_id',
        'reminder_type',
        'reminder_time',
        'reminder_title',
        'reminder_content',
        'is_sent',
        'sent_at',
        'recipient_ids',
    ];

    protected $casts = [
        'reminder_time' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
        'recipient_ids' => 'array',
    ];

    /**
     * 关联：投标项目
     */
    public function bidProject()
    {
        return $this->belongsTo(BidProject::class, 'bid_project_id');
    }
}

