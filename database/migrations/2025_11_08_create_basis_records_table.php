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
        Schema::create('basis_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_set_id')->constrained()->onDelete('cascade')->comment('账套ID');
            $table->foreignId('project_id')->constrained()->onDelete('cascade')->comment('项目ID');
            $table->enum('type', ['attendance', 'salary'])->comment('依据类型：attendance-考勤依据，salary-工资依据');
            $table->string('month', 7)->comment('月份，格式：YYYY-MM');
            $table->text('description')->nullable()->comment('文字说明');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade')->comment('创建人');
            $table->timestamps();
            $table->softDeletes();
            
            // 唯一索引：同一个项目同一个月同一种类型只能有一条记录
            $table->unique(['project_id', 'month', 'type', 'deleted_at'], 'unique_project_month_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basis_records');
    }
};

