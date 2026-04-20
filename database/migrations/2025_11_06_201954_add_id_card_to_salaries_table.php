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
        Schema::table('salaries', function (Blueprint $table) {
            $table->string('id_card', 18)->nullable()->after('employee_id')->comment('身份证号');
            $table->string('employee_name')->nullable()->after('id_card')->comment('员工姓名');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn(['id_card', 'employee_name']);
        });
    }
};
