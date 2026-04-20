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
        Schema::table('invoice_applications', function (Blueprint $table) {
            // 添加审批状态字段
            if (!Schema::hasColumn('invoice_applications', 'approval_status')) {
                $table->string('approval_status', 20)
                    ->nullable()
                    ->after('status')
                    ->comment('审批状态：pending-审批中，approved-已通过，rejected-已驳回');
            }
        });
        
        // 数据迁移：将现有的审批状态从 status 迁移到 approval_status
        DB::statement("
            UPDATE invoice_applications 
            SET approval_status = CASE 
                WHEN status = 'pending' THEN 'pending'
                WHEN status = 'approved' THEN 'approved'
                WHEN status = 'rejected' THEN 'rejected'
                ELSE NULL
            END
            WHERE status IN ('pending', 'approved', 'rejected')
        ");
        
        // 将 status 字段的审批状态值改为业务状态
        DB::statement("
            UPDATE invoice_applications 
            SET status = CASE 
                WHEN status IN ('pending', 'approved') THEN 'normal'
                WHEN status = 'rejected' THEN 'red_flushed'
                ELSE status
            END
            WHERE status IN ('pending', 'approved', 'rejected')
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_applications', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });
    }
};

