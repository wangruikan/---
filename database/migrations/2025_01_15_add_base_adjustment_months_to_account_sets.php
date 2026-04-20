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
        // 为账套表添加基数调整月份字段
        Schema::table('account_sets', function (Blueprint $table) {
            $table->json('base_adjustment_months')->nullable()->comment('允许基数调整的月份，如[1,2,3]表示1-3月可调整');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 移除基数调整月份字段
        Schema::table('account_sets', function (Blueprint $table) {
            $table->dropColumn('base_adjustment_months');
        });
    }
};
