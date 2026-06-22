<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_delivery_configs', function (Blueprint $table) {
            $table->enum('delivery_release_month', ['current', 'next'])
                ->default('current')
                ->after('delivery_method');
        });

        Schema::table('document_deliveries', function (Blueprint $table) {
            $table->enum('delivery_release_month', ['current', 'next'])
                ->default('current')
                ->after('delivery_method');
            $table->string('display_month', 7)
                ->nullable()
                ->after('delivery_period');
        });

        DB::table('document_deliveries')
            ->whereNull('display_month')
            ->update([
                'display_month' => DB::raw('delivery_period'),
            ]);
    }

    public function down(): void
    {
        Schema::table('document_deliveries', function (Blueprint $table) {
            $table->dropColumn(['delivery_release_month', 'display_month']);
        });

        Schema::table('project_delivery_configs', function (Blueprint $table) {
            $table->dropColumn('delivery_release_month');
        });
    }
};
