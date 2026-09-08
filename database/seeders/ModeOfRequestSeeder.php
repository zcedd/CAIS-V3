<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

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
            ['name' => 'Online'],
        ];

        DB::table('mode_of_requests')->insert($mode);
    }
}
