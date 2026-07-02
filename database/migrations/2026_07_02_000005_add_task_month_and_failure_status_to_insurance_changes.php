<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('insurance_changes', 'task_month')) {
            Schema::table('insurance_changes', function (Blueprint $table) {
                $table->string('task_month', 7)->nullable()->after('account_set_id')->comment('任务月份 YYYY-MM');
            });
        }

        DB::statement("UPDATE insurance_changes SET task_month = DATE_FORMAT(created_at, '%Y-%m') WHERE task_month IS NULL OR task_month = ''");
        DB::statement("ALTER TABLE insurance_changes MODIFY COLUMN task_month VARCHAR(7) NOT NULL COMMENT '任务月份 YYYY-MM'");

        $hasIndex = collect(DB::select("SHOW INDEX FROM insurance_changes WHERE Key_name = 'idx_insurance_changes_task_month'"))->isNotEmpty();
        if (!$hasIndex) {
            Schema::table('insurance_changes', function (Blueprint $table) {
                $table->index('task_month', 'idx_insurance_changes_task_month');
            });
        }

        DB::statement("ALTER TABLE insurance_changes MODIFY COLUMN status ENUM('pending','processing','submitted','completed','failed','terminated') NOT NULL DEFAULT 'pending' COMMENT '状态：待处理、处理中、待确认、成功、失败、终止'");
        DB::statement("ALTER TABLE insurance_change_items MODIFY COLUMN status ENUM('pending','submitted','completed','failed','terminated') NOT NULL DEFAULT 'pending' COMMENT '子任务状态'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE insurance_change_items MODIFY COLUMN status ENUM('pending','submitted','completed') NOT NULL DEFAULT 'pending' COMMENT '子任务状态'");
        DB::statement("ALTER TABLE insurance_changes MODIFY COLUMN status ENUM('pending','processing','submitted','completed') NOT NULL DEFAULT 'pending' COMMENT '状态：待处理、处理中、待提交汇总审批、已完成'");

        if (Schema::hasColumn('insurance_changes', 'task_month')) {
            Schema::table('insurance_changes', function (Blueprint $table) {
                $table->dropIndex('idx_insurance_changes_task_month');
                $table->dropColumn('task_month');
            });
        }
    }
};
