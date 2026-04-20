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
        Schema::create('travel_application_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_application_id')->constrained()->onDelete('cascade'); // 差旅申请ID
            $table->string('file_name'); // 文件名
            $table->string('file_path'); // 文件路径
            $table->string('file_type')->nullable(); // 文件类型
            $table->bigInteger('file_size')->nullable(); // 文件大小（字节）
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_application_attachments');
    }
};
