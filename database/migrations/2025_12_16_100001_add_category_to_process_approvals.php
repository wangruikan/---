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
        Schema::table('process_approvals', function (Blueprint $table) {
            $table->string('category', 50)->default('social_insurance')->after('title')->comment('汇总类型：social_insurance=社保, housing_fund=公积金');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_approvals', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
