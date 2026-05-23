<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceChangeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_change_id',
        'category',
        'change_type',
        'status',
        'category_snapshot',
        'change_details',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function insuranceChange()
    {
        return $this->belongsTo(InsuranceChange::class);
    }

    public function attachments()
    {
        return $this->hasMany(InsuranceChangeAttachment::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
