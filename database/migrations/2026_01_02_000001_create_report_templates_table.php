<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('模板名称');
            $table->text('description')->nullable()->comment('模板说明');
            $table->unsignedBigInteger('region_id')->nullable()->comment('关联地区ID');
            $table->string('region_type', 50)->nullable()->comment('地区类型：social_security, medical_insurance, housing_fund');
            $table->text('fields')->comment('字段配置JSON');
            $table->unsignedBigInteger('account_set_id')->comment('账套ID');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->timestamps();
            
            $table->index('account_set_id');
            $table->index('region_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('report_templates');
    }
};
