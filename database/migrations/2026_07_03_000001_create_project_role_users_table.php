<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_role_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_set_id')->constrained('account_sets')->onDelete('cascade');
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('role_type', 32)->comment('insurance/salary/delivery');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['project_id', 'role_type', 'user_id'], 'project_role_users_unique');
            $table->index(['account_set_id', 'role_type'], 'project_role_users_account_role_idx');
            $table->index(['account_set_id', 'user_id', 'role_type'], 'project_role_users_account_user_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_role_users');
    }
};
