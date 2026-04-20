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
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->comment('员工ID');
            $table->unsignedBigInteger('account_set_id')->nullable()->comment('账套ID');
            $table->enum('contract_type', ['labor', 'termination', 'retirement'])->comment('合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议');
            $table->string('contract_file', 500)->nullable()->comment('合同文件路径');
            $table->string('original_filename', 255)->nullable()->comment('原始文件名');
            $table->enum('status', ['draft', 'pending_sign', 'employee_signed', 'completed', 'rejected'])->default('draft')->comment('状态：draft-草稿，pending_sign-待签署（乙方签署中），employee_signed-乙方已签署，completed-已完成，rejected-已拒绝');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->timestamp('uploaded_at')->nullable()->comment('上传时间');
            $table->timestamp('employee_signed_at')->nullable()->comment('员工签署时间');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            // 外键约束
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('account_set_id')->references('id')->on('account_sets')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // 索引
            $table->index('employee_id');
            $table->index('account_set_id');
            $table->index('contract_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};

