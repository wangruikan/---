<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 删除唯一索引，允许每个资料配置上传多个文件
     */
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            // 删除唯一索引
            $table->dropUnique('idx_unique_employee_document');
        });
        
        // 添加普通索引（用于查询优化）
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->index(['employee_id', 'document_config_id'], 'idx_employee_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropIndex('idx_employee_document');
        });
        
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->unique(['employee_id', 'document_config_id'], 'idx_unique_employee_document');
        });
    }
};
