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
        // 转换 insurance_changes 表的JSON字段为TEXT
        Schema::table('insurance_changes', function (Blueprint $table) {
            $table->text('social_security_types')->nullable()->change();
            $table->text('medical_insurance_types')->nullable()->change();
            $table->text('housing_fund_params')->nullable()->change();
            $table->text('large_medical_insurance_config')->nullable()->change();
            $table->text('other_insurance_policies')->nullable()->change();
            $table->text('change_details')->nullable()->change();
            $table->text('used_quotas')->nullable()->change();
        });

        // 转换 insurance_personnel 表的JSON字段为TEXT
        Schema::table('insurance_personnel', function (Blueprint $table) {
            $table->text('social_security_types')->nullable()->change();
            $table->text('medical_insurance_types')->nullable()->change();
            $table->text('housing_fund_params')->nullable()->change();
            $table->text('large_medical_insurance_config')->nullable()->change();
            $table->text('other_insurance_policy_versions')->nullable()->change();
        });

        // 转换 insurance_detail_records 表的JSON字段为TEXT
        Schema::table('insurance_detail_records', function (Blueprint $table) {
            $table->text('social_security_types')->nullable()->change();
            $table->text('medical_insurance_types')->nullable()->change();
            $table->text('housing_fund_params')->nullable()->change();
            $table->text('large_medical_insurance_config')->nullable()->change();
            $table->text('other_insurance_policies')->nullable()->change();
        });

        // 如果还有其他表使用JSON字段，也可以在这里添加
        // 例如：payment_applications 表可能也有JSON字段
        if (Schema::hasTable('payment_applications')) {
            Schema::table('payment_applications', function (Blueprint $table) {
                // 检查是否有JSON字段需要转换
                if (Schema::hasColumn('payment_applications', 'attachments')) {
                    $table->text('attachments')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 如果需要回滚，可以改回JSON类型
        // 但通常不建议回滚，因为TEXT类型更兼容
        
        Schema::table('insurance_changes', function (Blueprint $table) {
            $table->json('social_security_types')->nullable()->change();
            $table->json('medical_insurance_types')->nullable()->change();
            $table->json('housing_fund_params')->nullable()->change();
            $table->json('large_medical_insurance_config')->nullable()->change();
            $table->json('other_insurance_policies')->nullable()->change();
            $table->json('change_details')->nullable()->change();
            $table->json('used_quotas')->nullable()->change();
        });

        Schema::table('insurance_personnel', function (Blueprint $table) {
            $table->json('social_security_types')->nullable()->change();
            $table->json('medical_insurance_types')->nullable()->change();
            $table->json('housing_fund_params')->nullable()->change();
            $table->json('large_medical_insurance_config')->nullable()->change();
            $table->json('other_insurance_policy_versions')->nullable()->change();
        });

        Schema::table('insurance_detail_records', function (Blueprint $table) {
            $table->json('social_security_types')->nullable()->change();
            $table->json('medical_insurance_types')->nullable()->change();
            $table->json('housing_fund_params')->nullable()->change();
            $table->json('large_medical_insurance_config')->nullable()->change();
            $table->json('other_insurance_policies')->nullable()->change();
        });
    }
};
