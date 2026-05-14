<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            'firstName' => 'Zced Rick',
            'middleName' => 'Buduan',
            'lastName' => 'Tabladillo',
            'department_id' => 12,
            'email' => 'zcedbuduan@gmail.com',
            'password' => Hash::make('zcedzced'),
        ]);
        $user->assignRole('Admin');
    }
}
