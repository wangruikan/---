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
        Schema::table('employee_registration_forms', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_registration_forms', 'photo')) {
                $table->string('photo')->nullable()->after('birth_date')->comment('一寸照片路径');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_registration_forms', function (Blueprint $table) {
            if (Schema::hasColumn('employee_registration_forms', 'photo')) {
                $table->dropColumn('photo');
            }
        });
    }
};
