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
        // 添加生效时间和待生效配置字段
        Schema::table('large_medical_insurance_configs', function (Blueprint $table) {
            $table->date('effective_date')->nullable()->after('status')->comment('生效日期');
            // 待生效的新配置值（JSON格式存储）
            $table->json('pending_changes')->nullable()->after('effective_date')->comment('待生效的配置变更');
        });

        // 创建大额医疗保险配置历史记录表
        Schema::create('large_medical_insurance_config_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id')->comment('配置ID');
            $table->unsignedBigInteger('account_set_id')->comment('账套ID');
            $table->string('region_name', 100)->comment('地区名称');
            $table->string('change_type', 50)->comment('变更类型：create=新建, update=修改, effective=生效');
            
            // 变更前的值
            $table->string('old_calculation_type', 20)->nullable()->comment('原计算方式');
            $table->string('old_base_source', 20)->nullable()->comment('原基数来源');
            $table->decimal('old_base_amount', 12, 2)->nullable()->comment('原公司基数');
            $table->decimal('old_employee_base_amount', 12, 2)->nullable()->comment('原个人基数');
            $table->decimal('old_company_ratio', 8, 4)->nullable()->comment('原公司比例');
            $table->decimal('old_employee_ratio', 8, 4)->nullable()->comment('原个人比例');
            $table->decimal('old_company_amount', 12, 2)->nullable()->comment('原公司固定金额');
            $table->decimal('old_employee_amount', 12, 2)->nullable()->comment('原个人固定金额');
            
            // 变更后的值
            $table->string('new_calculation_type', 20)->nullable()->comment('新计算方式');
            $table->string('new_base_source', 20)->nullable()->comment('新基数来源');
            $table->decimal('new_base_amount', 12, 2)->nullable()->comment('新公司基数');
            $table->decimal('new_employee_base_amount', 12, 2)->nullable()->comment('新个人基数');
            $table->decimal('new_company_ratio', 8, 4)->nullable()->comment('新公司比例');
            $table->decimal('new_employee_ratio', 8, 4)->nullable()->comment('新个人比例');
            $table->decimal('new_company_amount', 12, 2)->nullable()->comment('新公司固定金额');
            $table->decimal('new_employee_amount', 12, 2)->nullable()->comment('新个人固定金额');
            
            $table->date('effective_date')->nullable()->comment('生效日期');
            $table->unsignedBigInteger('operated_by')->nullable()->comment('操作人ID');
            $table->string('operated_by_name', 100)->nullable()->comment('操作人姓名');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('config_id');
            $table->index('account_set_id');
            $table->index('effective_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('large_medical_insurance_configs', function (Blueprint $table) {
            $table->dropColumn(['effective_date', 'pending_changes']);
        });

        Schema::dropIfExists('large_medical_insurance_config_histories');
    }
};
