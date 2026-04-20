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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique();
            $table->enum('status', ['active', 'inactive', 'completed'])->default('active');
            $table->string('social_security_location')->nullable(); // 参保地
            $table->json('insurance_types')->nullable(); // 参保项目
            $table->date('salary_payment_date')->nullable(); // 工资发放日期
            $table->boolean('requires_attendance')->default(true); // 是否需要考勤表
            $table->json('delivery_requirements')->nullable(); // 交付资料要求
            $table->enum('delivery_frequency', ['monthly', 'quarterly'])->default('monthly'); // 交付频率
            $table->enum('delivery_method', ['express', 'electronic'])->default('electronic'); // 交付方式
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
