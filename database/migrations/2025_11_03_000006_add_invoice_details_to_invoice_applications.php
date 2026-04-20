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
        Schema::table('invoice_applications', function (Blueprint $table) {
            // 所属期
            $table->integer('period_year')->nullable()->after('project_name')->comment('所属期-年份');
            $table->integer('period_month')->nullable()->after('period_year')->comment('所属期-月份');
            
            // 单位名称
            $table->string('company_name', 200)->nullable()->after('period_month')->comment('单位名称');
            
            // 申请日期
            $table->date('application_date')->nullable()->after('company_name')->comment('申请日期');
            
            // 开票方式
            $table->enum('invoice_method', ['full', 'partial', 'none'])->nullable()->after('application_date')->comment('开票方式：full-全额，partial-缺额，none-无');
            
            // 开票种类
            $table->string('invoice_type', 50)->default('普票')->after('invoice_method')->comment('开票种类：普票、专票等');
            
            // 金额相关字段
            $table->decimal('deduction_amount', 15, 2)->default(0)->after('invoice_type')->comment('扣除额');
            $table->decimal('tax_rate', 5, 4)->default(0)->after('deduction_amount')->comment('税率（如0.06表示6%）');
            $table->decimal('amount_excluding_tax', 15, 2)->default(0)->after('tax_rate')->comment('不含税金额');
            $table->decimal('invoice_tax_amount', 15, 2)->default(0)->after('amount_excluding_tax')->comment('开票税额');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('invoice_tax_amount')->comment('税金');
            
            // 开票信息
            $table->date('invoice_date')->nullable()->after('tax_amount')->comment('开票日期');
            $table->boolean('is_completed')->default(false)->after('invoice_date')->comment('是否完成');
            $table->string('invoicer', 100)->nullable()->after('is_completed')->comment('开票人');
            $table->string('invoice_number', 100)->nullable()->after('invoicer')->comment('发票号码');
            
            // 备注
            $table->text('invoice_remark')->nullable()->after('invoice_number')->comment('开票备注');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_applications', function (Blueprint $table) {
            $table->dropColumn([
                'period_year',
                'period_month',
                'company_name',
                'application_date',
                'invoice_method',
                'invoice_type',
                'deduction_amount',
                'tax_rate',
                'amount_excluding_tax',
                'invoice_tax_amount',
                'tax_amount',
                'invoice_date',
                'is_completed',
                'invoicer',
                'invoice_number',
                'invoice_remark'
            ]);
        });
    }
};

