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
        // 参保增减主表
        Schema::create('insurance_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade')->comment('员工ID');
            $table->foreignId('project_id')->constrained()->onDelete('cascade')->comment('项目ID');
            $table->foreignId('account_set_id')->constrained()->onDelete('cascade')->comment('账套ID');
            
            // 参保地区信息
            $table->foreignId('social_security_region_id')->nullable()->constrained('social_security_regions')->onDelete('set null')->comment('社保地区ID');
            $table->foreignId('medical_insurance_region_id')->nullable()->constrained('medical_insurance_regions')->onDelete('set null')->comment('医保地区ID');
            $table->foreignId('housing_fund_id')->nullable()->constrained('housing_funds')->onDelete('set null')->comment('公积金ID');
            
            // 参保参数（TEXT格式存储）
            $table->text('social_security_types')->nullable()->comment('社保类型参数');
            $table->text('medical_insurance_types')->nullable()->comment('医保类型参数');
            $table->text('housing_fund_params')->nullable()->comment('公积金参数');
            $table->text('other_insurance_policies')->nullable()->comment('其他保险保单');
            
            // 状态管理
            $table->enum('status', ['pending', 'processing', 'submitted', 'completed'])->default('pending')->comment('状态：待处理、处理中、待提交汇总审批、已完成');
            $table->text('attachment_path')->nullable()->comment('附件路径');
            $table->timestamp('attachment_uploaded_at')->nullable()->comment('附件上传时间');
            $table->timestamp('processed_at')->nullable()->comment('处理完成时间');
            $table->timestamp('submitted_at')->nullable()->comment('提交汇总审批时间');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            
            // 备注信息
            $table->text('notes')->nullable()->comment('备注');
            $table->foreignId('created_by')->constrained('users')->onDelete('set null')->comment('创建人');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null')->comment('处理人');
            
            $table->timestamps();
            
            // 索引
            $table->index(['employee_id', 'project_id']);
            $table->index(['status']);
            $table->index(['account_set_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_changes');
    }
};
