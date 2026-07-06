<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employee_contracts', 'termination_reason')) {
            Schema::table('employee_contracts', function (Blueprint $table) {
                $table->string('termination_reason', 255)
                    ->nullable()
                    ->after('contract_type')
                    ->comment('离职/退休原因');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_contracts', 'termination_reason')) {
            Schema::table('employee_contracts', function (Blueprint $table) {
                $table->dropColumn('termination_reason');
            });
        }
    }
};
