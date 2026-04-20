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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->enum('type', ['full_amount', 'difference']); // 全额或差额
            $table->json('deduction_details')->nullable(); // 扣除明细
            $table->enum('status', ['draft', 'submitted', 'approved', 'issued', 'rejected'])->default('draft');
            $table->foreignId('applicant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
