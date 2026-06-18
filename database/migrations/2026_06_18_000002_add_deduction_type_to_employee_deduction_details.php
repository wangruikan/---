<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_deduction_details', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_deduction_details', 'deduction_type')) {
                $table->string('deduction_type', 20)
                    ->default('special')
                    ->after('month')
                    ->comment('扣除类型：special=专项扣除，other=其他扣除');
                $table->index(['account_set_id', 'employee_id', 'month', 'deduction_type'], 'edd_account_employee_month_type_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_deduction_details', function (Blueprint $table) {
            if (Schema::hasColumn('employee_deduction_details', 'deduction_type')) {
                $table->dropIndex('edd_account_employee_month_type_idx');
                $table->dropColumn('deduction_type');
            }
        });
    }
};
