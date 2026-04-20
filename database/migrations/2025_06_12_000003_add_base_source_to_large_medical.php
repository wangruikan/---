<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 添加基数来源字段，区分普通地区和特殊地区
     */
    public function up(): void
    {
        Schema::table('large_medical_insurance_configs', function (Blueprint $table) {
            // base_source: employee=使用员工基数(普通地区), config=使用统一基数(特殊地区)
            $table->string('base_source', 20)->default('employee')->after('calculation_type')
                ->comment('基数来源: employee=员工基数, config=统一基数');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('large_medical_insurance_configs', function (Blueprint $table) {
            $table->dropColumn('base_source');
        });
    }
};
