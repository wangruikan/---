<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReminderConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'days_before',
        'reminder_time',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'days_before' => 'integer',
    ];

    /**
     * 获取提醒时间描述
     */
    public function getReminderDescriptionAttribute()
    {
        $daysText = $this->days_before == 0 ? '当天' : "提前{$this->days_before}天";
        return "{$daysText} {$this->reminder_time}";
    }

    /**
     * 账套关联
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }
}
