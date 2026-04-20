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
        Schema::create('employee_registration_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->unsignedBigInteger('account_set_id')->nullable();
            
            // 头部信息
            $table->date('fill_date')->nullable()->comment('填表日期');
            $table->string('entry_position', 100)->nullable()->comment('入职职位');
            $table->date('entry_date')->nullable()->comment('入职日期');
            $table->string('department', 100)->nullable()->comment('部门');
            $table->string('job_title', 100)->nullable()->comment('职务');
            $table->string('housing_fund_account', 50)->nullable()->comment('公积金账户');
            $table->string('bank_account', 50)->nullable()->comment('银行账号');
            $table->string('bank_name', 100)->nullable()->comment('开户支行名称');
            
            // 一、个人资料
            $table->string('name', 50)->nullable()->comment('姓名');
            $table->string('english_name', 50)->nullable()->comment('英文名');
            $table->string('gender', 10)->nullable()->comment('性别');
            $table->string('height', 20)->nullable()->comment('身高');
            $table->date('birth_date')->nullable()->comment('出生日期');
            $table->string('political_status', 50)->nullable()->comment('政治面貌');
            $table->string('education_level', 50)->nullable()->comment('文化程度');
            $table->string('native_place', 100)->nullable()->comment('籍贯');
            $table->string('marital_status', 20)->nullable()->comment('婚姻状况');
            $table->string('has_children', 20)->nullable()->comment('是否有子女');
            $table->string('id_number', 20)->nullable()->comment('身份证/护照');
            $table->string('household_type', 20)->nullable()->comment('户口状态');
            $table->string('current_address', 200)->nullable()->comment('现居住地址');
            $table->string('postal_code', 10)->nullable()->comment('邮编');
            $table->string('household_address', 200)->nullable()->comment('户口地址');
            $table->string('contact_phone', 20)->nullable()->comment('联系电话');
            $table->string('document_address', 200)->nullable()->comment('文书送达地址');
            $table->string('disability_level', 20)->nullable()->comment('残疾证等级');
            
            // 二、个人技能
            $table->json('language_skills')->nullable()->comment('语言技能');
            $table->json('engineering_skills')->nullable()->comment('工程技能');
            $table->string('professional_title', 50)->nullable()->comment('职称');
            $table->json('hobbies')->nullable()->comment('兴趣爱好');
            $table->text('other_skills')->nullable()->comment('其他技能');
            
            // 三、教育情况（JSON数组）
            $table->json('education_history')->nullable()->comment('教育情况');
            
            // 四、工作履历（JSON数组）
            $table->json('work_history')->nullable()->comment('工作履历');
            $table->string('reference_company', 100)->nullable()->comment('前单位名称');
            $table->string('reference_contact', 100)->nullable()->comment('前单位联系人职位/电话');
            
            // 五、奖罚情况
            $table->text('rewards_punishments')->nullable()->comment('奖罚情况');
            
            // 六、家庭情况（JSON数组）
            $table->json('family_members')->nullable()->comment('家庭成员');
            
            // 七、紧急联系方式
            $table->string('emergency_contact1_name', 50)->nullable()->comment('第一联系人姓名');
            $table->string('emergency_contact1_relation', 20)->nullable()->comment('第一联系人关系');
            $table->string('emergency_contact1_phone', 20)->nullable()->comment('第一联系人电话');
            $table->string('emergency_contact2_name', 50)->nullable()->comment('第二联系人姓名');
            $table->string('emergency_contact2_relation', 20)->nullable()->comment('第二联系人关系');
            $table->string('emergency_contact2_phone', 20)->nullable()->comment('第二联系人电话');
            
            // 八、其他情况
            $table->string('mental_illness', 10)->nullable()->comment('精神病');
            $table->text('mental_illness_detail')->nullable()->comment('精神病详情');
            $table->string('other_illness', 10)->nullable()->comment('其他疾病');
            $table->text('other_illness_detail')->nullable()->comment('其他疾病详情');
            $table->string('hospitalized_recently', 10)->nullable()->comment('最近6个月住院记录');
            $table->text('hospitalized_reason')->nullable()->comment('住院病因');
            $table->string('criminal_record', 10)->nullable()->comment('违法犯罪记录');
            $table->string('criminal_record_time', 50)->nullable()->comment('违法犯罪时间');
            $table->json('employment_documents')->nullable()->comment('就业证件');
            
            // 九、其他需要说明的情况
            $table->text('remarks')->nullable()->comment('备注');
            
            // 十、其他需要核实的情况
            $table->string('is_pregnant', 10)->nullable()->comment('是否怀孕');
            $table->text('pregnant_detail')->nullable()->comment('怀孕详情');
            $table->string('accept_overtime', 10)->nullable()->comment('是否接受加班出差');
            $table->string('need_accommodation', 10)->nullable()->comment('是否需要住宿');
            $table->text('accommodation_detail')->nullable()->comment('住宿详情');
            $table->string('has_driving_license', 10)->nullable()->comment('是否有驾照');
            $table->text('driving_license_detail')->nullable()->comment('驾照详情');
            
            // 签名
            $table->string('signature', 255)->nullable()->comment('签名图片路径');
            $table->date('signature_date')->nullable()->comment('签名日期');
            
            $table->timestamps();
            
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_registration_forms');
    }
};
