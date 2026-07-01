<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('address_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('address_cities', function (Blueprint $table) {
            $table->foreignId('address_province_id')
                ->nullable()
                ->after('id')
                ->constrained('address_provinces');
        });

        $provinceId = DB::table('address_provinces')->insertGetId([
            'name' => 'Ilocos Norte',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('address_cities')->update([
            'address_province_id' => $provinceId,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('address_cities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('address_province_id');
        });

        Schema::dropIfExists('address_provinces');
    }
};
