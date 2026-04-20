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
        Schema::create('contract_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_set_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('reminder_type', ['labor_contract', 'termination_agreement', 'retirement_agreement']);
            $table->string('employee_name');
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->date('retirement_date')->nullable();
            $table->enum('status', ['pending', 'resolved', 'escalated'])->default('pending');
            $table->text('description');
            $table->unsignedBigInteger('handler_id')->default(0);
            $table->string('handler_name')->default('业务人员');
            $table->date('reminder_date'); // 提醒日期（月末）
            $table->date('escalation_date')->nullable(); // 升级日期（15号）
            $table->boolean('is_escalated')->default(false); // 是否已升级为考核
            $table->unsignedBigInteger('assessment_record_id')->nullable(); // 关联的考核记录ID
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('account_set_id')->references('id')->on('account_sets')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('assessment_record_id')->references('id')->on('assessment_records')->onDelete('set null');
            
            $table->index(['account_set_id', 'reminder_date']);
            $table->index(['status', 'is_escalated']);
            $table->index(['employee_id', 'reminder_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_reminders');
    }
};
