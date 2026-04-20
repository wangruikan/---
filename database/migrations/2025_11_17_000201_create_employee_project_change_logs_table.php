<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_project_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('to_project_id')->constrained('projects')->cascadeOnDelete();
            $table->dateTime('changed_at');
            $table->string('reason')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('account_set_id')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_project_change_logs');
    }
};
