<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 添加文件夹支持
     */
    public function up(): void
    {
        Schema::table('shared_files', function (Blueprint $table) {
            // 父文件夹ID（null表示根目录）
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            
            // 是否是文件夹
            $table->boolean('is_folder')->default(false)->after('parent_id');
            
            // 添加索引
            $table->index('parent_id');
            $table->index('is_folder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_files', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['is_folder']);
            $table->dropColumn(['parent_id', 'is_folder']);
        });
    }
};
