<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('to_project_id')->constrained('projects')->cascadeOnDelete();
            $table->dateTime('transfer_date');
            $table->string('reason')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('account_set_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_transfers');
    }
};
