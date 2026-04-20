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
        Schema::table('shared_files', function (Blueprint $table) {
            $table->softDeletes();  // 添加 deleted_at 字段实现软删除
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_files', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
