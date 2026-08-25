<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'mini_selected_at')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->timestamp('mini_selected_at')
                    ->nullable()
                    ->after('last_login_at')
                    ->comment('小程序最近选择该员工档案的时间');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'mini_selected_at')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('mini_selected_at');
            });
        }
    }
};
