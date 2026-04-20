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
        // 权限定义表
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50)->comment('模块名称，如：employees, projects');
            $table->string('action', 50)->comment('操作类型，如：view, create, edit, delete');
            $table->string('name', 100)->comment('权限名称，如：人员档案-查看');
            $table->string('description')->nullable()->comment('权限描述');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            
            // 唯一索引：模块+操作
            $table->unique(['module', 'action']);
        });

        // 用户-权限关联表
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // 唯一索引：用户+权限
            $table->unique(['user_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permissions');
    }
};
