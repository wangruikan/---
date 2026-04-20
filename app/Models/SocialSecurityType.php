<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialSecurityType extends Model
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
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function region()
    {
        return $this->belongsTo(SocialSecurityRegion::class, 'region_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
