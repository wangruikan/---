<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('notice_placeholder_positions')->nullable()->after('contract_notice_files')->comment('须知签名占位符配置，按文件ID映射');
        });

        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->json('notice_signed_files')->nullable()->after('signature_positions')->comment('须知签名副本文件列表');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('notice_placeholder_positions');
        });

        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn('notice_signed_files');
        });
    }
};
