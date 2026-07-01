<?php

namespace Database\Seeders;

use App\Models\AddressProvince;
use App\Models\AddrsCity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddrsCitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $Cities = [
        //     ['name' => 'Adams', 'zipcode' => '2922'],
        //     ['name' => 'Bacarra', 'zipcode' => '2916'],
        //     ['name' => 'Badoc', 'zipcode' => '2904'],
        //     ['name' => 'Bangui', 'zipcode' => '2920'],
        //     ['name' => 'Banna', 'zipcode' => '2908'],
        //     ['name' => 'Batac City', 'zipcode' => '2906'],
        //     ['name' => 'Burgos', 'zipcode' => '2918'],
        //     ['name' => 'Carasi', 'zipcode' => '2911'],
        //     ['name' => 'Currimao', 'zipcode' => '2903'],
        //     ['name' => 'Dingras', 'zipcode' => '2913'],
        //     ['name' => 'Dumalneg', 'zipcode' => '2921'],
        //     ['name' => 'Laoag City', 'zipcode' => '2900'],
        //     ['name' => 'Marcos', 'zipcode' => '2907'],
        //     ['name' => 'Nueva Era', 'zipcode' => '2909'],
        //     ['name' => 'Pagudpud', 'zipcode' => '2919'],
        //     ['name' => 'Paoay', 'zipcode' => '2902'],
        //     ['name' => 'Pasuquin', 'zipcode' => '2917'],
        //     ['name' => 'Piddig', 'zipcode' => '2912'],
        //     ['name' => 'Pinili', 'zipcode' => '2905'],
        //     ['name' => 'San Nicolas', 'zipcode' => '2901'],
        //     ['name' => 'Sarrat', 'zipcode' => '2914'],
        //     ['name' => 'Solsona', 'zipcode' => '2910'],
        //     ['name' => 'Vintar', 'zipcode' => '2915'],
        // ];
        // DB::table('Addrs_Cities')->insert($Cities);

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        AddrsCity::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $provinceId = AddressProvince::query()
            ->where('name', config('address.province', 'Ilocos Norte'))
            ->value('id');

        if ($provinceId === null) {
            $provinceId = AddressProvince::query()->create([
                'name' => config('address.province', 'Ilocos Norte'),
            ])->id;
        }

        $csvFile = fopen(base_path('database/csv/addrs_cities.csv'), 'r');

        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            if (! $firstline) {
                AddrsCity::create([
                    'name' => $data['0'],
                    'zipcode' => $data['1'],
                    'excel_name' => $data['2'],
                    'address_province_id' => $provinceId,
                ]);
            }
            $firstline = false;
        }

        fclose($csvFile);
    }
}
