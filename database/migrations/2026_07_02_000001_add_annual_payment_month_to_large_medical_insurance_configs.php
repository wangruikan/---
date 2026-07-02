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
        Schema::table('large_medical_insurance_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('annual_payment_month')
                ->nullable()
                ->after('payment_cycle')
                ->comment('按年模式生成月份(1-12)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('large_medical_insurance_configs', function (Blueprint $table) {
            $table->dropColumn('annual_payment_month');
        });
    }
};
