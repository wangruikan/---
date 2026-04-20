<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            // 发票审批相关字段
            $table->string('invoice_status')->nullable()->after('status')->comment('发票状态: pending_invoice(待上传发票), invoice_uploaded(发票已上传), invoice_approved(发票审批通过)');
            $table->unsignedBigInteger('invoice_approval_instance_id')->nullable()->after('approval_instance_id')->comment('发票审批流程实例ID');
            $table->timestamp('invoice_uploaded_at')->nullable()->comment('发票上传时间');
            $table->unsignedBigInteger('invoice_uploaded_by')->nullable()->comment('发票上传人');
            
            // 外键
            $table->foreign('invoice_approval_instance_id')->references('id')->on('approval_instances')->onDelete('set null');
            $table->foreign('invoice_uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
        
        // 添加发票附件表（用于存储二次审批的发票附件）
        Schema::create('payment_request_invoice_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_request_id');
            $table->string('filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            
            $table->foreign('payment_request_id')->references('id')->on('payment_requests')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropForeign(['invoice_approval_instance_id']);
            $table->dropForeign(['invoice_uploaded_by']);
            $table->dropColumn(['invoice_status', 'invoice_approval_instance_id', 'invoice_uploaded_at', 'invoice_uploaded_by']);
        });
        
        Schema::dropIfExists('payment_request_invoice_attachments');
    }
};
