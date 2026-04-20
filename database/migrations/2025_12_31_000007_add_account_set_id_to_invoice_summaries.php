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
        Schema::table('invoice_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('account_set_id')->nullable()->after('id')->comment('账套ID');
            $table->foreign('account_set_id')->references('id')->on('account_sets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_summaries', function (Blueprint $table) {
            $table->dropForeign(['account_set_id']);
            $table->dropColumn('account_set_id');
        });
    }
};
