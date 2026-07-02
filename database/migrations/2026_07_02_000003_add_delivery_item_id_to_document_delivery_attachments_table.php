<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_deliveries', function (Blueprint $table) {
            $table->string('document_period', 7)
                ->nullable()
                ->after('display_month')
                ->comment('所属期 YYYY-MM');
        });

        Schema::table('document_delivery_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_item_id')
                ->nullable()
                ->after('delivery_id')
                ->comment('交付资料项ID');
            $table->index('delivery_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_deliveries', function (Blueprint $table) {
            $table->dropColumn('document_period');
        });

        Schema::table('document_delivery_attachments', function (Blueprint $table) {
            $table->dropIndex(['delivery_item_id']);
            $table->dropColumn('delivery_item_id');
        });
    }
};
