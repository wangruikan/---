<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 添加预设签名位置字段，支持多个签名位置
     */
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->json('signature_positions')->nullable()->after('sign_device')
                  ->comment('预设的多个签名位置（从合同模板继承）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn('signature_positions');
        });
    }
};
