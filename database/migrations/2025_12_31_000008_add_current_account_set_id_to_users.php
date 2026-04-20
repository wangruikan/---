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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('current_account_set_id')->nullable()->after('account_set_id')->comment('当前选择的账套ID');
            $table->foreign('current_account_set_id')->references('id')->on('account_sets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_account_set_id']);
            $table->dropColumn('current_account_set_id');
        });
    }
};
