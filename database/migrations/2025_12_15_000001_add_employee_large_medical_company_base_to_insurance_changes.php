<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 添加员工大额医疗保险单位基数字段（特殊地区支持个人/单位两个不同基数）
     */
    public function up(): void
    {
        Schema::table('insurance_changes', function (Blueprint $table) {
            // 在 employee_large_medical_base 字段后添加单位基数字段
            $table->decimal('employee_large_medical_company_base', 10, 2)
                ->nullable()
                ->after('employee_large_medical_base')
                ->comment('员工大额医疗保险单位基数（特殊地区）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_changes', function (Blueprint $table) {
            $table->dropColumn('employee_large_medical_company_base');
        });
    }
};
