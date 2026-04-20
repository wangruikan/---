<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 添加退休类别字段，用于区分女职工的退休年龄类型
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 退休类别：cadre-管理/技术岗(女55岁), worker-生产/操作岗(女50岁)
            // 男职工统一60岁，此字段仅对女职工有效
            $table->enum('retirement_category', ['cadre', 'worker'])
                  ->default('worker')
                  ->after('is_retired')
                  ->comment('退休类别：cadre-管理岗(女55岁), worker-普通岗(女50岁)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('retirement_category');
        });
    }
};
