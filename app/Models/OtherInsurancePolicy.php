<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherInsurancePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'type_id',
        'policy_number',
        'policy_name',
        'insurance_company',
        'coverage_amount',
        'employee_per_capita_cost',
        'quota',
        'premium_amount',
        'contact_name',
        'contact_phone',
        'personnel_name_list',
        'endorsement_number',
        'policy_end_date',
        'start_date',
        'end_date',
        'status',
        'description',
        'account_set_id',
        'created_by'
    ];

    protected $casts = [
        'coverage_amount' => 'float',
        'employee_per_capita_cost' => 'float',
        'quota' => 'integer',
        'premium_amount' => 'float',
        'personnel_name_list' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    /**
     * 获取所属保险种类
     */
    public function type()
    {
        return $this->belongsTo(OtherInsuranceType::class, 'type_id');
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
     * 检查保单是否即将到期（30天内）
     */
    public function getIsExpiringSoonAttribute()
    {
        return $this->end_date <= now()->addDays(30) && $this->status === 'active';
    }

    /**
     * 检查保单是否已过期
     */
    public function getIsExpiredAttribute()
    {
        return $this->end_date < now() && $this->status === 'active';
    }

    /**
     * 获取保单剩余天数
     */
    public function getRemainingDaysAttribute()
    {
        if ($this->status !== 'active') {
            return 0;
        }
        
        $remaining = now()->diffInDays($this->end_date, false);
        return max(0, $remaining);
    }
}
