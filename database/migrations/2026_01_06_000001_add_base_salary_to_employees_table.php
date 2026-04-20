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
            // 检查字段是否已存在，避免重复添加
            if (!Schema::hasColumn('employees', 'basic_salary')) {
                $table->decimal('basic_salary', 10, 2)->nullable()->after('bank_branch')->comment('基础工资');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'basic_salary')) {
                $table->dropColumn('basic_salary');
            }
        });
    }
};
