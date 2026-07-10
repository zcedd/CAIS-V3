<?php

use App\Models\AddressBarangay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('formatted label includes barangay city and province', function () {
    $provinceId = DB::table('address_provinces')->insertGetId([
        'name' => 'Cebu',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cityId = DB::table('address_cities')->insertGetId([
        'name' => 'Cebu City',
        'zipcode' => '6000',
        'excel_name' => 'Cebu City',
        'address_province_id' => $provinceId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $barangayId = DB::table('address_barangays')->insertGetId([
        'name' => 'San Jose',
        'address_city_id' => $cityId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $barangay = AddressBarangay::query()
        ->with('city.province:id,name')
        ->findOrFail($barangayId);

    expect($barangay->formattedLabel())->toBe('San Jose, Cebu City, Cebu');
});
