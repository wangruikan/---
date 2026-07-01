<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `large_medical_insurance_configs`
            MODIFY `company_ratio` DECIMAL(8,4) NULL COMMENT '公司比例',
            MODIFY `employee_ratio` DECIMAL(8,4) NULL COMMENT '员工比例'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `large_medical_insurance_configs`
            MODIFY `company_ratio` DECIMAL(5,2) NULL COMMENT '公司比例',
            MODIFY `employee_ratio` DECIMAL(5,2) NULL COMMENT '员工比例'
        ");
    }
};
