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
        Schema::rename('addrs_brgies', 'address_barangays');

        Schema::table('address_barangays', function (Blueprint $table) {
            $table->renameColumn('addrs_city_id', 'address_city_id');

            $table->bigInteger('address_city_id')->after('id')->unsigned()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('address_barangays', 'addrs_brgies');

        Schema::table('address_barangays', function (Blueprint $table) {
            $table->renameColumn('address_city_id', 'addrs_city_id');

            $table->bigInteger('addrs_city_id')->after('id')->unsigned()->change();
        });
    }
};
