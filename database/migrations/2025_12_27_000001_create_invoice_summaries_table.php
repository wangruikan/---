<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoice_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_application_id')->comment('发票申请ID');
            $table->string('period', 7)->comment('所属期（YYYY-MM）');
            $table->string('unit_name')->comment('单位名称');
            $table->date('apply_date')->comment('申请日期');
            $table->string('invoice_method', 20)->comment('开票方式：full-全额, diff-差额, none-无');
            $table->string('invoice_type', 50)->comment('开票种类');
            $table->string('status', 20)->default('pending')->comment('状态：pending-待开票, completed-已完成');
            $table->string('project_name')->nullable()->comment('项目名称');
            $table->decimal('invoice_amount', 15, 2)->default(0)->comment('开票金额');
            $table->decimal('deduction_amount', 15, 2)->default(0)->comment('扣除额');
            $table->decimal('tax_rate', 5, 4)->default(0)->comment('税率');
            $table->decimal('amount_without_tax', 15, 2)->default(0)->comment('不含税金额');
            $table->decimal('invoice_tax', 15, 2)->default(0)->comment('开票税金');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('税金');
            $table->date('invoice_date')->nullable()->comment('开票日期');
            $table->boolean('is_completed')->default(false)->comment('是否完成');
            $table->string('invoicer')->nullable()->comment('开票人');
            $table->string('invoice_number')->nullable()->comment('发票号码');
            $table->text('remarks')->nullable()->comment('备注');
            $table->timestamps();
            
            $table->foreign('invoice_application_id')->references('id')->on('invoice_applications')->onDelete('cascade');
            $table->index('period');
            $table->index('status');
            $table->index('apply_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_summaries');
    }
};
