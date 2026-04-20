<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddInsuranceSurrenderPermissionSeeder extends Seeder
{
    public function run()
    {
        // 检查权限表结构
        $columns = DB::select("SHOW COLUMNS FROM permissions");
        $columnNames = array_column($columns, 'Field');
        
        echo "权限表字段: " . implode(', ', $columnNames) . "\n";
        
        // 添加商业险退保相关权限
        // 权限表字段: id, module, action, route, method, name, description, sort_order, created_at, updated_at
        $permissions = [
            [
                'module' => 'insurance_surrender',
                'action' => 'view',
                'route' => '/insurance-surrenders',
                'method' => 'GET',
                'name' => '查看商业险退保',
                'description' => '可以查看商业险退保列表和详情',
                'sort_order' => 1,
            ],
            [
                'module' => 'insurance_surrender',
                'action' => 'upload',
                'route' => '/insurance-surrenders/{id}/attachments',
                'method' => 'POST',
                'name' => '上传退保附件',
                'description' => '可以上传退保保单页和收款回单',
                'sort_order' => 2,
            ],
            [
                'module' => 'insurance_surrender',
                'action' => 'submit_business',
                'route' => '/insurance-surrenders/{id}/submit-business',
                'method' => 'POST',
                'name' => '提交业务处理',
                'description' => '可以提交业务处理（上传保单页和退保金额）',
                'sort_order' => 3,
            ],
            [
                'module' => 'insurance_surrender',
                'action' => 'submit_finance',
                'route' => '/insurance-surrenders/{id}/submit-finance',
                'method' => 'POST',
                'name' => '提交财务处理',
                'description' => '可以提交财务处理（上传收款回单）',
                'sort_order' => 4,
            ],
        ];

        foreach ($permissions as $permission) {
            // 检查权限是否已存在（根据 module + action 判断）
            $exists = DB::table('permissions')
                ->where('module', $permission['module'])
                ->where('action', $permission['action'])
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert([
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                    'route' => $permission['route'],
                    'method' => $permission['method'],
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'sort_order' => $permission['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $this->command->info("✓ 权限已添加: {$permission['module']}.{$permission['action']} ({$permission['name']})");
            } else {
                $this->command->warn("⚠ 权限已存在: {$permission['module']}.{$permission['action']}");
            }
        }

        $this->command->info("\n商业险退保权限添加完成！");
        $this->command->info("请在角色权限管理页面为相应角色分配这些权限。");
    }
}
