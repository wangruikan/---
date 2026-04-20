<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxDeclarationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'uploaded_by',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = ['file_url'];

    /**
     * 获取完整的文件 URL
     */
    public function getFileUrlAttribute()
    {
        if (empty($this->file_path)) {
            return null;
        }
        
        // 如果已经是完整URL，直接返回
        if (strpos($this->file_path, 'http') === 0) {
            return $this->file_path;
        }
        
        // 动态获取服务器地址
        $host = request()->getSchemeAndHttpHost();
        return $host . '/' . $this->file_path;
    }

    /**
     * 关联任务
     */
    public function task()
    {
        return $this->belongsTo(TaxDeclarationTask::class, 'task_id');
    }

    /**
     * 关联上传人
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
