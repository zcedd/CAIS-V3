<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class departmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $department = [
            ['name' => 'Admin Office'],
            ['name' => 'Bangui District Hospital'],
            ['name' => 'Communications And Media Office'],
            ['name' => 'Dingras District Hospital'],
            ['name' => 'Gender And Development Office'],
            ['name' => 'General Service Office'],
            ['name' => 'Gov. Roque B. Ablan Sr. Memorial Hospital'],
            ['name' => 'Human Resource Management Office'],
            ['name' => 'Ilocos Norte Provicial Jail'],
            ['name' => 'Ilocos Norte Provincial Library And IHUB'],
            ['name' => 'Ilocos Norte Sports Development Office'],
            ['name' => 'Information Technology Office'],
            ['name' => 'Ilocos Norte Youth Development Office'],
            ['name' => 'Invest Office'],
            ['name' => 'Marcos District Office'],
            ['name' => 'Metro Ilocos Norte Council Office'],
            ['name' => 'MSME'],
            ['name' => 'Office Of The Barangay Affairs'],
            ['name' => 'Office Of The Governor'],
            ['name' => 'Piddig District Hospital'],
            ['name' => 'Provincial Accounting Office'],
            ['name' => 'Provincial Agriculture Office'],
            ['name' => 'Provincial Assessor\'s Office'],
            ['name' => 'Provincial Budget Office'],
            ['name' => 'Provincial Education Department'],
            ['name' => 'Provincial Engineering Office'],
            ['name' => 'Provincial Environment And Natural Resources Office'],
            ['name' => 'Provincial Health Office'],
            ['name' => 'Provincial Legal Office'],
            ['name' => 'Provincial Planning And Development Office'],
            ['name' => 'Provincial Resiliency Office'],
            ['name' => 'Provincial Social Welfare And Development Office'],
            ['name' => 'Provincial Tourism Office'],
            ['name' => 'Provincial Tresurer\'s Office'],
            ['name' => 'Provincial Veterinary Office'],
            ['name' => 'Provincial Employment Service Office'],
            ['name' => 'Provincial Quarry Office'],
            ['name' => 'Sanggunian Panlalawigan Office'],
            ['name' => 'Sustainable Development Center Office'],
            ['name' => 'Vice Governor\'s Office'],
            ['name' => 'Vintar District Hospital'],
        ];

        DB::table('Departments')->insert($department);
    }
}
