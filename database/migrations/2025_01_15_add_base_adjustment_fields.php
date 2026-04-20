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
        // 社保表已经包含基数调整字段，无需重复添加
        
        // 为公积金表添加基数调整字段
        Schema::table('housing_funds', function (Blueprint $table) {
            $table->decimal('adjustment_base', 10, 2)->nullable()->comment('调整基数（预设值）');
            $table->date('effective_date')->nullable()->comment('生效时间');
        });
    }

    /**
     * Reverse the entire migration.
     */
    public function down(): void
    {
        // 移除公积金表的基数调整字段
        Schema::table('housing_funds', function (Blueprint $table) {
            $table->dropColumn(['adjustment_base', 'effective_date']);
        });
    }
};
