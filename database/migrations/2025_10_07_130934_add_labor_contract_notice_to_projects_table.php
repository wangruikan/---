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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('labor_contract_notice_name')->nullable()->comment('劳动合同须知文件名称');
            $table->string('labor_contract_notice_file')->nullable()->comment('劳动合同须知文件路径');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['labor_contract_notice_name', 'labor_contract_notice_file']);
        });
    }
};
