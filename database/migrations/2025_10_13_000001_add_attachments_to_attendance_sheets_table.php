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
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->text('attachments')->nullable()->after('notes')->comment('附件信息（JSON格式）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};

