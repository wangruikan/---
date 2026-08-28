<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_categories') && !Schema::hasColumn('tax_categories', 'parent_id')) {
            Schema::table('tax_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')
                    ->nullable()
                    ->after('name')
                    ->index()
                    ->comment('所属税种大类 ID，NULL 表示大类');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tax_categories') && Schema::hasColumn('tax_categories', 'parent_id')) {
            Schema::table('tax_categories', function (Blueprint $table) {
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
    }
};
