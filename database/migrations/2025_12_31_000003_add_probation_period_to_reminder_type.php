<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 修改 contract_reminders 表的 reminder_type 字段，添加 probation_period
        DB::statement("ALTER TABLE `contract_reminders` MODIFY COLUMN `reminder_type` ENUM('labor_contract', 'termination_agreement', 'retirement_agreement', 'probation_period') NOT NULL COMMENT '提醒类型'");
    }

    public function down(): void
    {
        // 回滚时删除 probation_period 选项
        DB::statement("ALTER TABLE `contract_reminders` MODIFY COLUMN `reminder_type` ENUM('labor_contract', 'termination_agreement', 'retirement_agreement') NOT NULL COMMENT '提醒类型'");
    }
};
