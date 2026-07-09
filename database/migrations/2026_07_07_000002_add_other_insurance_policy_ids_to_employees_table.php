<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'other_insurance_policy_ids')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->text('other_insurance_policy_ids')
                    ->nullable()
                    ->comment('员工选择的其他保险保单ID列表')
                    ->after('other_insurance_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'other_insurance_policy_ids')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('other_insurance_policy_ids');
            });
        }
    }
};
