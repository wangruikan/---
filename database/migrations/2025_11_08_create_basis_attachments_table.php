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
        Schema::create('basis_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basis_record_id')->constrained()->onDelete('cascade')->comment('依据记录ID');
            $table->string('file_name')->comment('文件名');
            $table->string('file_path')->comment('文件路径');
            $table->string('file_type')->comment('文件类型：image/document/other');
            $table->bigInteger('file_size')->comment('文件大小（字节）');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basis_attachments');
    }
};

