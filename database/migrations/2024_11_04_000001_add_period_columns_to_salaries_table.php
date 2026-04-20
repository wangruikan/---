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
            $table->integer('period_start')->nullable()->comment('工资周期开始日期')->after('month');
            $table->integer('period_end')->nullable()->comment('工资周期结束日期')->after('period_start');
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

