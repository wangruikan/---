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
        Schema::table('contract_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('contract_templates', 'placeholder_positions')) {
                $table->json('placeholder_positions')->nullable()->after('is_default')->comment('占位符位置数据');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            if (Schema::hasColumn('contract_templates', 'placeholder_positions')) {
                $table->dropColumn('placeholder_positions');
            }
        });
    }
};
