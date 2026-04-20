<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherInsuranceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'account_set_id',
        'created_by'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    /**
     * 获取该保险种类下的所有保单
     */
    public function policies()
    {
        return $this->hasMany(OtherInsurancePolicy::class, 'type_id');
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

    /**
     * 获取活跃保单数量
     */
    public function getActivePoliciesCountAttribute()
    {
        return $this->policies()->where('status', 'active')->count();
    }

    /**
     * 获取总保险金额
     */
    public function getTotalCoverageAmountAttribute()
    {
        return $this->policies()->where('status', 'active')->sum('coverage_amount');
    }

    /**
     * 获取总保费金额
     */
    public function getTotalPremiumAmountAttribute()
    {
        return $this->policies()->where('status', 'active')->sum('premium_amount');
    }
}
