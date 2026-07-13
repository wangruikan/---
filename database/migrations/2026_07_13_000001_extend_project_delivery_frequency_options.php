<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `projects` MODIFY `delivery_frequency` ENUM('monthly', 'quarterly', 'semiannual', 'annual') NULL DEFAULT 'monthly' COMMENT '交付频率'");
    }

    public function down(): void
    {
        DB::statement("UPDATE `projects` SET `delivery_frequency` = 'quarterly' WHERE `delivery_frequency` IN ('semiannual', 'annual')");
        DB::statement("ALTER TABLE `projects` MODIFY `delivery_frequency` ENUM('monthly', 'quarterly') NULL DEFAULT 'monthly' COMMENT '交付频率'");
    }
};
