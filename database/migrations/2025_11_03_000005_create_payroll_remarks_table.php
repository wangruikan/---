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
        Schema::create('payroll_remarks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_set_id')->comment('账套ID');
            $table->string('project_name', 100)->comment('项目名称');
            $table->integer('year')->comment('年份');
            $table->integer('month')->comment('月份');
            $table->text('remark')->nullable()->comment('工资表备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新人');
            $table->timestamps();
            
            // 索引
            $table->index(['account_set_id', 'project_name', 'year', 'month'], 'idx_payroll_remark');
            $table->unique(['account_set_id', 'project_name', 'year', 'month'], 'unique_payroll_remark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_remarks');
    }
};

