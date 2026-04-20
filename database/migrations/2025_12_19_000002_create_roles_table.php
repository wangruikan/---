<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 创建角色表
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('角色标识');
            $table->string('display_name', 100)->comment('显示名称');
            $table->string('description')->nullable()->comment('角色描述');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });

        // 创建角色-权限关联表
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['role_id', 'permission_id']);
        });

        // 插入默认角色
        DB::table('roles')->insert([
            ['name' => 'super_admin', 'display_name' => '超级管理员', 'description' => '拥有所有权限', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'display_name' => '管理员', 'description' => '系统管理员', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'employee', 'display_name' => '业务人员', 'description' => '普通业务人员', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'payroll', 'display_name' => '薪资核算', 'description' => '负责薪资核算', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'finance', 'display_name' => '财务', 'description' => '财务人员', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'settlement', 'display_name' => '结算员', 'description' => '负责结算', 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'recruitment', 'display_name' => '招聘', 'description' => '负责招聘', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
