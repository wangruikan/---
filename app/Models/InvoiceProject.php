<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 发票项目配置模型
 */
class InvoiceProject extends Model
{
    use HasFactory;

    protected $table = 'invoice_projects';

    protected $fillable = [
        'account_set_id',
        'project_name',
        'remark',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    /**
     * 关联发票明细
     */
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_project_id');
    }
}

