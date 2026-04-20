<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'account_set_id',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public static function get($key, $default = null, $accountSetId = null)
    {
        $query = static::where('key', $key);
        
        if ($accountSetId !== null) {
            $query->where('account_set_id', $accountSetId);
        } else {
            // 如果没有指定账套ID，则查找全局设置（account_set_id 为 null）
            $query->whereNull('account_set_id');
        }
        
        $setting = $query->first();
        
        if (!$setting) {
            return $default;
        }
        
        return $setting->value;
    }

    public static function set($key, $value, $description = null, $accountSetId = null)
    {
        $query = static::where('key', $key);
        
        if ($accountSetId !== null) {
            $query->where('account_set_id', $accountSetId);
        } else {
            $query->whereNull('account_set_id');
        }
        
        $setting = $query->first();
        
        if (!$setting) {
            $setting = new static();
            $setting->key = $key;
            $setting->description = $description;
            $setting->account_set_id = $accountSetId;
        }
        
        $setting->value = (string) $value;
        $setting->save();
        
        return $setting;
    }
}
