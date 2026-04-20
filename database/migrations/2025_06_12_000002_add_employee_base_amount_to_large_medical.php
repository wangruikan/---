<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 大额医疗保险支持个人基数和公司基数分开设置
     */
    public function up(): void
    {
        // 1. 配置表：新增个人基数字段（原base_amount作为公司基数）
        Schema::table('large_medical_insurance_configs', function (Blueprint $table) {
            $table->decimal('employee_base_amount', 12, 2)->nullable()->after('base_amount')->comment('个人基数（原base_amount为公司基数）');
        });

        // 2. 员工表：新增公司基数字段（原large_medical_base作为个人基数）
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('large_medical_company_base', 12, 2)->nullable()->after('large_medical_base')->comment('大额医疗公司基数');
        });

        // 3. 保险人员表：新增公司基数字段
        Schema::table('insurance_personnel', function (Blueprint $table) {
            $table->decimal('employee_large_medical_company_base', 12, 2)->nullable()->after('employee_large_medical_base')->comment('大额医疗公司基数');
        });

        // 4. 调基表：新增公司基数相关字段
        Schema::table('base_adjustments', function (Blueprint $table) {
            $table->decimal('old_large_medical_company_base', 12, 2)->nullable()->after('old_large_medical_base')->comment('旧大额医疗公司基数');
            $table->decimal('new_large_medical_company_base', 12, 2)->nullable()->after('new_large_medical_base')->comment('新大额医疗公司基数');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('large_medical_insurance_configs', function (Blueprint $table) {
            $table->dropColumn('employee_base_amount');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('large_medical_company_base');
        });

        Schema::table('insurance_personnel', function (Blueprint $table) {
            $table->dropColumn('employee_large_medical_company_base');
        });

        Schema::table('base_adjustments', function (Blueprint $table) {
            $table->dropColumn(['old_large_medical_company_base', 'new_large_medical_company_base']);
        });
    }
};
