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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 审批类型：attendance_sheet, salary, payment, etc.
            $table->unsignedBigInteger('related_id'); // 关联记录ID
            $table->foreignId('applicant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approver_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected', 'returned'])->default('pending');
            $table->text('reason')->nullable();
            $table->text('approval_comment')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->string('signature')->nullable(); // 签名图片
            $table->string('seal')->nullable(); // 印章图片
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
