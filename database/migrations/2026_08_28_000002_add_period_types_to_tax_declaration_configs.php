<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_declaration_configs') && !Schema::hasColumn('tax_declaration_configs', 'period_types')) {
            Schema::table('tax_declaration_configs', function (Blueprint $table) {
                $table->text('period_types')
                    ->nullable()
                    ->after('period_type')
                    ->comment('申报周期列表：monthly=月度，quarterly=季度，yearly=年度');
            });

            DB::statement(
                "UPDATE tax_declaration_configs
                 SET period_types = CONCAT('[\"', period_type, '\"]')
                 WHERE period_types IS NULL"
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tax_declaration_configs') && Schema::hasColumn('tax_declaration_configs', 'period_types')) {
            Schema::table('tax_declaration_configs', function (Blueprint $table) {
                $table->dropColumn('period_types');
            });
        }
    }
};
