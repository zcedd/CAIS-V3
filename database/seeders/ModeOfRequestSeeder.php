<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class ModeOfRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $mode = [
            ['name' => 'Letter'],
            ['name' => 'Text'],
            ['name' => 'Call'],
            ['name' => 'Email'],
            ['name' => 'Walk In'],
        ];

        DB::table('mode_of_requests')->insert($mode);
    }
}
