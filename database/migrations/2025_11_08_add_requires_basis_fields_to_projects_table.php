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
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('requires_salary_basis')->default(false)->after('requires_attendance')->comment('是否需要上传工资依据');
            $table->boolean('requires_attendance_basis')->default(false)->after('requires_salary_basis')->comment('是否需要上传考勤依据');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['requires_salary_basis', 'requires_attendance_basis']);
        });
    }
};

