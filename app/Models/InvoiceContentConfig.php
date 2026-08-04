<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 开票内容配置项目模型
 */
class InvoiceContentConfig extends Model
{
    use HasFactory;

    protected $table = 'invoice_content_configs';

    protected $fillable = [
        'account_set_id',
        'project_name',
        'sort_order',
        'tax_rate',
        'remark',
        'deduction_info',
        'created_by',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:4',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }
}
