<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function createBeneficiaryDepartmentUser(): array
{
    $department = Department::create(['name' => 'Department A']);

    $userId = DB::table('users')->insertGetId([
        'firstName' => 'Test',
        'lastName' => 'User',
        'email' => 'test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'department_id' => $department->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::query()->findOrFail($userId);

    return compact('department', 'user');
}

function createAddressBarangay(): int
{
    $cityId = DB::table('address_cities')->insertGetId([
        'name' => 'Test City',
        'zipcode' => '1000',
        'excel_name' => 'Test City',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('address_barangays')->insertGetId([
        'name' => 'Test Barangay',
        'address_city_id' => $cityId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedCivilStatusAndIdentification(): void
{
    DB::table('civil_statuses')->insert([
        'name' => 'Single',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('identifications')->insert([
        ['name' => 'National ID', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'RSBSA ID', 'created_at' => now(), 'updated_at' => now()],
    ]);
}
