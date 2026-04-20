<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 为付款申请表添加关联项目ID字段
     */
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->json('project_ids')->nullable()->after('approval_instance_id')->comment('关联项目ID列表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropColumn('project_ids');
        });
    }
};
