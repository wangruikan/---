<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建管理员账户
        User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('123456'),
            'phone' => '13800138000',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // 创建经理账户
        User::create([
            'name' => 'manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('123456'),
            'phone' => '13800138001',
            'role' => 'manager',
            'is_active' => true,
        ]);

        // 创建员工账户
        User::create([
            'name' => 'employee',
            'email' => 'employee@example.com',
            'password' => Hash::make('123456'),
            'phone' => '13800138002',
            'role' => 'employee',
            'is_active' => true,
        ]);

        echo "用户数据已创建:\n";
        echo "管理员 - 用户名: admin, 密码: 123456\n";
        echo "经理 - 用户名: manager, 密码: 123456\n";
        echo "员工 - 用户名: employee, 密码: 123456\n";
    }
}


