<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_request_attachments', function (Blueprint $table) {
            $table->string('attachment_type')->default('attachment')->after('mime_type')->comment('附件类型: invoice=发票, attachment=普通附件');
        });
    }

    public function down(): void
    {
        Schema::table('payment_request_attachments', function (Blueprint $table) {
            $table->dropColumn('attachment_type');
        });
    }
};
