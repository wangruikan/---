<?php

use App\Models\Permission;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$all = Permission::orderBy('sort_order')->get(['id', 'module', 'action', 'name']);

echo 'total_permissions=' . $all->count() . PHP_EOL;

$lastModules = $all->groupBy('module')->keys()->slice(-10)->values();
echo 'last_modules=' . implode(',', $lastModules->toArray()) . PHP_EOL;

// 输出我们关心的几个模块的明细
$targetModules = [
    'material_assets',
    'material_requests',
    'salary_summaries',
    'salary_payment',
    'special_deductions',
    'payment_summaries',
    'invoice_summaries',
];

foreach ($targetModules as $m) {
    $items = $all->where('module', $m)->values()->toArray();
    echo 'module=' . $m . PHP_EOL;
    echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}

