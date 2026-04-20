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
        Schema::table('employees', function (Blueprint $table) {
            $table->date('id_card_valid_from')->nullable()->comment('身份证有效期开始日期');
            $table->date('id_card_valid_until')->nullable()->comment('身份证有效期结束日期，NULL表示长期有效');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['id_card_valid_from', 'id_card_valid_until']);
        });
    }
};
