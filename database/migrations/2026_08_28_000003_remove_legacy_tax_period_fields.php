<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tax_declaration_configs')) {
            return;
        }

        $legacyColumns = array_values(array_filter([
            Schema::hasColumn('tax_declaration_configs', 'period_type') ? 'period_type' : null,
            Schema::hasColumn('tax_declaration_configs', 'declaration_type') ? 'declaration_type' : null,
        ]));

        if (empty($legacyColumns)) {
            return;
        }

        Schema::table('tax_declaration_configs', function (Blueprint $table) use ($legacyColumns) {
            foreach ($legacyColumns as $column) {
                $table->dropColumn($column);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tax_declaration_configs')) {
            return;
        }

        Schema::table('tax_declaration_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('tax_declaration_configs', 'period_type')) {
                $table->enum('period_type', ['monthly', 'quarterly', 'yearly'])
                    ->nullable()
                    ->after('tax_category_ids');
            }

            if (!Schema::hasColumn('tax_declaration_configs', 'declaration_type')) {
                $table->string('declaration_type', 20)
                    ->nullable()
                    ->after('period_type');
            }
        });
    }
};
