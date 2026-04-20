<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'report_title',
        'region_id',
        'region_type',
        'fields',
        'header_fields',
        'footer_fields',
        'account_set_id',
        'created_by'
    ];

    protected $casts = [
        'fields' => 'array',
        'header_fields' => 'array',
        'footer_fields' => 'array'
    ];

    // 关联创建人
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }
}
