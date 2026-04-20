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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('id_number')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->date('birth_date');
            $table->string('nationality')->default('中国');
            $table->string('marital_status')->nullable();
            $table->string('education')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            
            // 入离职及合同信息
            $table->date('hire_date');
            $table->date('contract_start_date');
            $table->date('contract_end_date')->nullable();
            $table->enum('contract_status', ['active', 'expired', 'terminated'])->default('active');
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();
            $table->boolean('is_retired')->default(false);
            $table->date('retirement_date')->nullable();
            
            // 银行信息
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_branch')->nullable();
            
            // 社保公积金信息
            $table->decimal('social_security_base', 10, 2)->nullable();
            $table->decimal('housing_fund_base', 10, 2)->nullable();
            $table->boolean('has_social_security')->default(false);
            $table->boolean('has_housing_fund')->default(false);
            $table->boolean('has_work_injury')->default(false);
            $table->boolean('has_liability_insurance')->default(false);
            $table->boolean('has_accident_insurance')->default(false);
            $table->boolean('has_employer_insurance')->default(false);
            
            // 专项附加扣除
            $table->decimal('special_deduction', 10, 2)->default(5000);
            $table->boolean('is_annual_deduction')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
