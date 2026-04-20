<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BasisRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_set_id',
        'project_id',
        'type',
        'month',
        'description',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联项目
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 关联附件
     */
    public function attachments()
    {
        return $this->hasMany(BasisAttachment::class);
    }
}

