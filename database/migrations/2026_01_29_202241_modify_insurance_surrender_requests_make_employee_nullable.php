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
        Schema::table('insurance_surrender_requests', function (Blueprint $table) {
            // 将 employee_id 改为可空，因为现在退保不再关联具体员工
            $table->unsignedBigInteger('employee_id')->nullable()->change();
            
            // 将 insurance_change_id 也改为可空，因为现在是手动创建，不一定关联变更记录
            $table->unsignedBigInteger('insurance_change_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_surrender_requests', function (Blueprint $table) {
            // 回滚时改回非空
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
            $table->unsignedBigInteger('insurance_change_id')->nullable(false)->change();
        });
    }
};
