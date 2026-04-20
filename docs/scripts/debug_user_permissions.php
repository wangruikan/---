<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "==== 用户权限调试脚本 ====" . PHP_EOL;

// 如果通过命令行参数传入了 user_id，则只检查该用户
$userId = null;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $userId = (int) $argv[1];
    echo "仅检查用户 ID = {$userId}" . PHP_EOL;
}

$query = User::query();

if ($userId) {
    $query->where('id', $userId);
} else {
    // 默认只看非 admin 用户，避免输出太多
    $query->where('role', '!=', 'admin');
}

$users = $query->orderBy('id')->get();

if ($users->isEmpty()) {
    echo "未找到符合条件的用户。" . PHP_EOL;
    exit(0);
}

foreach ($users as $user) {
    $roleModel = $user->roleModel();
    $rolePermissionCount = $roleModel ? $roleModel->permissions()->count() : 0;
    $userDirectPermissionCount = $user->permissions()->count();
    $permissionKeys = $user->getPermissionKeys();
    $mergedPermissionCount = is_array($permissionKeys) ? count($permissionKeys) : 0;

    echo PHP_EOL;
    echo "----------------------------------------" . PHP_EOL;
    echo "用户 ID: {$user->id}" . PHP_EOL;
    echo "姓名: {$user->name}" . PHP_EOL;
    echo "邮箱: {$user->email}" . PHP_EOL;
    echo "users.role 字段: {$user->role}" . PHP_EOL;
    echo "roleModel 是否找到: " . ($roleModel ? 'YES' : 'NO') . PHP_EOL;
    if ($roleModel) {
        echo "角色 ID / name: {$roleModel->id} / {$roleModel->name}" . PHP_EOL;
    }
    echo "角色权限数量 (role_permissions): {$rolePermissionCount}" . PHP_EOL;
    echo "用户直配权限数量 (user_permissions): {$userDirectPermissionCount}" . PHP_EOL;
    echo "合并后的权限标识数量 (getPermissionKeys): {$mergedPermissionCount}" . PHP_EOL;

    // 只输出我们关心的几个模块的权限，避免太长
    $targetModules = [
        'salary_summaries',
        'salary_payment',
        'special_deductions',
        'payment_summaries',
        'invoice_summaries',
        'material_assets',
        'material_requests',
    ];

    $byModule = [];
    foreach ($permissionKeys as $key) {
        [$module, $action] = explode('.', $key);
        if (in_array($module, $targetModules, true)) {
            $byModule[$module][] = $action;
        }
    }

    foreach ($targetModules as $m) {
        $actions = $byModule[$m] ?? [];
        echo "  模块 {$m}: " . (empty($actions) ? '无权限' : implode(',', $actions)) . PHP_EOL;
    }
}

echo PHP_EOL . "==== 调试结束 ====" . PHP_EOL;

