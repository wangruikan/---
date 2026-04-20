<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 先将现有数据转换为有效的枚举值
        DB::statement("UPDATE payment_requests SET invoice_status = NULL WHERE invoice_status NOT IN ('pending_invoice', 'invoice_uploaded', 'invoice_in_approval', 'invoice_approved')");
        
        // 修改字段为枚举类型
        DB::statement("ALTER TABLE payment_requests MODIFY COLUMN invoice_status ENUM('pending_invoice', 'invoice_uploaded', 'invoice_in_approval', 'invoice_approved') NULL DEFAULT NULL COMMENT '发票状态：pending_invoice=待上传发票, invoice_uploaded=发票已上传, invoice_in_approval=发票审批中, invoice_approved=发票审批完成'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payment_requests MODIFY COLUMN invoice_status VARCHAR(50) NULL DEFAULT NULL");
    }
};
