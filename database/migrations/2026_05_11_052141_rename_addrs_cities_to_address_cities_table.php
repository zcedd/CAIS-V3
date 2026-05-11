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
        Schema::rename('addrs_cities', 'address_cities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('address_cities', 'addrs_cities');
    }
};
