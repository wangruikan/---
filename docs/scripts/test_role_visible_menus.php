<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Role;

echo "=== 测试角色菜单显示权限 ===\n\n";

// 1. 检查 roles 表是否有 visible_menus 字段
echo "1. 检查数据库字段...\n";
try {
    $columns = DB::select("SHOW COLUMNS FROM roles LIKE 'visible_menus'");
    if (empty($columns)) {
        echo "❌ roles 表中没有 visible_menus 字段！\n";
        echo "请执行 SQL: ALTER TABLE roles ADD COLUMN visible_menus TEXT NULL COMMENT '可见菜单ID列表(JSON格式)' AFTER description;\n";
        exit;
    } else {
        echo "✅ visible_menus 字段存在\n";
        print_r($columns);
    }
} catch (Exception $e) {
    echo "❌ 检查字段失败: " . $e->getMessage() . "\n";
    exit;
}

echo "\n2. 获取所有角色...\n";
$roles = Role::all();
foreach ($roles as $role) {
    echo "角色: {$role->display_name} ({$role->name})\n";
    echo "  visible_menus: " . ($role->visible_menus === null ? 'null' : json_encode($role->visible_menus, JSON_UNESCAPED_UNICODE)) . "\n";
}

echo "\n3. 测试更新角色菜单...\n";
$testRole = Role::where('name', '!=', 'super_admin')
    ->where('name', '!=', 'admin')
    ->first();

if (!$testRole) {
    echo "❌ 没有找到可测试的角色（非管理员角色）\n";
    exit;
}

echo "测试角色: {$testRole->display_name} (ID: {$testRole->id})\n";

// 保存原始值
$originalMenus = $testRole->visible_menus;
echo "原始 visible_menus: " . ($originalMenus === null ? 'null' : json_encode($originalMenus, JSON_UNESCAPED_UNICODE)) . "\n";

// 测试更新
$testMenus = ['home', 'personnel', 'personnel--employees'];
echo "\n尝试更新为: " . json_encode($testMenus, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $testRole->visible_menus = $testMenus;
    $testRole->save();
    echo "✅ 保存成功\n";
    
    // 重新读取验证
    $testRole->refresh();
    echo "重新读取后的值: " . json_encode($testRole->visible_menus, JSON_UNESCAPED_UNICODE) . "\n";
    
    if ($testRole->visible_menus === $testMenus) {
        echo "✅ 值匹配，保存成功！\n";
    } else {
        echo "⚠️ 值不匹配\n";
        echo "期望: " . json_encode($testMenus, JSON_UNESCAPED_UNICODE) . "\n";
        echo "实际: " . json_encode($testRole->visible_menus, JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    // 恢复原始值
    echo "\n恢复原始值...\n";
    $testRole->visible_menus = $originalMenus;
    $testRole->save();
    echo "✅ 已恢复\n";
    
} catch (Exception $e) {
    echo "❌ 保存失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 测试完成 ===\n";
