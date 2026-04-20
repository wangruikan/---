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
        Schema::table('recruitment', function (Blueprint $table) {
            $table->string('department')->nullable()->comment('部门');
            $table->string('salary_range')->nullable()->comment('薪资范围');
            $table->string('work_location')->nullable()->comment('工作地点');
            $table->string('education')->nullable()->comment('学历要求');
            $table->string('experience')->nullable()->comment('工作经验');
            $table->text('description')->nullable()->comment('职位描述');
            $table->date('start_date')->nullable()->comment('开始日期');
            $table->date('end_date')->nullable()->comment('结束日期');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment', function (Blueprint $table) {
            $table->dropColumn([
                'department',
                'salary_range',
                'work_location',
                'education',
                'experience',
                'description',
                'start_date',
                'end_date'
            ]);
        });
    }
};
