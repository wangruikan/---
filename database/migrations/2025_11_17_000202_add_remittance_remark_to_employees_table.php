<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'remittance_remark')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('remittance_remark')->nullable()->after('bank_branch');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'remittance_remark')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('remittance_remark');
            });
        }
    }
};
