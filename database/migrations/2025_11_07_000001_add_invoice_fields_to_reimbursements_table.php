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
        Schema::table('reimbursements', function (Blueprint $table) {
            // 添加新的发票相关字段
            $table->string('company_name')->nullable()->after('account_set_id')->comment('单位名称');
            $table->string('invoice_number')->nullable()->after('company_name')->comment('发票号码');
            $table->date('payment_date')->nullable()->after('invoice_number')->comment('打款日期');
            $table->string('category')->nullable()->after('amount')->comment('类目');
            $table->string('project')->nullable()->after('category')->comment('项目');
            $table->string('received_invoice')->nullable()->after('project')->comment('收到发票(是/否)');
            $table->string('invoice_type')->nullable()->after('received_invoice')->comment('发票类型');
            $table->decimal('invoice_amount', 10, 2)->nullable()->after('reason')->comment('开票金额');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('invoice_amount')->comment('税率(%)');
            $table->decimal('tax_deduction', 10, 2)->nullable()->after('tax_rate')->comment('扣税额');
            $table->decimal('amount_excluding_tax', 10, 2)->nullable()->after('tax_deduction')->comment('不含税金额');
            $table->decimal('tax_amount', 10, 2)->nullable()->after('amount_excluding_tax')->comment('税金');
            $table->date('invoice_date')->nullable()->after('tax_amount')->comment('开票日期');
            $table->string('record_status')->nullable()->after('invoice_date')->comment('状态(是/否)');
            $table->string('accounting_status')->nullable()->after('record_status')->comment('入账(是/否)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'invoice_number',
                'payment_date',
                'category',
                'project',
                'received_invoice',
                'invoice_type',
                'invoice_amount',
                'tax_rate',
                'tax_deduction',
                'amount_excluding_tax',
                'tax_amount',
                'invoice_date',
                'record_status',
                'accounting_status',
            ]);
        });
    }
};

