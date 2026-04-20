<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    use HasFactory;

    protected $table = 'blacklist';

    protected $fillable = [
        'id_number',
        'name',
        'reason',
        'created_by'
    ];

    /**
     * 创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 检查身份证号是否在黑名单中
     */
    public static function isBlacklisted($idNumber)
    {
        return self::where('id_number', $idNumber)->exists();
    }

    /**
     * 获取黑名单信息
     */
    public static function getBlacklistInfo($idNumber)
    {
        return self::where('id_number', $idNumber)->first();
    }
}
