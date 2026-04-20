<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bank_stamps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name')->default('银行付讫');
            $table->string('image_path');
            $table->string('original_filename')->nullable();
            $table->integer('position_x')->default(70); // 默认X位置（百分比）
            $table->integer('position_y')->default(80); // 默认Y位置（百分比）
            $table->integer('width')->default(100); // 默认宽度
            $table->integer('height')->default(50); // 默认高度
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bank_stamps');
    }
};
