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
            // 添加是否已重新发起的标记
            if (!Schema::hasColumn('invoice_applications', 'has_resubmitted')) {
                $table->boolean('has_resubmitted')->default(false)->after('status')->comment('是否已重新发起（红冲后）');
            }
            // 添加新申请ID的关联（可选）
            if (!Schema::hasColumn('invoice_applications', 'new_application_id')) {
                $table->bigInteger('new_application_id')->nullable()->after('has_resubmitted')->comment('重新发起后的新申请ID');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_applications', function (Blueprint $table) {
            $table->dropColumn(['has_resubmitted', 'new_application_id']);
        });
    }
};

