<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            // 报表模板管理
            ['module' => 'report_templates', 'action' => 'view', 'route' => '/report-templates', 'method' => 'GET', 'name' => '报表模板-查看', 'sort_order' => 700],
            ['module' => 'report_templates', 'action' => 'create', 'route' => '/report-templates', 'method' => 'POST', 'name' => '报表模板-创建', 'sort_order' => 701],
            ['module' => 'report_templates', 'action' => 'update', 'route' => '/report-templates/{id}', 'method' => 'PUT', 'name' => '报表模板-编辑', 'sort_order' => 702],
            ['module' => 'report_templates', 'action' => 'delete', 'route' => '/report-templates/{id}', 'method' => 'DELETE', 'name' => '报表模板-删除', 'sort_order' => 703],
        ];

        foreach ($permissions as $permission) {
            // 检查权限是否已存在
            $exists = DB::table('permissions')
                ->where('module', $permission['module'])
                ->where('action', $permission['action'])
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert($permission);
            }
        }

        // 为所有管理员和业务人员用户添加这些权限
        $users = DB::table('users')->whereIn('role', ['admin', 'employee'])->get();
        
        foreach ($users as $user) {
            $permissionIds = DB::table('permissions')
                ->where('module', 'report_templates')
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                // 检查是否已存在
                $exists = DB::table('user_permissions')
                    ->where('user_id', $user->id)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (!$exists) {
                    DB::table('user_permissions')->insert([
                        'user_id' => $user->id,
                        'permission_id' => $permissionId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')
            ->where('module', 'report_templates')
            ->delete();
    }
};
