<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'social_security_regions',
            'medical_insurance_regions',
            'housing_fund_regions',
        ] as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'account_opening_month')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->date('account_opening_month')
                    ->nullable()
                    ->comment('开户年月，按月份保存为该月第一天');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'social_security_regions',
            'medical_insurance_regions',
            'housing_fund_regions',
        ] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'account_opening_month')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('account_opening_month');
                });
            }
        }
    }
};
