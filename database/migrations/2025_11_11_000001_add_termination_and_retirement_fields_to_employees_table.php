<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 添加 termination_date 字段（如果不存在）
            if (!Schema::hasColumn('employees', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('contract_status');
            }
            
            // 添加 termination_reason 字段（如果不存在）
            if (!Schema::hasColumn('employees', 'termination_reason')) {
                $table->text('termination_reason')->nullable()->after('termination_date');
            }
            
            // 添加 is_retired 字段（如果不存在）
            if (!Schema::hasColumn('employees', 'is_retired')) {
                $table->boolean('is_retired')->default(false)->after('termination_reason');
            }
            
            // 添加 retirement_date 字段（如果不存在）
            if (!Schema::hasColumn('employees', 'retirement_date')) {
                $table->date('retirement_date')->nullable()->after('is_retired');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 只有在字段存在时才删除
            if (Schema::hasColumn('employees', 'retirement_date')) {
                $table->dropColumn('retirement_date');
            }
            if (Schema::hasColumn('employees', 'is_retired')) {
                $table->dropColumn('is_retired');
            }
            if (Schema::hasColumn('employees', 'termination_reason')) {
                $table->dropColumn('termination_reason');
            }
            if (Schema::hasColumn('employees', 'termination_date')) {
                $table->dropColumn('termination_date');
            }
        });
    }
};

