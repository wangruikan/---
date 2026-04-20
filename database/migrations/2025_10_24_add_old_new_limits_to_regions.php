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
        // 1. 社保地区表添加新旧上下限字段
        Schema::table('social_security_regions', function (Blueprint $table) {
            $table->decimal('old_min_base_amount', 10, 2)->nullable()->after('max_base_amount')->comment('旧最低基数');
            $table->decimal('old_max_base_amount', 10, 2)->nullable()->after('old_min_base_amount')->comment('旧最高基数');
            $table->timestamp('old_limits_updated_at')->nullable()->after('old_max_base_amount')->comment('旧上下限修改时间');
            $table->decimal('new_min_base_amount', 10, 2)->nullable()->after('old_limits_updated_at')->comment('新最低基数');
            $table->decimal('new_max_base_amount', 10, 2)->nullable()->after('new_min_base_amount')->comment('新最高基数');
            $table->timestamp('new_limits_updated_at')->nullable()->after('new_max_base_amount')->comment('新上下限修改时间');
        });

        // 迁移现有数据：将 min_base_amount 和 max_base_amount 复制到 new_* 字段
        DB::statement('
            UPDATE social_security_regions 
            SET new_min_base_amount = min_base_amount,
                new_max_base_amount = max_base_amount,
                new_limits_updated_at = updated_at
            WHERE min_base_amount IS NOT NULL OR max_base_amount IS NOT NULL
        ');

        // 2. 医保地区表添加新旧上下限字段
        Schema::table('medical_insurance_regions', function (Blueprint $table) {
            $table->decimal('old_min_base_amount', 10, 2)->nullable()->after('max_base_amount')->comment('旧最低基数');
            $table->decimal('old_max_base_amount', 10, 2)->nullable()->after('old_min_base_amount')->comment('旧最高基数');
            $table->timestamp('old_limits_updated_at')->nullable()->after('old_max_base_amount')->comment('旧上下限修改时间');
            $table->decimal('new_min_base_amount', 10, 2)->nullable()->after('old_limits_updated_at')->comment('新最低基数');
            $table->decimal('new_max_base_amount', 10, 2)->nullable()->after('new_min_base_amount')->comment('新最高基数');
            $table->timestamp('new_limits_updated_at')->nullable()->after('new_max_base_amount')->comment('新上下限修改时间');
        });

        DB::statement('
            UPDATE medical_insurance_regions 
            SET new_min_base_amount = min_base_amount,
                new_max_base_amount = max_base_amount,
                new_limits_updated_at = updated_at
            WHERE min_base_amount IS NOT NULL OR max_base_amount IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_security_regions', function (Blueprint $table) {
            $table->dropColumn([
                'old_min_base_amount',
                'old_max_base_amount',
                'old_limits_updated_at',
                'new_min_base_amount',
                'new_max_base_amount',
                'new_limits_updated_at'
            ]);
        });

        Schema::table('medical_insurance_regions', function (Blueprint $table) {
            $table->dropColumn([
                'old_min_base_amount',
                'old_max_base_amount',
                'old_limits_updated_at',
                'new_min_base_amount',
                'new_max_base_amount',
                'new_limits_updated_at'
            ]);
        });
    }
};

