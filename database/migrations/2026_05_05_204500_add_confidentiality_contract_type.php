<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `employee_contracts`
            MODIFY COLUMN `contract_type`
            ENUM('labor', 'termination', 'retirement', 'confidentiality', 'other')
            NOT NULL
            COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议，confidentiality-保密协议，other-其他合同'
        ");

        DB::statement("
            ALTER TABLE `contract_templates`
            MODIFY COLUMN `contract_type`
            ENUM('labor', 'termination', 'retirement', 'confidentiality', 'other')
            NOT NULL
            COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议，confidentiality-保密协议，other-其他合同'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `employee_contracts`
            MODIFY COLUMN `contract_type`
            ENUM('labor', 'termination', 'retirement', 'other')
            NOT NULL
            COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议，other-其他合同'
        ");

        DB::statement("
            ALTER TABLE `contract_templates`
            MODIFY COLUMN `contract_type`
            ENUM('labor', 'termination', 'retirement', 'other')
            NOT NULL
            COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议，other-其他合同'
        ");
    }
};

