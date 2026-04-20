<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelApplicationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'travel_application_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联差旅申请
     */
    public function travelApplication()
    {
        return $this->belongsTo(TravelApplication::class);
    }
}

