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
            $table->string("model_number")->nullable();
            $table->string("video_link")->nullable();
            $table->string("data_sheet_link")->nullable();
            $table->string("product_gallery_link")->nullable();
            $table->string("website_link")->nullable();
            $table->unsignedBigInteger('product_category_id')->default(0);
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
            $table->dropColumn("model_number");
            $table->dropColumn("video_link");
            $table->dropColumn("data_sheet_link");
            $table->dropColumn("product_gallery_link");
            $table->dropColumn("website_link");
            $table->dropColumn('product_category_id');
        });
    }
};
