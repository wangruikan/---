<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BasisAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'basis_record_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联依据记录
     */
    public function basisRecord()
    {
        return $this->belongsTo(BasisRecord::class);
    }
}

