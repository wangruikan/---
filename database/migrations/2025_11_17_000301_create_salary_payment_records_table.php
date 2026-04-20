<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_id')->constrained('salaries')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('month'); // 工资月份，格式：2025-01
            
            // 发工资表字段
            $table->string('bank_account')->nullable(); // 账号
            $table->string('bank_account_holder')->nullable(); // 户名
            $table->decimal('amount', 12, 2)->default(0); // 金额（实发工资）
            $table->string('bank_name')->nullable(); // 开户行
            $table->string('bank_province')->nullable(); // 开户地
            $table->string('remittance_remark')->nullable(); // 汇款备注
            
            // 元数据
            $table->unsignedBigInteger('account_set_id')->nullable();
            $table->timestamps();
            
            // 索引
            $table->index(['project_id', 'month']);
            $table->index(['employee_id', 'month']);
            $table->index(['account_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payment_records');
    }
};
