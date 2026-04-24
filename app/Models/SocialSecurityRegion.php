<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialSecurityRegion extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '社保地区';

    protected $auditableFields = [
        'name' => '地区名称',
        'code' => '社保编号',
        'company' => '单位',
        'min_base_amount' => '最低基数',
        'max_base_amount' => '最高基数',
        'effective_date' => '生效时间',
    ];

    public function getAuditIdentifier()
    {
        return $this->name;
    }

    protected $fillable = [
        'name',
        'code',
        'company',
        'min_base_amount',
        'max_base_amount',
        'old_min_base_amount',
        'old_max_base_amount',
        'old_limits_updated_at',
        'new_min_base_amount',
        'new_max_base_amount',
        'new_limits_updated_at',
        'account_set_id',
        'created_by',
        'adjustment_base',
        'effective_date',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'effective_date' => 'date',
        'adjustment_base' => 'decimal:2',
        'min_base_amount' => 'decimal:2',
        'max_base_amount' => 'decimal:2',
        'old_min_base_amount' => 'decimal:2',
        'old_max_base_amount' => 'decimal:2',
        'old_limits_updated_at' => 'datetime',
        'new_min_base_amount' => 'decimal:2',
        'new_max_base_amount' => 'decimal:2',
        'new_limits_updated_at' => 'datetime',
    ];

    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function socialSecurityTypes()
    {
        return $this->hasMany(SocialSecurityType::class, 'region_id');
    }

    /**
     * 检查是否有待生效的基数调整
     */
    public function hasPendingAdjustment()
    {
        return !is_null($this->adjustment_base) && !is_null($this->effective_date);
    }

    /**
     * 检查基数调整是否已生效
     */
    public function isAdjustmentEffective()
    {
        return $this->hasPendingAdjustment() && now()->toDateString() >= $this->effective_date;
    }

    /**
     * 应用基数调整
     */
    public function applyAdjustment()
    {
        if ($this->isAdjustmentEffective()) {
            // 这里需要更新相关的社保类型基数
            // 具体实现需要根据业务逻辑调整
            $this->adjustment_base = null;
            $this->effective_date = null;
            $this->save();
            
            return true;
        }
        
        return false;
    }

    /**
     * 获取当前有效的基数（考虑调整）
     */
    public function getCurrentBaseAttribute()
    {
        if ($this->isAdjustmentEffective()) {
            return $this->adjustment_base;
        }
        
        // 返回社保类型的基数，这里需要根据实际业务逻辑调整
        return $this->socialSecurityTypes()->first()?->base_amount ?? 0;
    }
}
