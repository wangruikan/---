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
        // 为社保地区表添加单位字段
        if (!Schema::hasColumn('social_security_regions', 'company')) {
            Schema::table('social_security_regions', function (Blueprint $table) {
                $table->string('company', 200)->nullable()->after('code')->comment('单位/公司名称');
            });
        }

        // 为医保地区表添加单位字段
        if (!Schema::hasColumn('medical_insurance_regions', 'company')) {
            Schema::table('medical_insurance_regions', function (Blueprint $table) {
                $table->string('company', 200)->nullable()->after('code')->comment('单位/公司名称');
            });
        }

        // 为公积金地区表添加单位字段
        if (!Schema::hasColumn('housing_fund_regions', 'company_name')) {
            Schema::table('housing_fund_regions', function (Blueprint $table) {
                $table->string('company_name', 200)->nullable()->after('account_number')->comment('单位/公司名称');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_security_regions', function (Blueprint $table) {
            if (Schema::hasColumn('social_security_regions', 'company')) {
                $table->dropColumn('company');
            }
        });

        Schema::table('medical_insurance_regions', function (Blueprint $table) {
            if (Schema::hasColumn('medical_insurance_regions', 'company')) {
                $table->dropColumn('company');
            }
        });

        Schema::table('housing_fund_regions', function (Blueprint $table) {
            if (Schema::hasColumn('housing_fund_regions', 'company_name')) {
                $table->dropColumn('company_name');
            }
        });
    }
};
