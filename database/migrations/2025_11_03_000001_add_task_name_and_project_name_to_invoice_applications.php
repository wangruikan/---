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
            // 检查字段是否存在，如果不存在才添加
            if (!Schema::hasColumn('invoice_applications', 'task_name')) {
                $table->string('task_name', 100)->nullable()->after('application_no')->comment('任务名称');
            }
            if (!Schema::hasColumn('invoice_applications', 'project_name')) {
                $table->string('project_name', 100)->nullable()->after('month')->comment('项目名称');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_applications', function (Blueprint $table) {
            $table->dropColumn(['task_name', 'project_name']);
        });
    }
};

