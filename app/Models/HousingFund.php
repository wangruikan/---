<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousingFund extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_name',
        'base_amount',
        'employee_ratio',
        'company_ratio',
        'account_set_id',
        'created_by',
        'adjustment_base',
        'effective_date',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'employee_ratio' => 'decimal:4',
        'company_ratio' => 'decimal:4',
        'adjustment_base' => 'decimal:2',
        'effective_date' => 'date',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
            // 将调整基数应用到当前基数
            $this->base_amount = $this->adjustment_base;
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
        
        return $this->base_amount;
    }

    /**
     * 设置基数调整
     */
    public function setAdjustment($newBase, $effectiveDate)
    {
        $this->adjustment_base = $newBase;
        $this->effective_date = $effectiveDate;
        return $this->save();
    }
}
