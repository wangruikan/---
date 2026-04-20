<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 发票申请明细模型
 */
class InvoiceItem extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '发票项目';
    
    protected $auditableFields = [
        'item_name' => '项目名称',
        'amount' => '金额',
        'sequence' => '序号',
        'remark' => '备注'
    ];

    protected $table = 'invoice_items';

    protected $fillable = [
        'application_id',
        'invoice_project_id',
        'project_name',
        'sequence',
        'item_name',
        'amount',
        'remark',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->item_name ?: "ID:{$this->id}";
    }

    /**
     * 关联发票申请
     */
    public function application()
    {
        return $this->belongsTo(InvoiceApplication::class, 'application_id');
    }

    /**
     * 关联发票项目
     */
    public function invoiceProject()
    {
        return $this->belongsTo(InvoiceProject::class, 'invoice_project_id');
    }
}

