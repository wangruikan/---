<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectDocumentSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'set_name',
        'sort_order',
        'is_default',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function configs()
    {
        return $this->hasMany(ProjectDocumentConfig::class, 'document_set_id')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');
    }
}
