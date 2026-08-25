<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'insurance_decrease_months')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->text('insurance_decrease_months')
                    ->nullable()
                    ->after('resignation_date')
                    ->comment('离职退休各险种减员月份(JSON)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'insurance_decrease_months')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('insurance_decrease_months');
            });
        }
    }
};
