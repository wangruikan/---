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
        Schema::create('reimbursements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_set_id')->comment('账套ID');
            $table->string('applicant')->comment('报销人');
            $table->decimal('amount', 10, 2)->comment('报销金额');
            $table->text('reason')->comment('报销事由');
            $table->text('remarks')->nullable()->comment('备注');
            $table->string('status')->default('pending')->comment('状态: pending-待审批, approved-已通过, rejected-已拒绝');
            $table->unsignedBigInteger('created_by')->comment('创建人ID');
            $table->unsignedBigInteger('approval_flow_id')->nullable()->comment('审批流程ID');
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_set_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('reimbursement_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reimbursement_id')->comment('报销申请ID');
            $table->string('file_name')->comment('文件名');
            $table->string('file_path')->comment('文件路径');
            $table->string('file_type')->nullable()->comment('文件类型');
            $table->bigInteger('file_size')->nullable()->comment('文件大小(字节)');
            $table->timestamps();

            $table->foreign('reimbursement_id')->references('id')->on('reimbursements')->onDelete('cascade');
            $table->index('reimbursement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reimbursement_attachments');
        Schema::dropIfExists('reimbursements');
    }
};

