<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Warehouses are the delivery destination for a Purchase Order
     * (the "Deliver To" block on the Zoho form). A single default
     * warehouse — the company itself — is seeded so the form works
     * out of the box.
     */
    public function up()
    {
        if (! Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('phone')->nullable();
                $table->string('trn')->nullable();
                $table->boolean('is_default')->default(false);
                $table->string('status')->default('active'); // active | inactive
                $table->timestamps();
            });
        }

        if (DB::table('warehouses')->count() === 0) {
            DB::table('warehouses')->insert([
                'name'       => 'Roze Skincare LLC',
                'address'    => 'Khalid bin Waleed Road, Next to Admiral Plaza Hotel, Dubai',
                'city'       => 'Dubai',
                'country'    => 'United Arab Emirates',
                'phone'      => '+971 4 3939 562 / +971 55 330 3991',
                'trn'        => '100391417100003',
                'is_default' => true,
                'status'     => 'active',
                'created_at' => DB::raw('now()'),
                'updated_at' => DB::raw('now()'),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('warehouses');
    }
};
