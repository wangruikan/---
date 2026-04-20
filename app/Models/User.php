<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '用户';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'name' => '用户名',
        'nickname' => '昵称',
        'email' => '邮箱',
        'role' => '角色',
        'is_active' => '是否激活',
        'phone' => '手机号',
        'avatar' => '头像',
        'current_account_set_id' => '当前账套',
        'can_view_operation_barrage' => '弹幕权限',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->name;
    }

    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'role',
        'is_active',
        'phone',
        'avatar',
        'current_account_set_id',
        'can_view_operation_barrage',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'can_view_operation_barrage' => 'boolean',
    ];

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isManager()
    {
        return $this->role === 'manager';
    }

    public function isEmployee()
    {
        return $this->role === 'employee';
    }

    /**
     * 检查用户是否是当前账套的经办人员
     */
    public function isHandlerForAccountSet($accountSetId)
    {
        $setting = \App\Models\SystemSetting::where('account_set_id', $accountSetId)
            ->where('key', 'handler_user_id')
            ->first();

        return $setting && $setting->value == $this->id;
    }

    /**
     * 检查用户是否是审批人员（非经办人员的管理员或经理）
     */
    public function isApproverForAccountSet($accountSetId)
    {
        // 如果是经办人员，则不是审批人员
        if ($this->isHandlerForAccountSet($accountSetId)) {
            return false;
        }

        // 管理员和经理是审批人员
        return in_array($this->role, ['admin', 'manager']);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->notifications()->where('is_read', false);
    }

    /**
     * 关联的账套
     */
    public function accountSets()
    {
        return $this->belongsToMany(AccountSet::class, 'account_set_users')
            ->withPivot('role', 'is_default', 'approval_level', 'approval_level_name')
            ->withTimestamps();
    }

    /**
     * 关联的权限
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withTimestamps();
    }

    /**
     * 获取用户的角色模型
     */
    public function roleModel()
    {
        return Role::where('name', $this->role)->first();
    }

    /**
     * 检查用户是否有某个权限
     * @param string $permissionKey 权限标识，如：employees.view
     */
    public function hasPermission($permissionKey)
    {
        // super_admin 拥有所有权限
        if ($this->role === 'super_admin') {
            return true;
        }

        // 解析权限标识
        $parts = explode('.', $permissionKey);
        if (count($parts) !== 2) {
            return false;
        }

        [$module, $action] = $parts;

        // 只根据角色权限判断是否拥有指定权限
        // 不再考虑用户直接分配的权限（user_permissions），统一由角色控制
        $role = $this->roleModel();
        if ($role && $role->hasPermission($module, $action)) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否有多个权限中的任意一个
     * @param array $permissionKeys 权限标识数组
     */
    public function hasAnyPermission(array $permissionKeys)
    {
        if ($this->role === 'admin') {
            return true;
        }

        foreach ($permissionKeys as $key) {
            if ($this->hasPermission($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查用户是否拥有所有指定权限
     * @param array $permissionKeys 权限标识数组
     */
    public function hasAllPermissions(array $permissionKeys)
    {
        if ($this->role === 'admin') {
            return true;
        }

        foreach ($permissionKeys as $key) {
            if (!$this->hasPermission($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取用户的所有权限标识列表
     */
    public function getPermissionKeys()
    {
        // super_admin 返回所有权限
        if ($this->role === 'super_admin') {
            return Permission::all()->map(function ($p) {
                return $p->module . '.' . $p->action;
            })->toArray();
        }

        // 仅根据角色返回权限列表，忽略用户直配权限（user_permissions）
        $role = $this->roleModel();
        if (!$role) {
            return [];
        }

        return $role->permissions->map(function ($p) {
            return $p->module . '.' . $p->action;
        })->unique()->values()->toArray();
    }

    /**
     * 检查用户是否是超级管理员
     */
    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }
}
