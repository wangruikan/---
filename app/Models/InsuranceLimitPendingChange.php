<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceLimitPendingChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_type',
        'target_id',
        'account_set_id',
        'pending_min_base_amount',
        'pending_max_base_amount',
        'effective_date',
        'status',
        'created_by',
        'applied_at',
        'error_message',
    ];

    protected $casts = [
        'pending_min_base_amount' => 'decimal:2',
        'pending_max_base_amount' => 'decimal:2',
        'effective_date' => 'date',
        'applied_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDue($query)
    {
        return $query->pending()->where('effective_date', '<=', now()->toDateString());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
