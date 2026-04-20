<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalInsuranceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id',
        'name',
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
        'account_set_id',
        'created_by'
    ];

    protected $casts = [
        'min_base_amount' => 'float',
        'max_base_amount' => 'float',
        'old_min_base_amount' => 'decimal:2',
        'old_max_base_amount' => 'decimal:2',
        'old_limits_updated_at' => 'datetime',
        'new_min_base_amount' => 'decimal:2',
        'new_max_base_amount' => 'decimal:2',
        'new_limits_updated_at' => 'datetime',
        'employee_ratio' => 'float',
        'company_ratio' => 'float',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    /**
     * 获取所属地区
     */
    public function region()
    {
        return $this->belongsTo(MedicalInsuranceRegion::class, 'region_id');
    }

    /**
     * 获取所属账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    /**
     * 获取创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

