<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceChangeAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_change_id',
        'file_path',
        'original_name',
        'file_size',
        'file_type',
        'uploaded_by',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联到保险变更记录
     */
    public function insuranceChange()
    {
        return $this->belongsTo(InsuranceChange::class);
    }

    /**
     * 关联到上传人
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * 获取文件URL
     */
    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * 格式化文件大小
     */
    public function getFileSizeFormattedAttribute()
    {
        $size = $this->file_size;
        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1024 * 1024) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return round($size / (1024 * 1024), 2) . ' MB';
        }
    }
}

