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
        Schema::table('invoice_applications', function (Blueprint $table) {
            // 添加备注字段
            if (!Schema::hasColumn('invoice_applications', 'remark')) {
                $table->text('remark')->nullable()->after('project_name')->comment('备注');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_applications', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
