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
            // 修改education字段类型，确保能存储中文字符
            if (Schema::hasColumn('employees', 'education')) {
                $table->string('education', 50)->nullable()->change()->comment('学历');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 回滚时不需要特殊处理，保持字段不变
        });
    }
};
