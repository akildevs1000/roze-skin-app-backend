<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand vendors into a full contact record:
     * title, first/last name, company, tax number, email, work phone, mobile,
     * country/state/city, zip code. Older free-form columns are dropped.
     */
    public function up()
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'title')) $table->string('title')->nullable()->after('id');
            if (! Schema::hasColumn('vendors', 'first_name')) $table->string('first_name')->nullable()->after('title');
            if (! Schema::hasColumn('vendors', 'last_name')) $table->string('last_name')->nullable()->after('first_name');
            if (! Schema::hasColumn('vendors', 'company_name')) $table->string('company_name')->nullable()->after('last_name');
            if (! Schema::hasColumn('vendors', 'tax_number')) $table->string('tax_number')->nullable()->after('company_name');
            if (! Schema::hasColumn('vendors', 'work_phone')) $table->string('work_phone')->nullable()->after('email');
            if (! Schema::hasColumn('vendors', 'mobile')) $table->string('mobile')->nullable()->after('work_phone');
            if (! Schema::hasColumn('vendors', 'country')) $table->string('country')->nullable()->after('mobile');
            if (! Schema::hasColumn('vendors', 'state')) $table->string('state')->nullable()->after('country');
            if (! Schema::hasColumn('vendors', 'city')) $table->string('city')->nullable()->after('state');
            if (! Schema::hasColumn('vendors', 'zip_code')) $table->string('zip_code')->nullable()->after('city');
        });

        // Drop the redundant simple columns (the table was just created and is empty).
        // We KEEP a single common 'address' field; 'name' is kept (NOT NULL) and always
        // populated by the controller as a derived display label (company / full name).
        Schema::table('vendors', function (Blueprint $table) {
            foreach (['contact_person', 'phone'] as $col) {
                if (Schema::hasColumn('vendors', $col)) $table->dropColumn($col);
            }
        });
    }

    public function down()
    {
        Schema::table('vendors', function (Blueprint $table) {
            foreach (['title', 'first_name', 'last_name', 'company_name', 'tax_number', 'work_phone', 'mobile', 'country', 'state', 'city', 'zip_code'] as $col) {
                if (Schema::hasColumn('vendors', $col)) $table->dropColumn($col);
            }
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
        });
    }
};
