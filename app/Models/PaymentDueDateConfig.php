<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDueDateConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'payment_type',
        'month',
        'due_day',
    ];

    protected $casts = [
        'month' => 'integer',
        'due_day' => 'integer',
    ];

    /**
     * 获取缴费类型文本
     */
    public function getPaymentTypeTextAttribute()
    {
        return $this->payment_type === 'social_security' ? '社保' : '公积金';
    }

    /**
     * 获取月份文本
     */
    public function getMonthTextAttribute()
    {
        return $this->month . '月';
    }

    /**
     * 获取指定账套、类型和月份的缴费日期
     */
    public static function getDueDayForMonth($accountSetId, $paymentType, $month)
    {
        $config = self::where('account_set_id', $accountSetId)
            ->where('payment_type', $paymentType)
            ->where('month', $month)
            ->first();

        return $config ? $config->due_day : 15; // 默认15号
    }

    /**
     * 账套关联
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }
}
