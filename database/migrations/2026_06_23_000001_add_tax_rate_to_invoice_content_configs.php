<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('invoice_content_configs')) {
            return;
        }

        Schema::table('invoice_content_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_content_configs', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 4)
                    ->default(0)
                    ->after('project_name')
                    ->comment('税率');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('invoice_content_configs')) {
            return;
        }

        Schema::table('invoice_content_configs', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_content_configs', 'tax_rate')) {
                $table->dropColumn('tax_rate');
            }
        });
    }
};
