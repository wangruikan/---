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
            // 修改 salary_payment_date 字段从 date 类型改为 integer（存储1-31的数字）
            $table->integer('salary_payment_date')->nullable()->change()->comment('工资发放日期（每月几号，1-31）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 恢复为 date 类型
            $table->date('salary_payment_date')->nullable()->change()->comment('工资发放日期');
        });
    }
};

