<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('invoice_applications', function (Blueprint $table) {
            $table->date('earliest_invoice_date')->nullable()->after('invoice_date')->comment('最早开票日期');
        });
    }

    public function down()
    {
        Schema::table('invoice_applications', function (Blueprint $table) {
            $table->dropColumn('earliest_invoice_date');
        });
    }
};
