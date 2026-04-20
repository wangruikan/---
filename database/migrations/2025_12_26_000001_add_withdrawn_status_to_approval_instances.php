<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 修改 approval_instances 表的 status 字段，添加 withdrawn 状态
        DB::statement("ALTER TABLE `approval_instances` MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending'");
        
        // 修改 approval_records 表的 status 字段，添加 withdrawn 状态
        DB::statement("ALTER TABLE `approval_records` MODIFY COLUMN `status` ENUM('pending', 'waiting', 'approved', 'rejected', 'returned', 'withdrawn') NOT NULL DEFAULT 'waiting'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚时移除 withdrawn 状态
        DB::statement("ALTER TABLE `approval_instances` MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
        
        DB::statement("ALTER TABLE `approval_records` MODIFY COLUMN `status` ENUM('pending', 'waiting', 'approved', 'rejected', 'returned') NOT NULL DEFAULT 'waiting'");
    }
};
