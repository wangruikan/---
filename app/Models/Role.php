<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'sort_order',
        'visible_menus',
    ];

    protected $casts = [
        'visible_menus' => 'array',
    ];

    /**
     * 角色拥有的权限
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * 检查角色是否拥有某个权限
     */
    public function hasPermission($module, $action)
    {
        return $this->permissions()
            ->where('module', $module)
            ->where('action', $action)
            ->exists();
    }

    /**
     * 获取角色的所有权限ID
     */
    public function getPermissionIds()
    {
        return $this->permissions()->pluck('permissions.id')->toArray();
    }
}
