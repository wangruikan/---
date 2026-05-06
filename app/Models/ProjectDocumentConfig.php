<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectDocumentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'document_name',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

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
}

