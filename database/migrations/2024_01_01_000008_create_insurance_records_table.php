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
        Schema::create('insurance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->enum('insurance_type', ['social_security', 'housing_fund', 'work_injury', 'liability_insurance', 'accident_insurance', 'employer_insurance']);
            $table->enum('action', ['add', 'remove', 'replace']);
            $table->date('effective_date');
            $table->date('completion_date')->nullable();
            $table->enum('status', ['pending', 'completed', 'overdue'])->default('pending');
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable(); // 参保证明等附件
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_records');
    }
};
