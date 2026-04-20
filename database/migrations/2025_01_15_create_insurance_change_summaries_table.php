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
        // 参保汇总表
        Schema::create('insurance_change_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_set_id')->constrained()->onDelete('cascade')->comment('账套ID');
            $table->string('region_name')->comment('地区名称');
            $table->enum('insurance_type', ['social_security', 'medical_insurance', 'housing_fund', 'other_insurance'])->comment('保险类型');
            $table->string('insurance_name')->comment('保险名称');
            
            // 汇总数据
            $table->integer('employee_count')->default(0)->comment('参保人数');
            $table->decimal('total_base_amount', 15, 2)->default(0)->comment('总基数');
            $table->decimal('total_employee_amount', 15, 2)->default(0)->comment('员工总缴纳金额');
            $table->decimal('total_company_amount', 15, 2)->default(0)->comment('公司总缴纳金额');
            $table->decimal('total_amount', 15, 2)->default(0)->comment('总缴纳金额');
            
            // 统计时间
            $table->date('summary_date')->comment('汇总日期');
            $table->foreignId('created_by')->constrained('users')->onDelete('set null')->comment('创建人');
            
            $table->timestamps();
            
            // 索引
            $table->index(['account_set_id', 'summary_date']);
            $table->index(['region_name', 'insurance_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_change_summaries');
    }
};
