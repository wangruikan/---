<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bid_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('bid_projects', 'lost_reason')) {
                $table->string('lost_reason', 50)->nullable()->after('bid_result')->comment('未中标原因');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bid_projects', function (Blueprint $table) {
            if (Schema::hasColumn('bid_projects', 'lost_reason')) {
                $table->dropColumn('lost_reason');
            }
        });
    }
};
