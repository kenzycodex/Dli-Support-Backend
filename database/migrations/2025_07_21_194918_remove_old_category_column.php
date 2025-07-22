<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Remove the old hardcoded category column
            $table->dropColumn('category');
        });
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Restore the old category column
            $table->string('category')->default('general')->after('description');
        });
    }
};
