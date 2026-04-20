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
        Schema::create('blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('id_number', 18)->unique()->comment('身份证号');
            $table->string('name')->comment('姓名');
            $table->text('reason')->comment('加入黑名单原因');
            $table->unsignedBigInteger('created_by')->nullable()->comment('操作人ID');
            $table->timestamps();
            
            $table->index('id_number');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklist');
    }
};
