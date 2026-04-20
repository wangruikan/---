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
            $table->date('social_insurance_enrollment_date')->nullable()->comment('社保参保日期');
            $table->date('provident_fund_enrollment_date')->nullable()->comment('公积金参保日期');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['social_insurance_enrollment_date', 'provident_fund_enrollment_date']);
        });
    }
};
