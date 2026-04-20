<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 添加新角色：薪资核算(payroll)、财务(finance)、结算员(settlement)、招聘(recruitment)
     */
    public function up(): void
    {
        // MySQL 修改 ENUM 类型需要使用原生 SQL
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'super_admin', 'manager', 'employee', 'payroll', 'finance', 'settlement', 'recruitment') DEFAULT 'employee'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚时恢复原来的 ENUM 值
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'employee') DEFAULT 'employee'");
    }
};
