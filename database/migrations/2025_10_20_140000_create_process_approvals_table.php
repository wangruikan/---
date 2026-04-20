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
        // 流程审批表
        Schema::create('process_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_set_id')->constrained('account_sets')->onDelete('cascade')->comment('账套ID');
            $table->foreignId('initiator_id')->constrained('users')->onDelete('cascade')->comment('发起人ID');
            $table->string('title')->comment('流程标题');
            $table->string('month', 7)->comment('审批月份 (YYYY-MM)');
            $table->text('description')->nullable()->comment('流程描述');
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft')->comment('流程状态: draft-草稿, pending-待审批, approved-已通过, rejected-已驳回');
            $table->foreignId('current_approver_id')->nullable()->constrained('users')->onDelete('set null')->comment('当前审批人ID');
            $table->dateTime('approved_at')->nullable()->comment('审批通过时间');
            $table->dateTime('rejected_at')->nullable()->comment('审批驳回时间');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('account_set_id');
            $table->index('initiator_id');
            $table->index('status');
            $table->index('month');
        });

        // 流程附件表
        Schema::create('process_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_approval_id')->constrained('process_approvals')->onDelete('cascade')->comment('流程审批ID');
            $table->string('filename')->comment('文件名');
            $table->string('file_path')->comment('文件存储路径');
            $table->bigInteger('file_size')->comment('文件大小 (bytes)');
            $table->string('mime_type')->nullable()->comment('MIME类型');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade')->comment('上传人ID');
            $table->timestamps();
            
            $table->index('process_approval_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_attachments');
        Schema::dropIfExists('process_approvals');
    }
};

