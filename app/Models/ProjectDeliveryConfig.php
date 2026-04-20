<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectDeliveryConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'project_id',
        'delivery_cycle',
        'delivery_method',
        'required_documents',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'required_documents' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    // 关联项目
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // 关联创建人
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 关联更新人
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

