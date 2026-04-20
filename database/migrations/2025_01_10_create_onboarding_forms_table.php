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
        Schema::create('onboarding_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->comment('员工ID');
            $table->unsignedBigInteger('account_set_id')->comment('账套ID');
            
            // 登记日期
            $table->date('registration_date')->comment('登记日期');
            
            // 基本信息
            $table->string('name', 50)->comment('姓名');
            $table->enum('gender', ['male', 'female'])->comment('性别');
            $table->string('ethnicity', 50)->nullable()->comment('民族');
            $table->string('political_status', 50)->nullable()->comment('政治面貌');
            $table->string('place_of_origin', 100)->nullable()->comment('籍贯');
            $table->date('birth_date')->nullable()->comment('出生年月');
            $table->string('graduated_school', 200)->nullable()->comment('毕业学校');
            $table->date('graduation_date')->nullable()->comment('毕业时间');
            $table->string('education_level', 50)->nullable()->comment('文化程度');
            $table->string('major', 100)->nullable()->comment('所学专业');
            $table->string('degree', 50)->nullable()->comment('学位');
            $table->string('technical_title', 50)->nullable()->comment('技术职称');
            $table->string('health_status', 50)->nullable()->comment('健康状况');
            $table->integer('height')->nullable()->comment('身高(cm)');
            $table->decimal('weight', 5, 2)->nullable()->comment('体重(kg)');
            $table->string('marital_status', 20)->nullable()->comment('婚姻状况');
            $table->string('id_number', 18)->comment('身份证号码');
            $table->string('current_residence', 200)->nullable()->comment('现居住地');
            $table->string('household_registration', 200)->nullable()->comment('户口所在地');
            
            // 就业信息
            $table->string('position', 100)->nullable()->comment('岗位');
            $table->string('desired_location', 100)->nullable()->comment('求职地区');
            $table->boolean('accept_assignment')->nullable()->comment('是否服从调配');
            $table->string('contact_address', 200)->nullable()->comment('联系地址');
            $table->string('contact_phone', 20)->nullable()->comment('联系电话');
            
            // 备注
            $table->text('remarks')->nullable()->comment('备注');
            
            // 声明和签名
            $table->boolean('declaration_agreed')->default(false)->comment('是否同意声明');
            $table->string('signature', 255)->nullable()->comment('本人签名图片路径');
            
            // JSON字段存储多行数据
            $table->json('education_background')->nullable()->comment('学习简历');
            $table->json('work_experience')->nullable()->comment('工作经历');
            $table->json('family_info')->nullable()->comment('家庭情况');
            
            $table->timestamps();
            
            // 索引
            $table->index('employee_id');
            $table->index('account_set_id');
            $table->index('id_number');
            
            // 外键
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_forms');
    }
};

