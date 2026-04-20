<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SharedFile extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '共享文件';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'name' => '文件名',
        'original_name' => '原始文件名',
        'path' => '文件路径',
        'type' => '文件类型',
        'size' => '文件大小',
        'description' => '描述',
        'file_category' => '文件分类',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->name;
    }

    protected $fillable = [
        'name',
        'original_name',
        'path',
        'type',
        'size',
        'uploader_id',
        'description',
        'account_set_id',  // 【账套关联】
        'file_category',   // 【文件分类】shared=共享文件, notice=须知文件
    ];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
