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
        // 参保明细表
        Schema::create('insurance_change_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_change_id')->constrained()->onDelete('cascade')->comment('参保增减ID');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade')->comment('员工ID');
            $table->foreignId('project_id')->constrained()->onDelete('cascade')->comment('项目ID');
            $table->foreignId('account_set_id')->constrained()->onDelete('cascade')->comment('账套ID');
            
            // 保险类型
            $table->enum('insurance_type', ['social_security', 'medical_insurance', 'housing_fund', 'other_insurance'])->comment('保险类型');
            $table->string('insurance_name')->comment('保险名称');
            $table->string('region_name')->nullable()->comment('地区名称');
            
            // 参保参数
            $table->decimal('base_amount', 10, 2)->default(0)->comment('基数');
            $table->decimal('employee_ratio', 8, 4)->default(0)->comment('员工比例');
            $table->decimal('company_ratio', 8, 4)->default(0)->comment('公司比例');
            $table->decimal('employee_amount', 10, 2)->default(0)->comment('员工缴纳金额');
            $table->decimal('company_amount', 10, 2)->default(0)->comment('公司缴纳金额');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('总缴纳金额');
            
            // 状态
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamp('effective_date')->nullable()->comment('生效日期');
            $table->timestamp('expiry_date')->nullable()->comment('失效日期');
            
            $table->timestamps();
            
            // 索引
            $table->index(['insurance_change_id']);
            $table->index(['employee_id', 'project_id']);
            $table->index(['insurance_type']);
            $table->index(['account_set_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_change_details');
    }
};
