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
            // 检查字段是否存在，只添加不存在的字段
            if (!Schema::hasColumn('employees', 'household_province')) {
                $table->string('household_province')->nullable()->comment('户籍所在地（省）');
            }
            if (!Schema::hasColumn('employees', 'household_city')) {
                $table->string('household_city')->nullable()->comment('户籍所在地（市）');
            }
            if (!Schema::hasColumn('employees', 'household_district')) {
                $table->string('household_district')->nullable()->comment('户籍所在地（区县）');
            }
            if (!Schema::hasColumn('employees', 'household_address')) {
                $table->text('household_address')->nullable()->comment('户籍所在地（详细地址）');
            }
            
            // 经常居住地信息
            if (!Schema::hasColumn('employees', 'residence_province')) {
                $table->string('residence_province')->nullable()->comment('经常居住地（省）');
            }
            if (!Schema::hasColumn('employees', 'residence_city')) {
                $table->string('residence_city')->nullable()->comment('经常居住地（市）');
            }
            if (!Schema::hasColumn('employees', 'residence_district')) {
                $table->string('residence_district')->nullable()->comment('经常居住地（区县）');
            }
            if (!Schema::hasColumn('employees', 'residence_address')) {
                $table->text('residence_address')->nullable()->comment('经常居住地（详细地址）');
            }
            
            // 联系地址信息
            if (!Schema::hasColumn('employees', 'contact_province')) {
                $table->string('contact_province')->nullable()->comment('联系地址（省）');
            }
            if (!Schema::hasColumn('employees', 'contact_city')) {
                $table->string('contact_city')->nullable()->comment('联系地址（市）');
            }
            if (!Schema::hasColumn('employees', 'contact_district')) {
                $table->string('contact_district')->nullable()->comment('联系地址（区县）');
            }
            if (!Schema::hasColumn('employees', 'contact_address')) {
                $table->text('contact_address')->nullable()->comment('联系地址（详细地址）');
            }
            
            // 其他信息 - 只添加不存在的字段
            if (!Schema::hasColumn('employees', 'remarks')) {
                $table->text('remarks')->nullable()->comment('备注');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'household_province', 'household_city', 'household_district', 'household_address',
                'residence_province', 'residence_city', 'residence_district', 'residence_address',
                'contact_province', 'contact_city', 'contact_district', 'contact_address',
                'education', 'remarks'
            ]);
        });
    }
};
