<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\Program;
use App\Models\User;
use App\Services\User\StockLedgerService;
use App\Support\StockMovementType;
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
    ->use(RefreshDatabase::class)
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

/**
 * @param  list<string>|string  $only
 * @return array<string, string>
 */
function inertiaPartialHeaders(string $component, array|string $only): array
{
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => is_array($only) ? implode(',', $only) : $only,
    ];

    if (config('app.asset_url')) {
        $headers['X-Inertia-Version'] = hash('xxh128', (string) config('app.asset_url'));

        return $headers;
    }

    foreach (['build/manifest.json', 'mix-manifest.json'] as $relative) {
        $manifest = public_path($relative);

        if (is_file($manifest)) {
            $headers['X-Inertia-Version'] = hash_file('xxh128', $manifest);

            return $headers;
        }
    }

    return $headers;
}

function createBeneficiaryDepartmentUser(): array
{
    $department = Department::create(['name' => 'Department A']);

    $userId = DB::table('users')->insertGetId([
        'firstName' => 'Test',
        'lastName' => 'User',
        'email' => 'test-'.uniqid().'@example.com',
        'email_verified_at' => now(),
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
    $provinceId = DB::table('address_provinces')->insertGetId([
        'name' => 'Test Province-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cityId = DB::table('address_cities')->insertGetId([
        'name' => 'Test City',
        'zipcode' => '1000',
        'excel_name' => 'Test City',
        'address_province_id' => $provinceId,
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

/**
 * @return array{address_province_id: int, address_city_id: int, address_barangay_id: int}
 */
function addressCascadePayload(int $barangayId): array
{
    $city = DB::table('address_barangays')
        ->join('address_cities', 'address_cities.id', '=', 'address_barangays.address_city_id')
        ->where('address_barangays.id', $barangayId)
        ->first(['address_barangays.address_city_id', 'address_cities.address_province_id']);

    return [
        'address_province_id' => (int) $city->address_province_id,
        'address_city_id' => (int) $city->address_city_id,
        'address_barangay_id' => $barangayId,
    ];
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

function seedProgramStock(Program $program, Item $item, int $quantity, ?User $user = null): void
{
    if (! $program->item()->where('items.id', $item->id)->exists()) {
        $program->item()->attach($item->id);
    }

    $user ??= User::query()->where('department_id', $program->department_id)->first()
        ?? User::factory()->create(['department_id' => $program->department_id]);

    $ledger = app(StockLedgerService::class);

    $ledger->receive($item, $user, [
        'quantity' => $quantity,
        'type' => StockMovementType::OpeningBalance,
    ]);

    $ledger->allocate($item, $user, [
        'program_id' => $program->id,
        'quantity' => $quantity,
    ]);
}
