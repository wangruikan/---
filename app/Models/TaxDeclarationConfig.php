<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TaxDeclarationConfig extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '税费申报配置';

    protected $fillable = [
        'account_set_id',
        'company_name',
        'tax_category_ids',
        'period_type',
        'declaration_date',
        'created_by',
    ];

    protected $casts = [
        'tax_category_ids' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $auditableFields = [
        'company_name' => '公司名称',
        'period_type' => '申报周期',
        'declaration_date' => '申报日期',
    ];

    public function getAuditIdentifier()
    {
        return $this->company_name . ' - ' . $this->declaration_date;
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 获取税种列表
     */
    public function getTaxCategoriesAttribute()
    {
        if (empty($this->tax_category_ids)) {
            return collect();
        }
        
        return TaxCategory::whereIn('id', $this->tax_category_ids)->get();
    }
}
