<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$employee = \App\Models\Employee::find(58);

if ($employee) {
    echo "员工ID: {$employee->id}\n";
    echo "姓名: {$employee->name}\n";
    echo "hire_date: {$employee->hire_date}\n";
    echo "offline_onboarding_date: {$employee->offline_onboarding_date}\n";
    echo "contract_upload_deadline: {$employee->contract_upload_deadline}\n";
    echo "contract_status: {$employee->contract_status}\n";
    echo "is_offline_onboarding: " . ($employee->is_offline_onboarding ? 'true' : 'false') . "\n";
} else {
    echo "员工不存在\n";
}
