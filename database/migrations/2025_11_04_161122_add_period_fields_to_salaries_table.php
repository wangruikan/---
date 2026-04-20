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
        Schema::table('salaries', function (Blueprint $table) {
            $table->integer('period_start')->nullable()->after('month')->comment('工资周期开始日期');
            $table->integer('period_end')->nullable()->after('period_start')->comment('工资周期结束日期');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
