<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'project_id',
        'document_config_id',
        'document_name',
        'file_path',
        'original_filename',
        'file_size',
        'file_type',
        'upload_source',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    protected $appends = ['file_url'];

    /**
     * 关联员工
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 关联项目
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 关联资料配置
     */
    public function documentConfig()
    {
        return $this->belongsTo(ProjectDocumentConfig::class, 'document_config_id');
    }

    /**
     * 获取文件完整URL
     */
    public function getFileUrlAttribute()
    {
        if (!$this->file_path) {
            return null;
        }

        // 如果已经是完整URL，直接返回
        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        // 直接使用public路径，不通过storage
        return url($this->file_path);
    }

    /**
     * 获取文件大小的可读格式
     */
    public function getFileSizeFormattedAttribute()
    {
        if (!$this->file_size) {
            return '-';
        }

        $size = $this->file_size;
        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1024 * 1024) {
            return round($size / 1024, 2) . ' KB';
        } elseif ($size < 1024 * 1024 * 1024) {
            return round($size / (1024 * 1024), 2) . ' MB';
        } else {
            return round($size / (1024 * 1024 * 1024), 2) . ' GB';
        }
    }
}

