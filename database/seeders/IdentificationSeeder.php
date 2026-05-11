<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IdentificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $identification = [
            ['name' => 'RSBSA ID'],
            ['name' => 'Passport'],
            ['name' => 'Driver\'s License'],
            ['name' => 'UMID'],
            ['name' => 'PhilHealth ID'],
            ['name' => 'TIN ID'],
            ['name' => 'Postal ID'],
            ['name' => 'NBI Clearance'],
            ['name' => 'PRC ID'],
            ['name' => 'OWWA OFW e-Card'],
            ['name' => 'Senior Citizen ID'],
            ['name' => 'National ID'],
            ['name' => 'PWD ID'],
        ];

        DB::table('identifications')->insert($identification);
    }
}
