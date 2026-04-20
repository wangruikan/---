<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousingFundRegion extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_name',
        'account_number',
        'company_name',
        'account_set_id',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

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
     * 该地区下的所有配置
     */
    public function configs()
    {
        return $this->hasMany(HousingFundConfig::class, 'region_id');
    }

    /**
     * 该地区的默认配置
     */
    public function defaultConfig()
    {
        return $this->hasOne(HousingFundConfig::class, 'region_id')->where('is_default', true);
    }

    /**
     * 获取配置数量
     */
    public function getConfigCountAttribute()
    {
        return $this->configs()->count();
    }

    /**
     * 是否有默认配置
     */
    public function getHasDefaultAttribute()
    {
        return $this->defaultConfig()->exists();
    }
}
