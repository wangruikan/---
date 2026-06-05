<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_projects', function (Blueprint $table) {
            $table->string('spec_model', 255)->nullable()->after('project_name')->comment('规格型号');
            $table->string('unit', 50)->nullable()->after('spec_model')->comment('单位');
            $table->decimal('quantity', 15, 4)->nullable()->after('unit')->comment('数量');
            $table->decimal('unit_price', 15, 2)->nullable()->after('quantity')->comment('单价（不含税）');
            $table->decimal('amount', 15, 2)->nullable()->after('unit_price')->comment('金额（不含税）');
            $table->decimal('tax_rate', 5, 4)->default(0)->after('amount')->comment('税率/征收率');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate')->comment('税额');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('spec_model', 255)->nullable()->after('item_name')->comment('规格型号');
            $table->string('unit', 50)->nullable()->after('spec_model')->comment('单位');
            $table->decimal('quantity', 15, 4)->nullable()->after('unit')->comment('数量');
            $table->decimal('unit_price', 15, 2)->nullable()->after('quantity')->comment('单价（不含税）');
            $table->decimal('tax_rate', 5, 4)->default(0)->after('amount')->comment('税率/征收率');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate')->comment('税额');
        });

        DB::statement("ALTER TABLE `invoice_items` MODIFY `invoice_project_id` BIGINT UNSIGNED NULL COMMENT '项目配置ID'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `invoice_items` MODIFY `invoice_project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目配置ID'");

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'spec_model',
                'unit',
                'quantity',
                'unit_price',
                'tax_rate',
                'tax_amount',
            ]);
        });

        Schema::table('invoice_projects', function (Blueprint $table) {
            $table->dropColumn([
                'spec_model',
                'unit',
                'quantity',
                'unit_price',
                'amount',
                'tax_rate',
                'tax_amount',
            ]);
        });
    }
};
