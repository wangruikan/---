<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'image_path',
        'original_filename',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取图片URL
     */
    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /**
     * 设置为默认印章
     */
    public function setAsDefault()
    {
        // 先取消该用户的所有默认印章
        self::where('user_id', $this->user_id)->update(['is_default' => false]);
        
        // 设置当前印章为默认
        $this->update(['is_default' => true]);
    }
}

