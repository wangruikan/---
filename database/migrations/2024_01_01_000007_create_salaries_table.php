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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('month'); // 格式：2024-01
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->decimal('allowance', 10, 2)->default(0);
            $table->decimal('overtime_pay', 10, 2)->default(0);
            $table->decimal('bonus', 10, 2)->default(0);
            $table->decimal('gross_salary', 10, 2)->default(0); // 应发工资
            $table->decimal('social_security', 10, 2)->default(0); // 社保
            $table->decimal('housing_fund', 10, 2)->default(0); // 公积金
            $table->decimal('special_deduction', 10, 2)->default(0); // 专项扣除
            $table->decimal('taxable_income', 10, 2)->default(0); // 应纳税所得额
            $table->decimal('personal_tax', 10, 2)->default(0); // 个税
            $table->decimal('actual_tax', 10, 2)->default(0); // 实际个税
            $table->decimal('net_salary', 10, 2)->default(0); // 实发工资
            $table->decimal('paid_salary', 10, 2)->default(0); // 发放工资
            $table->enum('status', ['draft', 'submitted', 'approved', 'paid', 'rejected'])->default('draft');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
