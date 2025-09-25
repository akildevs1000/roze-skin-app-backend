<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('akil_security_catalogs', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_category_id')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('akil_security_catalogs', function (Blueprint $table) {
            $table->dropColumn('catalog_category_id');
        });
    }
};
