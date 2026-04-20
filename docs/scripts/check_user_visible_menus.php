<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Role;

echo "=== 检查用户可见菜单 ===\n\n";

// 获取所有用户
$users = User::all();

foreach ($users as $user) {
    echo "用户: {$user->name} (ID: {$user->id})\n";
    echo "  角色: {$user->role}\n";
    echo "  角色ID: " . ($user->role_id ?? 'null') . "\n";
    
    if ($user->role_id) {
        $role = Role::find($user->role_id);
        if ($role) {
            echo "  角色名称: {$role->display_name}\n";
            echo "  visible_menus: " . ($role->visible_menus === null ? 'null (可见所有菜单)' : json_encode($role->visible_menus, JSON_UNESCAPED_UNICODE)) . "\n";
        } else {
            echo "  ⚠️ 角色不存在\n";
        }
    } else {
        echo "  ⚠️ 未分配角色\n";
    }
    
    echo "\n";
}

echo "=== 检查完成 ===\n";
