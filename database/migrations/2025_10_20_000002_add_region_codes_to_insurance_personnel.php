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
        Schema::table('insurance_personnel', function (Blueprint $table) {
            $table->string('social_security_code', 100)->nullable()->after('social_security_region_id')->comment('社保编号');
            $table->string('medical_insurance_code', 100)->nullable()->after('medical_insurance_region_id')->comment('医保编号');
            $table->string('housing_fund_account_number', 100)->nullable()->after('housing_fund_region_id')->comment('公积金账号');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_personnel', function (Blueprint $table) {
            $table->dropColumn(['social_security_code', 'medical_insurance_code', 'housing_fund_account_number']);
        });
    }
};

