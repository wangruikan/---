<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_deduction_items', function (Blueprint $table) {
            if (!Schema::hasColumn('special_deduction_items', 'item_type')) {
                $table->string('item_type', 20)
                    ->default('special')
                    ->after('project_id')
                    ->comment('项目类型：special=专项扣除，other=其他扣除');
                $table->index(['account_set_id', 'item_type', 'is_active'], 'sdi_account_type_active_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('special_deduction_items', function (Blueprint $table) {
            if (Schema::hasColumn('special_deduction_items', 'item_type')) {
                $table->dropIndex('sdi_account_type_active_idx');
                $table->dropColumn('item_type');
            }
        });
    }
};
