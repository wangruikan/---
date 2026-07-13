<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_contracts', 'contract_template_id')) {
                $table->unsignedBigInteger('contract_template_id')
                    ->nullable()
                    ->after('contract_type')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('employee_contracts', 'contract_template_id')) {
                $table->dropIndex(['contract_template_id']);
                $table->dropColumn('contract_template_id');
            }
        });
    }
};
