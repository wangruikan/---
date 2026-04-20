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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->enum('category', ['salary', 'social_security', 'commercial_insurance', 'reimbursement']);
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->json('attachments')->nullable(); // 附件列表
            $table->enum('status', ['draft', 'submitted', 'approved', 'paid', 'rejected'])->default('draft');
            $table->foreignId('applicant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_recorded')->default(false); // 是否已入账
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
