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
        Schema::create('travel_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_set_id')->constrained()->onDelete('cascade'); // 账套ID
            $table->string('department'); // 所在部门/项目
            $table->date('apply_date')->nullable(); // 申请日期
            $table->string('applicant'); // 申请人
            $table->string('destination'); // 出差地
            $table->text('reason'); // 出差事由
            $table->dateTime('start_time'); // 起始时间
            $table->dateTime('end_time'); // 结束时间
            $table->integer('days')->default(0); // 计划天数
            $table->decimal('advance_amount', 10, 2)->default(0); // 预支金额
            $table->date('payment_date')->nullable(); // 付款日期
            $table->text('remarks')->nullable(); // 备注
            $table->string('status')->default('pending'); // 状态：pending待审批, approved已通过, rejected已拒绝
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // 创建人
            $table->foreignId('approval_flow_id')->nullable()->constrained('approval_instances')->onDelete('set null'); // 审批流程ID
            $table->timestamps();
            $table->softDeletes(); // 软删除
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_applications');
    }
};
