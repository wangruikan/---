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
        Schema::table('employees', function (Blueprint $table) {
            // 一、基础身份信息
            $table->string('country_region')->nullable()->comment('国籍(地区)');
            $table->string('chinese_name')->nullable()->comment('中文名');
            $table->string('birth_country')->nullable()->comment('出生国家(地区)');
            $table->string('other_id_type')->nullable()->comment('其他证件类型');
            $table->string('other_id_number')->nullable()->comment('其他证件号码');
            
            // 二、从业任职信息
            $table->string('personnel_status')->nullable()->comment('人员状态');
            $table->string('employment_type')->nullable()->comment('任职受雇从业类型');
            $table->date('employment_date')->nullable()->comment('任职受雇从业日期');
            $table->date('resignation_date')->nullable()->comment('离职日期');
            $table->string('annual_employment_status')->nullable()->comment('入职年度就业情形');
            $table->string('job_title')->nullable()->comment('职务');
            
            // 三、特殊身份信息
            $table->boolean('is_disabled')->default(false)->comment('是否残疾');
            $table->string('disability_cert_type')->nullable()->comment('残疾证件类型');
            $table->string('disability_cert_number')->nullable()->comment('残疾证号');
            $table->boolean('is_martyr_family')->default(false)->comment('是否烈属');
            $table->string('martyr_family_cert_number')->nullable()->comment('烈属证号');
            $table->boolean('is_elderly_alone')->default(false)->comment('是否孤老');
            
            // 四、涉税与投资信息
            $table->string('tax_matter')->nullable()->comment('涉税事由');
            $table->boolean('deduct_expense')->default(true)->comment('是否扣除减除费用');
            $table->decimal('personal_investment_amount', 15, 2)->nullable()->comment('个人投资额');
            $table->decimal('personal_investment_ratio', 5, 2)->nullable()->comment('个人投资比例(%)');
            
            // 五、出入境信息
            $table->date('first_entry_date')->nullable()->comment('首次入境时间');
            $table->date('expected_departure_date')->nullable()->comment('预计离境时间');
            
            // 六、联系方式与银行信息
            $table->string('email_address')->nullable()->comment('电子邮箱');
            $table->string('bank_province')->nullable()->comment('开户行省份');
            
            // 七、备注说明信息
            $table->text('other_notes')->nullable()->comment('其他情况说明');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'country_region', 'chinese_name', 'birth_country', 'other_id_type', 'other_id_number',
                'personnel_status', 'employment_type', 'employment_date', 'resignation_date', 
                'annual_employment_status', 'job_title',
                'is_disabled', 'disability_cert_type', 'disability_cert_number', 
                'is_martyr_family', 'martyr_family_cert_number', 'is_elderly_alone',
                'tax_matter', 'deduct_expense', 'personal_investment_amount', 'personal_investment_ratio',
                'first_entry_date', 'expected_departure_date',
                'email_address', 'bank_province',
                'other_notes'
            ]);
        });
    }
};
