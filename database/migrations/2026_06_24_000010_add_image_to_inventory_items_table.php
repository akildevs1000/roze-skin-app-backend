<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('inventory_items') && ! Schema::hasColumn('inventory_items', 'image')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->string('image')->nullable()->after('name'); // relative path under public/
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('inventory_items') && Schema::hasColumn('inventory_items', 'image')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
