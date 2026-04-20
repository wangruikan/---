<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResignationCertificate extends Model
{
    protected $fillable = [
        'employee_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
        'upload_source',
        'remark',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关联员工
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // 关联上传人
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // 获取文件URL
    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }
}
