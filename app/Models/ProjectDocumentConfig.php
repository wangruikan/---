<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectDocumentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'document_set_id',
        'document_name',
        'document_type',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'document_set_id' => 'integer',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['document_type_text'];

    /**
     * 关联项目
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 关联员工上传的文件
     */
    public function employeeDocuments()
    {
        return $this->hasMany(EmployeeDocument::class, 'document_config_id');
    }

    public function documentSet()
    {
        return $this->belongsTo(ProjectDocumentSet::class, 'document_set_id');
    }

    public function getDocumentTypeAttribute($value)
    {
        return $value ?: 'all';
    }

    public function getDocumentTypeTextAttribute()
    {
        return match ($this->document_type) {
            'image' => '仅图片',
            'pdf' => '仅PDF',
            'document' => '文档',
            default => '所有类型',
        };
    }
}

