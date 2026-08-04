<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_projects') && !Schema::hasColumn('invoice_projects', 'sort_order')) {
            Schema::table('invoice_projects', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('project_name')->comment('排序');
                $table->index(['account_set_id', 'sort_order'], 'idx_invoice_projects_account_sort');
            });
        }

        if (Schema::hasTable('invoice_content_configs') && !Schema::hasColumn('invoice_content_configs', 'sort_order')) {
            Schema::table('invoice_content_configs', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('project_name')->comment('排序');
                $table->index(['account_set_id', 'sort_order'], 'idx_invoice_content_configs_account_sort');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_projects') && Schema::hasColumn('invoice_projects', 'sort_order')) {
            Schema::table('invoice_projects', function (Blueprint $table) {
                $table->dropIndex('idx_invoice_projects_account_sort');
                $table->dropColumn('sort_order');
            });
        }

        if (Schema::hasTable('invoice_content_configs') && Schema::hasColumn('invoice_content_configs', 'sort_order')) {
            Schema::table('invoice_content_configs', function (Blueprint $table) {
                $table->dropIndex('idx_invoice_content_configs_account_sort');
                $table->dropColumn('sort_order');
            });
        }
    }
};
