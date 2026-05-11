<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->call(departmentSeeder::class);
        $this->call(AddrsCitySeeder::class);
        $this->call(AddrsBrgySeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(IdentificationSeeder::class);
        $this->call(ProjectSeeder::class);
        $this->call(ModeOfRequestSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(CivilStatusSeeder::class);
        $this->call(SectorSeeder::class);
        $this->call(RequestStatusSeeder::class);
        $this->call(RequestSubStatusSeeder::class);
    }
}
