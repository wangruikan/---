<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'probation_end_date')) {
                $table->date('probation_end_date')->nullable()->after('contract_end_date')->comment('试用期结束日期');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'probation_end_date')) {
                $table->dropColumn('probation_end_date');
            }
        });
    }
};
