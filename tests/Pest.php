<?php

use App\Enums\RequestStatusCode;
use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\DisbursementBatch;
use App\Models\Individual;
use App\Models\Item;
use App\Models\Program;
use App\Models\User;
use App\Services\User\StockLedgerService;
use App\Services\Workflow\RequestStatusCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
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

    return [
        'department' => $department,
        'user' => grantResourceRoles($user),
    ];
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
        'type' => StockMovementType::OpeningBalance->value,
    ]);

    $ledger->allocate($item, $user, [
        'program_id' => $program->id,
        'quantity' => $quantity,
    ]);
}

function catalogReasonId(RequestSubStatusCode $code): int
{
    return app(RequestStatusCatalog::class)->reasonId($code);
}

function catalogParentId(RequestStatusCode $code): int
{
    return app(RequestStatusCatalog::class)->parentId($code);
}

function seedRolesAndPermissions(): void
{
    if (app()->bound('cais.roles-permissions-seeded')) {
        return;
    }

    test()->seed(RolePermissionSeeder::class);
    app()->instance('cais.roles-permissions-seeded', true);
}

function grantResourceRoles(User $user, string ...$roles): User
{
    seedRolesAndPermissions();

    $roleNames = $roles === []
        ? RoleName::resourceRoleValues()
        : $roles;

    $user->syncRoles($roleNames);

    return $user->fresh() ?? $user;
}

function withoutResourceRoles(User $user): User
{
    $user->syncRoles([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh() ?? $user;
}

/**
 * @return array<string, mixed>
 */
function disbursementFixture(string $kind = 'cash', int $requests = 1): array
{
    $department = Department::create(['name' => 'Social Welfare '.uniqid()]);
    $creator = User::factory()->create(['department_id' => $department->id]);
    $creator->syncRoles([RoleName::Program->value]);
    $governor = User::factory()->create(['department_id' => null]);
    $governor->syncRoles([RoleName::Executive->value]);
    $head = User::factory()->create(['department_id' => $department->id]);
    $head->syncRoles([RoleName::DepartmentHead->value]);
    $officer = User::factory()->create(['department_id' => $department->id]);
    $officer->syncRoles([RoleName::ReleasingOfficer->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $program = Program::factory()->forDepartment($department)->create();
    $item = Item::factory()->forDepartment($department)->when(
        $kind === 'cash',
        fn ($factory) => $factory->cash(),
        fn ($factory) => $factory,
    )->create(['name' => $kind === 'cash' ? 'Cash aid' : 'Rice']);
    $program->item()->attach($item->id);

    $assistances = [];

    for ($index = 0; $index < $requests; $index++) {
        $individual = Individual::factory()->create();
        $assistance = Assistance::query()->create([
            'program_id' => $program->id,
            'beneficiary_id' => $individual->beneficiaryRecord->id,
            'date_requested' => now()->toDateString(),
            'user_id' => $creator->id,
            'current_request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Verified),
        ]);
        AssistanceItem::query()->create([
            'assistance_id' => $assistance->id,
            'item_id' => $item->id,
            'quantity' => $kind === 'cash' ? 500 + ($index * 100) : 4,
            'requested_quantity' => $kind === 'cash' ? 500 + ($index * 100) : 4,
        ]);
        $assistances[] = $assistance;
    }

    return compact('department', 'creator', 'governor', 'head', 'officer', 'program', 'item', 'assistances');
}

/**
 * @param  array<string, mixed>  $fixture
 * @return array<string, mixed>
 */
function disbursementPayload(array $fixture, string $mode = 'batch'): array
{
    return [
        'program_id' => $fixture['program']->id,
        'title' => 'Barangay release',
        'description' => 'Scheduled payout',
        'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'venue' => 'Covered court',
        'approval_mode' => $mode,
        'assistance_ids' => collect($fixture['assistances'])->pluck('id')->all(),
    ];
}

/**
 * @param  array<string, mixed>  $fixture
 */
function storeDisbursement(array $fixture, string $mode = 'batch'): DisbursementBatch
{
    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.store', $fixture['department']), disbursementPayload($fixture, $mode))
        ->assertRedirect();

    return DisbursementBatch::query()->latest('id')->firstOrFail();
}

/**
 * @param  array<string, mixed>  $fixture
 */
function submitDisbursement(array $fixture, DisbursementBatch $batch): void
{
    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.validate', [$fixture['department'], $batch]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    test()->actingAs($fixture['creator'])
        ->post(route('user.disbursements.submit', [$fixture['department'], $batch]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
}

function assignSuperAdminRole(User $user): User
{
    seedRolesAndPermissions();
    $user->syncRoles([RoleName::SuperAdmin->value]);

    return $user->fresh() ?? $user;
}
