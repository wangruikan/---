<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_declaration_configs') && !Schema::hasColumn('tax_declaration_configs', 'declaration_type')) {
            Schema::table('tax_declaration_configs', function (Blueprint $table) {
                $table->string('declaration_type', 20)
                    ->nullable()
                    ->after('period_type')
                    ->comment('申报类型：monthly=月度，quarterly=季度，yearly=年度');
            });

            DB::table('tax_declaration_configs')->update([
                'declaration_type' => DB::raw('period_type'),
            ]);
        }

        if (Schema::hasTable('tax_declaration_tasks') && !Schema::hasColumn('tax_declaration_tasks', 'declaration_type')) {
            Schema::table('tax_declaration_tasks', function (Blueprint $table) {
                $table->string('declaration_type', 20)
                    ->nullable()
                    ->after('config_id')
                    ->comment('申报类型：monthly=月度，quarterly=季度，yearly=年度');
            });

            DB::statement(
                "UPDATE tax_declaration_tasks AS tasks
                 INNER JOIN tax_declaration_configs AS configs ON configs.id = tasks.config_id
                 SET tasks.declaration_type = COALESCE(configs.declaration_type, configs.period_type)
                 WHERE tasks.declaration_type IS NULL"
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tax_declaration_tasks') && Schema::hasColumn('tax_declaration_tasks', 'declaration_type')) {
            Schema::table('tax_declaration_tasks', function (Blueprint $table) {
                $table->dropColumn('declaration_type');
            });
        }

        if (Schema::hasTable('tax_declaration_configs') && Schema::hasColumn('tax_declaration_configs', 'declaration_type')) {
            Schema::table('tax_declaration_configs', function (Blueprint $table) {
                $table->dropColumn('declaration_type');
            });
        }
    }
};
