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
        // 为社保地区表添加编号字段
        Schema::table('social_security_regions', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->after('name')->comment('社保编号');
        });

        // 为医保地区表添加编号字段
        Schema::table('medical_insurance_regions', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->after('name')->comment('医保编号');
        });

        // 为公积金地区表添加账号字段
        Schema::table('housing_fund_regions', function (Blueprint $table) {
            $table->string('account_number', 100)->nullable()->after('region_name')->comment('公积金账号');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_security_regions', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::table('medical_insurance_regions', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::table('housing_fund_regions', function (Blueprint $table) {
            $table->dropColumn('account_number');
        });
    }
};

