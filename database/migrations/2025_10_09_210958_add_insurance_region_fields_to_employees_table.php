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
        Schema::table('employees', function (Blueprint $table) {
            // 只添加缺少的字段
            $table->foreignId('medical_insurance_region_id')->nullable()->constrained('medical_insurance_regions')->onDelete('set null')->comment('医保参保地区ID');
            $table->timestamp('insurance_completed_at')->nullable()->comment('参保完成时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 删除外键约束
            $table->dropForeign(['medical_insurance_region_id']);
            
            // 删除字段
            $table->dropColumn([
                'medical_insurance_region_id', 
                'insurance_completed_at'
            ]);
        });
    }
};
