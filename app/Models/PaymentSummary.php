<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_request_id',
        'payment_type',
        'category',               // 类目：报销/差旅/采购/项目/其他
        'account_set_id',
        'month',
        'project',
        'apply_date',
        'unit_name',
        'invoice_number',
        'verified',
        'payment_date',
        'expenditure_amount',
        'project_name',
        'summary',
        'invoice_received',
        'invoice_type',
        'invoice_amount',
        'tax_rate',
        'deduction_amount',
        'amount_excluding_tax',
        'tax_amount',
        'is_consistent',
        'status_checked',
        'selected_month',
        'reimburser',
        'invoice_date',
        'accounted',
        'company',
        'amount',
        'approved_at',
    ];

    protected $casts = [
        'apply_date' => 'date',
        'payment_date' => 'date',
        'invoice_date' => 'date',
        'approved_at' => 'datetime',
        'verified' => 'boolean',
        'invoice_received' => 'boolean',
        'is_consistent' => 'boolean',
        'status_checked' => 'boolean',
        'accounted' => 'boolean',
        'expenditure_amount' => 'decimal:2',
        'invoice_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'amount_excluding_tax' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关联付款申请
    public function paymentRequest()
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }
}

