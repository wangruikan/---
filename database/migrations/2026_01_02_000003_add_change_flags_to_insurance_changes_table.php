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
        Schema::table('insurance_changes', function (Blueprint $table) {
            // 添加变更标记字段
            $table->boolean('social_security_changed')->default(false)->after('social_security_types')->comment('社保是否发生变更');
            $table->boolean('medical_insurance_changed')->default(false)->after('medical_insurance_types')->comment('医保是否发生变更');
            $table->boolean('housing_fund_changed')->default(false)->after('housing_fund_params')->comment('公积金是否发生变更');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_changes', function (Blueprint $table) {
            $table->dropColumn(['social_security_changed', 'medical_insurance_changed', 'housing_fund_changed']);
        });
    }
};
