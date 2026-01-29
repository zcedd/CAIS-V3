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
        Schema::rename('addrs_cities', 'address_cities');

        Schema::table('address_barangays', function (Blueprint $table) {
            $table->renameColumn('addrs_cities_id', 'address_city_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('address_barangays', 'addrs_brgies');
        Schema::rename('address_cities', 'addrs_cities');

        Schema::table('addrs_brgies', function (Blueprint $table) {
            $table->renameColumn('address_city_id', 'addrs_cities_id');
        });
    }
};
