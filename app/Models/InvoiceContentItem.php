<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 开票内容明细模型
 */
class InvoiceContentItem extends Model
{
    use HasFactory;

    protected $table = 'invoice_content_items';

    protected $fillable = [
        'application_id',
        'invoice_content_config_id',
        'sequence',
        'project_name',
        'remark',
        'deduction_info',
        'invoice_amount',
        'tax_rate',
        'deduction_amount',
        'invoice_tax_amount',
        'amount_excluding_tax',
        'tax_amount',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'invoice_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'deduction_amount' => 'decimal:2',
        'invoice_tax_amount' => 'decimal:2',
        'amount_excluding_tax' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(InvoiceApplication::class, 'application_id');
    }

    public function config()
    {
        return $this->belongsTo(InvoiceContentConfig::class, 'invoice_content_config_id');
    }
}
