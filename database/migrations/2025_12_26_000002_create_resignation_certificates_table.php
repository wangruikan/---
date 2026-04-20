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
        Schema::create('resignation_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('file_name'); // 文件名
            $table->string('file_path'); // 文件路径
            $table->string('file_type')->nullable(); // 文件类型
            $table->bigInteger('file_size')->nullable(); // 文件大小（字节）
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null'); // 上传人
            $table->enum('upload_source', ['miniprogram', 'pc'])->default('pc'); // 上传来源
            $table->text('remark')->nullable(); // 备注
            $table->timestamps();
            
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resignation_certificates');
    }
};
