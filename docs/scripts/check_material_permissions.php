<?php

// 简单脚本：检查 material_assets / material_requests 相关权限是否存在

use App\Models\Permission;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$perms = Permission::whereIn('module', ['material_assets', 'material_requests'])
    ->orderBy('module')
    ->orderBy('action')
    ->get(['id', 'module', 'action', 'name'])
    ->toArray();

echo json_encode($perms, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

