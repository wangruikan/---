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
        Schema::create('personnel_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_set_id')->constrained()->onDelete('cascade'); // 账套ID
            $table->foreignId('project_id')->constrained()->onDelete('cascade'); // 项目ID
            $table->string('month'); // 工资期间（YYYY-MM）
            $table->enum('change_type', ['add', 'remove']); // 变动类型：add新增，remove减少
            $table->json('personnel_list'); // 人员列表 [{"id_card":"xxx", "name":"xxx"}]
            $table->text('remark')->nullable(); // 备注
            $table->string('status')->default('pending'); // 状态：pending待审批, in_approval审批中, approved已通过, rejected已拒绝
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // 创建人
            $table->foreignId('approval_flow_id')->nullable()->constrained('approval_instances')->onDelete('set null'); // 审批流程ID
            $table->timestamps();
            $table->softDeletes(); // 软删除
            
            // 唯一索引：同一个项目、月份、变动类型只能有一条未审批的记录（允许覆盖）
            $table->unique(['project_id', 'month', 'change_type', 'deleted_at'], 'unique_project_month_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personnel_change_requests');
    }
};
