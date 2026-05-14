<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sector = [
            ['name' => 'Administrative governance'],
            ['name' => 'Social governance'],
            ['name' => 'Economic governance'],
            ['name' => 'Environmental governance'],
        ];

        DB::table('sectors')->insert($sector);
    }
}
