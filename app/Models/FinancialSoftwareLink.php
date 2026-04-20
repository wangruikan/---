<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialSoftwareLink extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '财务软件链接';
    
    protected $auditableFields = [
        'name' => '软件名称',
        'url' => '软件地址',
        'sort_order' => '排序',
        'is_active' => '启用状态'
    ];

    protected $fillable = [
        'account_set_id',
        'name',
        'url',
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
        return $this->name;
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
     * 关联更新人
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
