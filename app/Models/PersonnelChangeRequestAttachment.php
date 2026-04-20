<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonnelChangeRequestAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'personnel_change_request_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联人员变动申请
     */
    public function personnelChangeRequest()
    {
        return $this->belongsTo(PersonnelChangeRequest::class);
    }
}

