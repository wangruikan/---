<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->string('report_title', 200)->nullable()->after('description')->comment('报表标题');
            $table->text('header_fields')->nullable()->after('fields')->comment('表头字段配置JSON');
            $table->text('footer_fields')->nullable()->after('header_fields')->comment('表尾字段配置JSON');
        });
    }

    public function down()
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropColumn(['report_title', 'header_fields', 'footer_fields']);
        });
    }
};
