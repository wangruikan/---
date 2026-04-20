<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 修改 employee_contracts 表的 contract_type 字段
        DB::statement("ALTER TABLE `employee_contracts` MODIFY COLUMN `contract_type` ENUM('labor', 'termination', 'retirement', 'other') NOT NULL COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议，other-其他合同'");
        
        // 修改 contract_templates 表的 contract_type 字段
        DB::statement("ALTER TABLE `contract_templates` MODIFY COLUMN `contract_type` ENUM('labor', 'termination', 'retirement', 'other') NOT NULL COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议，other-其他合同'");
    }

    public function down(): void
    {
        // 回滚时删除 other 选项（注意：如果有数据使用了 other 类型，回滚会失败）
        DB::statement("ALTER TABLE `employee_contracts` MODIFY COLUMN `contract_type` ENUM('labor', 'termination', 'retirement') NOT NULL COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议'");
        
        DB::statement("ALTER TABLE `contract_templates` MODIFY COLUMN `contract_type` ENUM('labor', 'termination', 'retirement') NOT NULL COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议'");
    }
};
