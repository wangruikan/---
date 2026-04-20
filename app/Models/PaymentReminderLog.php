<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReminderLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'account_set_id',
        'payment_request_id',
        'payment_type',
        'year',
        'month',
        'due_date',
        'reminder_date',
        'reminder_time',
        'notified_user_id',
        'notification_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'due_date' => 'date',
        'reminder_date' => 'date',
    ];

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联付款申请
     */
    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    /**
     * 关联被提醒的用户
     */
    public function notifiedUser()
    {
        return $this->belongsTo(User::class, 'notified_user_id');
    }

    /**
     * 关联通知记录
     */
    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * 获取缴费类型文本
     */
    public function getPaymentTypeTextAttribute()
    {
        return $this->payment_type === 'social_security' ? '社保' : '公积金';
    }
}
