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
        if (!Schema::hasTable('invoice_content_configs')) {
            Schema::create('invoice_content_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_set_id')->comment('账套ID');
                $table->string('project_name', 255)->comment('项目名称');
                $table->text('remark')->nullable()->comment('备注');
                $table->text('deduction_info')->nullable()->comment('维护扣除信息');
                $table->unsignedBigInteger('created_by')->comment('创建人ID');
                $table->timestamps();

                $table->index('account_set_id', 'idx_invoice_content_configs_account_set');
                $table->index('created_by', 'idx_invoice_content_configs_created_by');
            });
        }

        if (!Schema::hasTable('invoice_content_items')) {
            Schema::create('invoice_content_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->comment('发票申请ID');
                $table->unsignedBigInteger('invoice_content_config_id')->nullable()->comment('开票内容配置ID');
                $table->integer('sequence')->default(1)->comment('序号');
                $table->string('project_name', 255)->comment('项目名称');
                $table->text('remark')->nullable()->comment('备注');
                $table->text('deduction_info')->nullable()->comment('维护扣除信息');
                $table->decimal('invoice_amount', 15, 2)->default(0)->comment('开票金额');
                $table->decimal('tax_rate', 5, 4)->default(0)->comment('税率');
                $table->decimal('deduction_amount', 15, 2)->default(0)->comment('扣除额');
                $table->decimal('invoice_tax_amount', 15, 2)->default(0)->comment('开票税额');
                $table->decimal('amount_excluding_tax', 15, 2)->default(0)->comment('不含税金额');
                $table->decimal('tax_amount', 15, 2)->default(0)->comment('税金');
                $table->timestamps();

                $table->index('application_id', 'idx_invoice_content_items_application');
                $table->index('invoice_content_config_id', 'idx_invoice_content_items_config');
                $table->foreign('application_id', 'fk_invoice_content_items_application')
                    ->references('id')
                    ->on('invoice_applications')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_content_items');
        Schema::dropIfExists('invoice_content_configs');
    }
};
