<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'social_security_enrollment_month')) {
                $table->string('social_security_enrollment_month', 7)->nullable()->after('social_security_base')->comment('社保参保月份 YYYY-MM');
            }
            if (!Schema::hasColumn('employees', 'housing_fund_enrollment_month')) {
                $table->string('housing_fund_enrollment_month', 7)->nullable()->after('housing_fund_base')->comment('公积金参保月份 YYYY-MM');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'social_security_enrollment_month')) {
                $table->dropColumn('social_security_enrollment_month');
            }
            if (Schema::hasColumn('employees', 'housing_fund_enrollment_month')) {
                $table->dropColumn('housing_fund_enrollment_month');
            }
        });
    }
};
