<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentFormHistory extends Model
{
    use HasFactory;

    protected $table = 'payment_form_history';

    protected $fillable = [
        'bank_account',
        'department',
        'payee',
        'amount_small',
        'amount_large',
        'payment_method',
        'bank',
        'purpose',
        'invoice_status',
        'user_id',
    ];

    protected $casts = [
        'payment_method' => 'array',
        'invoice_status' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

