<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CivilStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $civil_status = [
            ['name' => 'Single'],
            ['name' => 'Married'],
            ['name' => 'Widowed'],
            ['name' => 'Divorced'],
            ['name' => 'Separated'],
            ['name' => 'Unknown'],
        ];

        DB::table('civil_statuses')->insert($civil_status);
    }
}
