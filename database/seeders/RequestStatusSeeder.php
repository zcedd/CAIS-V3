<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RequestStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $status = [
            ['name' => 'Draft'],
            ['name' => 'Submitted'],
            ['name' => 'Pending Review'],
            ['name' => 'Verification'],
            ['name' => 'In Progress'],
            ['name' => 'On Hold'],
            ['name' => 'Escalated'],
            ['name' => 'Approved'],
            ['name' => 'Beneficiary Confirmation'],
            ['name' => 'To Deliver'],
            ['name' => 'Delivered'],
            ['name' => 'Denied'],
            ['name' => 'Closed'],
        ];

        DB::table('request_statuses')->insert($status);
    }
}
