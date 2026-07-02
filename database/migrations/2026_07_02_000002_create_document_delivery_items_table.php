<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id')->index()->comment('交付记录ID');
            $table->string('document_name', 255)->comment('资料名称');
            $table->unsignedInteger('sort_order')->default(1)->comment('排序');
            $table->enum('status', ['pending', 'submitted', 'completed'])->default('pending')->comment('状态');
            $table->text('submitted_documents')->nullable()->comment('提交说明');
            $table->string('express_number', 100)->nullable()->comment('快递单号');
            $table->date('express_date')->nullable()->comment('快递日期');
            $table->unsignedBigInteger('submitted_by')->nullable()->comment('提交人ID');
            $table->dateTime('submitted_at')->nullable()->comment('提交时间');
            $table->unsignedBigInteger('completed_by')->nullable()->comment('完成人ID');
            $table->dateTime('completed_at')->nullable()->comment('完成时间');
            $table->text('remarks')->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_delivery_items');
    }
};
