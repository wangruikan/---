<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionPortal extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '地区网页入口';
    
    protected $auditableFields = [
        'region_name' => '地区名称',
        'business_type' => '业务类型',
        'portal_name' => '入口名称',
        'portal_url' => '入口网址',
        'remarks' => '备注',
        'sort_order' => '排序',
        'is_active' => '启用状态'
    ];

    protected $fillable = [
        'account_set_id',
        'region_name',
        'business_type',
        'portal_name',
        'portal_url',
        'remarks',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->region_name} - {$this->portal_name}";
    }

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    // 关联创建人
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 关联更新人
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

