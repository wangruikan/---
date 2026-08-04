<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_declaration_tasks') && !Schema::hasColumn('tax_declaration_tasks', 'completed_tax_category_ids')) {
            Schema::table('tax_declaration_tasks', function (Blueprint $table) {
                $table->text('completed_tax_category_ids')
                    ->nullable()
                    ->after('tax_category_ids')
                    ->comment('已完成申报的税种 ID 列表');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tax_declaration_tasks') && Schema::hasColumn('tax_declaration_tasks', 'completed_tax_category_ids')) {
            Schema::table('tax_declaration_tasks', function (Blueprint $table) {
                $table->dropColumn('completed_tax_category_ids');
            });
        }
    }
};
