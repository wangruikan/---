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
            // 修改所有可能导致数据截断的字段
            if (Schema::hasColumn('employees', 'country_region')) {
                $table->string('country_region', 100)->nullable()->change()->comment('国籍(地区)');
            }
            if (Schema::hasColumn('employees', 'chinese_name')) {
                $table->string('chinese_name', 100)->nullable()->change()->comment('中文名');
            }
            if (Schema::hasColumn('employees', 'birth_country')) {
                $table->string('birth_country', 100)->nullable()->change()->comment('出生国家(地区)');
            }
            if (Schema::hasColumn('employees', 'other_id_type')) {
                $table->string('other_id_type', 50)->nullable()->change()->comment('其他证件类型');
            }
            if (Schema::hasColumn('employees', 'personnel_status')) {
                $table->string('personnel_status', 50)->nullable()->change()->comment('人员状态');
            }
            if (Schema::hasColumn('employees', 'employment_type')) {
                $table->string('employment_type', 100)->nullable()->change()->comment('任职受雇从业类型');
            }
            if (Schema::hasColumn('employees', 'annual_employment_status')) {
                $table->string('annual_employment_status', 100)->nullable()->change()->comment('入职年度就业情形');
            }
            if (Schema::hasColumn('employees', 'job_title')) {
                $table->string('job_title', 50)->nullable()->change()->comment('职务');
            }
            if (Schema::hasColumn('employees', 'disability_cert_type')) {
                $table->string('disability_cert_type', 50)->nullable()->change()->comment('残疾证件类型');
            }
            if (Schema::hasColumn('employees', 'tax_matter')) {
                $table->string('tax_matter', 50)->nullable()->change()->comment('涉税事由');
            }
            if (Schema::hasColumn('employees', 'email_address')) {
                $table->string('email_address', 255)->nullable()->change()->comment('电子邮箱');
            }
            if (Schema::hasColumn('employees', 'bank_province')) {
                $table->string('bank_province', 50)->nullable()->change()->comment('开户行省份');
            }
            if (Schema::hasColumn('employees', 'other_notes')) {
                $table->string('other_notes', 200)->nullable()->change()->comment('其他情况说明');
            }
            
            // 地址相关字段
            if (Schema::hasColumn('employees', 'household_province')) {
                $table->string('household_province', 50)->nullable()->change()->comment('户籍所在地（省）');
            }
            if (Schema::hasColumn('employees', 'household_city')) {
                $table->string('household_city', 50)->nullable()->change()->comment('户籍所在地（市）');
            }
            if (Schema::hasColumn('employees', 'household_district')) {
                $table->string('household_district', 50)->nullable()->change()->comment('户籍所在地（区县）');
            }
            if (Schema::hasColumn('employees', 'residence_province')) {
                $table->string('residence_province', 50)->nullable()->change()->comment('经常居住地（省）');
            }
            if (Schema::hasColumn('employees', 'residence_city')) {
                $table->string('residence_city', 50)->nullable()->change()->comment('经常居住地（市）');
            }
            if (Schema::hasColumn('employees', 'residence_district')) {
                $table->string('residence_district', 50)->nullable()->change()->comment('经常居住地（区县）');
            }
            if (Schema::hasColumn('employees', 'contact_province')) {
                $table->string('contact_province', 50)->nullable()->change()->comment('联系地址（省）');
            }
            if (Schema::hasColumn('employees', 'contact_city')) {
                $table->string('contact_city', 50)->nullable()->change()->comment('联系地址（市）');
            }
            if (Schema::hasColumn('employees', 'contact_district')) {
                $table->string('contact_district', 50)->nullable()->change()->comment('联系地址（区县）');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 回滚时不需要特殊处理，保持字段不变
        });
    }
};
