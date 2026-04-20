<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousingFundConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id',
        'config_name',
        'min_base_amount',
        'max_base_amount',
        'old_min_base_amount',
        'old_max_base_amount',
        'old_limits_updated_at',
        'new_min_base_amount',
        'new_max_base_amount',
        'new_limits_updated_at',
        'employee_ratio',
        'company_ratio',
        'is_default',
        'account_set_id',
        'created_by',
    ];

    protected $casts = [
        'min_base_amount' => 'decimal:2',
        'max_base_amount' => 'decimal:2',
        'old_min_base_amount' => 'decimal:2',
        'old_max_base_amount' => 'decimal:2',
        'old_limits_updated_at' => 'datetime',
        'new_min_base_amount' => 'decimal:2',
        'new_max_base_amount' => 'decimal:2',
        'new_limits_updated_at' => 'datetime',
        'employee_ratio' => 'decimal:4',
        'company_ratio' => 'decimal:4',
        'is_default' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 地区关联
     */
    public function region()
    {
        return $this->belongsTo(HousingFundRegion::class, 'region_id');
    }

    /**
     * 创建人关联
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 账套关联
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 获取总比例
     */
    public function getTotalRatioAttribute()
    {
        return $this->employee_ratio + $this->company_ratio;
    }

    /**
     * 获取员工缴纳金额（基于最大基数）
     */
    public function getEmployeeAmountAttribute()
    {
        return $this->max_base_amount * $this->employee_ratio;
    }

    /**
     * 获取公司缴纳金额（基于最大基数）
     */
    public function getCompanyAmountAttribute()
    {
        return $this->max_base_amount * $this->company_ratio;
    }

    /**
     * 获取总缴纳金额
     */
    public function getTotalAmountAttribute()
    {
        return $this->employee_amount + $this->company_amount;
    }

    /**
     * 获取配置显示名称
     */
    public function getDisplayNameAttribute()
    {
        return $this->config_name . ' (基数: ¥' . number_format($this->min_base_amount, 2) . '-¥' . number_format($this->max_base_amount, 2) . ', 员工: ' . ($this->employee_ratio * 100) . '%, 公司: ' . ($this->company_ratio * 100) . '%)';
    }

    /**
     * 设置默认配置
     */
    public function setAsDefault()
    {
        // 先将同地区的其他配置设为非默认
        static::where('region_id', $this->region_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);
        
        // 设置当前配置为默认
        $this->update(['is_default' => true]);
    }

    /**
     * 获取地区的默认配置
     */
    public static function getDefaultForRegion($regionId)
    {
        return static::where('region_id', $regionId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * 获取地区的所有配置
     */
    public static function getConfigsForRegion($regionId)
    {
        return static::where('region_id', $regionId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }
}