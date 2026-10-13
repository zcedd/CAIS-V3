<?php

use App\Enums\ItemKind;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Organization;
use App\Models\Program;
use App\Models\UnspscCode;
use App\Models\User;
use App\Services\User\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     department: Department,
 *     user: User,
 *     program: Program,
 *     programB: Program,
 *     item: Item
 * }
 */
function createDashboardFixtures(): array
{
    $department = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $programB = Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    User::factory()->create([
        'department_id' => $departmentB->id,
    ]);

    return compact('department', 'departmentB', 'user', 'program', 'programB', 'item');
}

function createAssistanceForIndividual(
    Program $program,
    Individual $individual,
    Item $item,
    bool $isReceived = false,
    int $quantity = 2,
    ?string $dateRequested = null,
): Assistance {
    $beneficiary = Beneficiary::query()->firstOrCreate(
        [
            'beneficiable_type' => Individual::class,
            'beneficiable_id' => $individual->id,
        ],
        [
            'cais_number' => $individual->cais_number,
            'name' => $individual->fullName(),
        ],
    );

    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => $dateRequested ?? now()->toDateString(),
        'date_delivered' => $isReceived ? now()->toDateString() : null,
        'was_delivered' => $isReceived,
        'user_id' => User::factory()->create([
            'department_id' => $program->department_id,
        ])->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'is_received' => $isReceived,
    ]);

    return $assistance;
}

function dashboardBarangay(string $city): int
{
    $cityId = DB::table('address_cities')->insertGetId([
        'name' => $city,
        'zipcode' => 2900,
        'excel_name' => $city,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) DB::table('address_barangays')->insertGetId([
        'name' => $city.' Barangay',
        'address_city_id' => $cityId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('guests cannot view the department dashboard', function () {
    $response = $this->get(route('user.dashboard.index', ['department' => 'any-department']));

    $response->assertRedirect(route('login'));
});

test('authenticated users cannot view another departments dashboard', function () {
    ['departmentB' => $departmentB, 'user' => $user] = createDashboardFixtures();

    $this->actingAs($user)
        ->get(route('user.dashboard.index', ['department' => $departmentB->slug]))
        ->assertForbidden();
});

test('department users can view the dashboard with expected props', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $male = Individual::factory()->create(['sex' => 'Male']);
    createAssistanceForIndividual($program, $male, $item);

    $this->actingAs($user)
        ->get(route('user.dashboard.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/dashboard/index')
            ->where('department.slug', $department->slug)
            ->loadDeferredProps(['kpis', 'filters', 'charts', 'programs'], fn ($reload) => $reload
                ->where('summary.total_requests', 1)
                ->where('summary.cash_disbursed', 0)
                ->where('summary.goods_released', 0)
                ->has('cityChart')
                ->has('municipalityMap')
                ->has('requestStatusChart')
                ->has('deliveredItemsChart')
                ->has('unspscReleasedChart')
                ->has('programsTable', 2)
                ->has('filterOptions.programs', 2)
                ->where('filters.year', [(string) now()->year])
                ->where('filters.quarter', [])
                ->where('filters.program', [])));
});

test('municipality map counts delivered beneficiaries in their city', function () {
    [
        'department' => $department,
        'departmentB' => $departmentB,
        'user' => $user,
        'program' => $program,
        'item' => $item,
    ] = createDashboardFixtures();

    $laoagBarangay = dashboardBarangay('Laoag');
    $batacBarangay = dashboardBarangay('Batac');

    $laoagPerson = Individual::factory()->create([
        'address_barangay_id' => $laoagBarangay,
    ]);
    $batacPerson = Individual::factory()->create([
        'address_barangay_id' => $batacBarangay,
    ]);
    $otherPerson = Individual::factory()->create([
        'address_barangay_id' => $laoagBarangay,
    ]);

    $cash = Item::create([
        'name' => 'Financial assistance',
        'kind' => ItemKind::Cash,
        'department_id' => $department->id,
        'item_unit_measurement_id' => $item->item_unit_measurement_id,
    ]);

    $first = createAssistanceForIndividual($program, $laoagPerson, $item, true, 2);
    createAssistanceForIndividual($program, $laoagPerson, $item, true, 3);
    AssistanceItem::create([
        'assistance_id' => $first->id,
        'item_id' => $cash->id,
        'quantity' => 1500,
        'is_received' => true,
    ]);
    createAssistanceForIndividual($program, $batacPerson, $item, false, 9);

    $otherProgram = Program::create([
        'name' => 'Other Department Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    createAssistanceForIndividual($otherProgram, $otherPerson, $item, true, 8);

    $paoayBarangay = dashboardBarangay('Paoay');
    $organization = Organization::factory()->create([
        'address_barangay_id' => $paoayBarangay,
    ]);
    $orgAssistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $organization->beneficiaryRecord->id,
        'mode_of_request_id' => ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In'])->id,
        'date_requested' => now()->toDateString(),
        'date_delivered' => now()->toDateString(),
        'was_delivered' => true,
        'user_id' => $user->id,
    ]);
    AssistanceItem::create([
        'assistance_id' => $orgAssistance->id,
        'item_id' => $item->id,
        'quantity' => 4,
        'is_received' => true,
    ]);

    $this->actingAs($user)
        ->get(route('user.dashboard.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['charts'], fn (Assert $reload) => $reload
                ->where('municipalityMap', function ($rows): bool {
                    $byCity = $rows->keyBy('city');

                    expect($byCity->get('Laoag'))->toMatchArray([
                        'beneficiaries' => 1,
                        'delivered_requests' => 2,
                        'goods_released' => 5,
                        'cash_disbursed' => 1500,
                    ]);
                    expect($byCity->get('Batac'))->toMatchArray([
                        'beneficiaries' => 0,
                        'delivered_requests' => 0,
                        'goods_released' => 0,
                        'cash_disbursed' => 0,
                    ]);
                    expect($byCity->get('Paoay'))->toMatchArray([
                        'beneficiaries' => 1,
                        'delivered_requests' => 1,
                        'goods_released' => 4,
                        'cash_disbursed' => 0,
                    ]);

                    return true;
                })));
});

test('program filter reduces total requests on the dashboard', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'programB' => $programB, 'item' => $item] = createDashboardFixtures();

    $individualA = Individual::factory()->create(['sex' => 'Male']);
    $individualB = Individual::factory()->create(['sex' => 'Female']);

    createAssistanceForIndividual($program, $individualA, $item);
    createAssistanceForIndividual($programB, $individualB, $item);

    $this->actingAs($user)
        ->get(route('user.dashboard.index', [
            'department' => $department->slug,
            'program' => [$program->id],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['kpis', 'filters'], fn ($reload) => $reload
                ->where('summary.total_requests', 1)
                ->where('filters.program', [(string) $program->id])));
});

test('dashboard defaults to the current year when no year filter is provided', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $currentYearIndividual = Individual::factory()->create(['sex' => 'Male']);
    $previousYearIndividual = Individual::factory()->create(['sex' => 'Female']);

    createAssistanceForIndividual($program, $currentYearIndividual, $item, dateRequested: now()->toDateString());
    createAssistanceForIndividual($program, $previousYearIndividual, $item, dateRequested: now()->subYear()->toDateString());

    $this->actingAs($user)
        ->get(route('user.dashboard.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['kpis', 'filters'], fn ($reload) => $reload
                ->where('summary.total_requests', 1)
                ->where('filters.year', [(string) now()->year])));
});

test('year filter returns only assistances requested in the selected year', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individualA = Individual::factory()->create(['sex' => 'Male']);
    $individualB = Individual::factory()->create(['sex' => 'Female']);

    createAssistanceForIndividual($program, $individualA, $item, dateRequested: '2024-02-15');
    createAssistanceForIndividual($program, $individualB, $item, dateRequested: '2025-02-15');

    $this->actingAs($user)
        ->get(route('user.dashboard.index', [
            'department' => $department->slug,
            'year' => [2024],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['kpis', 'filters'], fn ($reload) => $reload
                ->where('summary.total_requests', 1)
                ->where('filters.year', ['2024'])));
});

test('year and quarter filters combine to narrow assistances', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individualA = Individual::factory()->create(['sex' => 'Male']);
    $individualB = Individual::factory()->create(['sex' => 'Female']);
    $individualC = Individual::factory()->create(['sex' => 'Male']);

    createAssistanceForIndividual($program, $individualA, $item, dateRequested: '2024-02-15');
    createAssistanceForIndividual($program, $individualB, $item, dateRequested: '2025-02-15');
    createAssistanceForIndividual($program, $individualC, $item, dateRequested: '2024-07-15');

    $this->actingAs($user)
        ->get(route('user.dashboard.index', [
            'department' => $department->slug,
            'year' => [2024],
            'quarter' => ['1'],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['kpis', 'filters'], fn ($reload) => $reload
                ->where('summary.total_requests', 1)
                ->where('filters.year', ['2024'])
                ->where('filters.quarter', ['1'])));
});

test('quarter filter returns only assistances requested in the selected quarter', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individualA = Individual::factory()->create(['sex' => 'Male']);
    $individualB = Individual::factory()->create(['sex' => 'Female']);

    createAssistanceForIndividual($program, $individualA, $item, dateRequested: '2024-02-15');
    createAssistanceForIndividual($program, $individualB, $item, dateRequested: '2024-07-15');

    $this->actingAs($user)
        ->get(route('user.dashboard.index', [
            'department' => $department->slug,
            'year' => [2024],
            'quarter' => ['1'],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['kpis', 'filters'], fn ($reload) => $reload
                ->where('summary.total_requests', 1)
                ->where('filters.year', ['2024'])
                ->where('filters.quarter', ['1'])));
});

test('sex filter returns only matching individual assistances', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $male = Individual::factory()->create(['sex' => 'Male']);
    $female = Individual::factory()->create(['sex' => 'Female']);

    createAssistanceForIndividual($program, $male, $item);
    createAssistanceForIndividual($program, $female, $item);

    $this->actingAs($user)
        ->get(route('user.dashboard.index', [
            'department' => $department->slug,
            'sex' => ['Male'],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['kpis', 'filters'], fn ($reload) => $reload
                ->where('summary.total_requests', 1)
                ->where('filters.sex', ['Male'])));
});

test('request status chart counts each assistance once using latest status', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individual = Individual::factory()->create(['sex' => 'Male']);
    $assistance = createAssistanceForIndividual($program, $individual, $item);

    $requestStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'In Progress',
    ]);

    $olderSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Awaiting Review',
        'request_status_id' => $requestStatusId,
        'description' => null,
    ]);

    $latestSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Under Verification',
        'request_status_id' => $requestStatusId,
        'description' => null,
    ]);

    DB::table('assistance_request_sub_status')->insert([
        [
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $olderSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-02-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $latestSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-02-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
    ]);

    DB::table('assistances')->where('id', $assistance->id)->update([
        'current_request_sub_status_id' => $latestSubStatusId,
        'current_status_recorded_at' => '2024-02-02 10:00:00',
        'was_delivered' => false,
    ]);

    $chart = app(DashboardService::class)->requestStatusChart($department, []);

    expect(collect($chart)->sum('count'))->toBe(1);
});

test('delivered requests count assistances with a delivered status in history even when latest status is closed', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individual = Individual::factory()->create(['sex' => 'Male']);
    $assistance = createAssistanceForIndividual($program, $individual, $item, isReceived: true, quantity: 5);

    $deliveredStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Delivered',
    ]);

    $closedStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Closed',
    ]);

    $deliveredSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Successfully Delivered',
        'request_status_id' => $deliveredStatusId,
        'description' => null,
    ]);

    $closedSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Closed after Resolution',
        'request_status_id' => $closedStatusId,
        'description' => null,
    ]);

    DB::table('assistance_request_sub_status')->insert([
        [
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $deliveredSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-02-01 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
        [
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $closedSubStatusId,
            'remark' => null,
            'recorded_at' => '2024-02-02 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ],
    ]);

    DB::table('assistances')->where('id', $assistance->id)->update([
        'current_request_sub_status_id' => $closedSubStatusId,
        'current_status_recorded_at' => '2024-02-02 10:00:00',
        'was_delivered' => true,
        'date_delivered' => '2024-02-01',
    ]);

    $summary = app(DashboardService::class)->summary($department, []);

    expect($summary['delivered_requests'])->toBe(1)
        ->and($summary['total_delivered_items'])->toBe(5)
        ->and($summary['total_requests'])->toBe(1);
});

test('delivered items are only counted for assistances with a delivered status', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individual = Individual::factory()->create(['sex' => 'Male']);

    createAssistanceForIndividual($program, $individual, $item, isReceived: true, quantity: 5);
    createAssistanceForIndividual($program, $individual, $item, isReceived: false, quantity: 10);

    $summary = app(DashboardService::class)->summary($department, []);

    expect($summary['total_delivered_items'])->toBe(5)
        ->and($summary['total_requests'])->toBe(2);
});

test('delivered items chart counts delivery lines per item not quantities', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individual = Individual::factory()->create(['sex' => 'Male']);

    createAssistanceForIndividual($program, $individual, $item, isReceived: true, quantity: 5);
    createAssistanceForIndividual($program, $individual, $item, isReceived: true, quantity: 3);

    $chart = app(DashboardService::class)->deliveredItemsChart($department, []);

    expect($chart)->toHaveCount(1)
        ->and($chart[0]['item'])->toBe('Rice')
        ->and($chart[0]['count'])->toBe(2);
});

test('unspsc released chart groups received quantity by segment', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $rice = UnspscCode::query()->where('code', '50221101')->firstOrFail();
    $item->update(['unspsc_code_id' => $rice->id]);

    $individual = Individual::factory()->create(['sex' => 'Male']);
    createAssistanceForIndividual($program, $individual, $item, isReceived: true, quantity: 7);

    $chart = app(DashboardService::class)->unspscReleasedChart($department, []);

    expect($chart)->not->toBeEmpty()
        ->and($chart[0]['quantity'])->toBe(7)
        ->and($chart[0]['code'])->toBe('50000000');
});

test('programs table shows only the 10 latest programs', function () {
    ['department' => $department] = createDashboardFixtures();

    foreach (range(1, 12) as $index) {
        Program::create([
            'name' => "Program {$index}",
            'descriptions' => 'Details',
            'start_at' => now()->toDateString(),
            'end_at' => null,
            'department_id' => $department->id,
            'is_closed' => false,
            'is_organization' => false,
        ]);
    }

    $programsTable = app(DashboardService::class)->programsTable($department, []);

    expect($programsTable)->toHaveCount(10)
        ->and($programsTable[0]['name'])->toBe('Program 12')
        ->and($programsTable[9]['name'])->toBe('Program 3');
});

test('recent beneficiaries lists the latest request for each person in the department', function () {
    [
        'department' => $department,
        'departmentB' => $departmentB,
        'user' => $user,
        'program' => $program,
        'programB' => $programB,
        'item' => $item,
    ] = createDashboardFixtures();

    $barangayId = dashboardBarangay('Laoag');

    $latestPerson = Individual::factory()->create([
        'first_name' => 'Latest',
        'middle_name' => null,
        'last_name' => 'Person',
        'suffix' => null,
        'address_barangay_id' => $barangayId,
    ]);
    $earlierPerson = Individual::factory()->create([
        'first_name' => 'Earlier',
        'middle_name' => null,
        'last_name' => 'Person',
        'suffix' => null,
    ]);
    $otherPerson = Individual::factory()->create([
        'first_name' => 'Other',
        'middle_name' => null,
        'last_name' => 'Department',
        'suffix' => null,
    ]);

    $year = now()->year;

    createAssistanceForIndividual($program, $latestPerson, $item, dateRequested: ($year - 1).'-01-15');
    createAssistanceForIndividual($program, $latestPerson, $item, dateRequested: "{$year}-06-01");
    createAssistanceForIndividual($programB, $latestPerson, $item, dateRequested: "{$year}-06-01");
    createAssistanceForIndividual($program, $earlierPerson, $item, dateRequested: "{$year}-03-01");

    $otherProgram = Program::create([
        'name' => 'Other Department Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    createAssistanceForIndividual($otherProgram, $otherPerson, $item, dateRequested: "{$year}-12-01");

    $this->actingAs($user)
        ->get(route('user.dashboard.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('beneficiaries', fn (Assert $reload) => $reload
                ->has('recentBeneficiaries', 2)
                ->where('recentBeneficiaries.0.name', 'Latest Person')
                ->where('recentBeneficiaries.0.cais_number', $latestPerson->cais_number)
                ->where('recentBeneficiaries.0.type', 'individual')
                ->where('recentBeneficiaries.0.barangay', 'Laoag Barangay')
                ->where('recentBeneficiaries.0.city', 'Laoag')
                ->where('recentBeneficiaries.0.program', 'Beta Program')
                ->where('recentBeneficiaries.0.status', 'Unrequested')
                ->where('recentBeneficiaries.0.date_requested', "{$year}-06-01")
                ->where('recentBeneficiaries.1.name', 'Earlier Person')
                ->where('recentBeneficiaries.1.program', 'Alpha Program')
                ->where('recentBeneficiaries.1.date_requested', "{$year}-03-01")));
});

test('recent beneficiaries respect the dashboard year filter', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $currentPerson = Individual::factory()->create([
        'first_name' => 'Current',
        'middle_name' => null,
        'last_name' => 'Person',
        'suffix' => null,
    ]);
    $previousPerson = Individual::factory()->create([
        'first_name' => 'Previous',
        'middle_name' => null,
        'last_name' => 'Person',
        'suffix' => null,
    ]);

    createAssistanceForIndividual($program, $currentPerson, $item, dateRequested: '2025-02-01');
    createAssistanceForIndividual($program, $previousPerson, $item, dateRequested: '2024-02-01');

    $rows = app(DashboardService::class)->recentBeneficiaries($department, [
        'year' => [2025],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Current Person')
        ->and($rows[0]['date_requested'])->toBe('2025-02-01');
});

test('recent beneficiaries returns at most 10 people', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    foreach (range(1, 11) as $index) {
        $person = Individual::factory()->create([
            'first_name' => 'Person',
            'middle_name' => null,
            'last_name' => (string) $index,
            'suffix' => null,
        ]);

        createAssistanceForIndividual(
            $program,
            $person,
            $item,
            dateRequested: sprintf('2025-01-%02d', $index),
        );
    }

    $rows = app(DashboardService::class)->recentBeneficiaries($department, []);

    expect($rows)->toHaveCount(10)
        ->and($rows[0]['name'])->toBe('Person 11')
        ->and($rows[9]['name'])->toBe('Person 2');
});

test('summary counts repeat and one-time beneficiaries in sql', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $repeatIndividual = Individual::factory()->create(['sex' => 'Male']);
    $oneTimeIndividual = Individual::factory()->create(['sex' => 'Female']);

    createAssistanceForIndividual($program, $repeatIndividual, $item);
    createAssistanceForIndividual($program, $repeatIndividual, $item);
    createAssistanceForIndividual($program, $oneTimeIndividual, $item);

    $summary = app(DashboardService::class)->summary($department, [
        'year' => [now()->year],
    ]);

    expect($summary['repeat_beneficiaries'])->toBe(1)
        ->and($summary['one_time_beneficiaries'])->toBe(1)
        ->and($summary['unique_beneficiaries'])->toBe(2);
});

test('filter options return distinct years without loading all request dates', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Male']), $item, dateRequested: '2023-05-01');
    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Male']), $item, dateRequested: '2023-08-01');
    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Female']), $item, dateRequested: '2024-01-15');

    $options = app(DashboardService::class)->filterOptions($department);

    expect(collect($options['year'])->pluck('value')->all())->toBe(['2024', '2023']);
});

test('apply dashboard filters use date ranges for selected years and quarters', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Male']), $item, dateRequested: '2024-02-15');
    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Female']), $item, dateRequested: '2024-03-31 18:30:00');
    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Female']), $item, dateRequested: '2024-07-15');
    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Male']), $item, dateRequested: '2025-02-15');

    $summary = app(DashboardService::class)->summary($department, [
        'year' => [2024],
        'quarter' => ['1'],
    ]);

    expect($summary['total_requests'])->toBe(2);
});

test('top barangays returns an empty list when the department has no requests', function () {
    ['department' => $department] = createDashboardFixtures();

    expect(app(DashboardService::class)->topBarangays($department, []))->toBe([]);
});

test('top barangays groups individual and organization requests by address name', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $barangayId = createAddressBarangay();
    DB::table('address_barangays')->where('id', $barangayId)->update(['name' => 'Poblacion']);

    $individual = Individual::factory()->create([
        'sex' => 'Male',
        'address_barangay_id' => $barangayId,
    ]);
    $blank = Individual::factory()->create(['sex' => 'Female']);
    DB::table('address_barangays')->where('id', $blank->address_barangay_id)->update(['name' => '']);

    $organization = Organization::factory()->create([
        'address_barangay_id' => $barangayId,
    ]);

    createAssistanceForIndividual($program, $individual, $item);
    createAssistanceForIndividual($program, $individual, $item);
    createAssistanceForIndividual($program, $blank, $item);

    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $organization->beneficiaryRecord->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => User::factory()->create([
            'department_id' => $program->department_id,
        ])->id,
    ]);

    expect(app(DashboardService::class)->topBarangays($department, [
        'year' => [now()->year],
    ]))->toBe([
        ['barangay' => 'Poblacion', 'count' => 3],
        ['barangay' => 'Unspecified', 'count' => 1],
    ]);
});

test('mode of request chart groups missing names as unspecified', function () {
    ['department' => $department, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $blank = ModeOfRequest::query()->create(['name' => '']);
    $individual = Individual::factory()->create(['sex' => 'Male']);

    createAssistanceForIndividual($program, $individual, $item);
    $blankAssistance = createAssistanceForIndividual($program, $individual, $item);
    $missingAssistance = createAssistanceForIndividual($program, $individual, $item);
    $blankAssistance->update(['mode_of_request_id' => $blank->id]);
    $missingAssistance->update(['mode_of_request_id' => null]);

    expect(app(DashboardService::class)->modeOfRequestChart($department, [
        'year' => [now()->year],
    ]))->toBe([
        ['label' => 'Unspecified', 'count' => 2],
        ['label' => 'Walk In', 'count' => 1],
    ]);
});

test('global dashboard redirects users with a department to the department dashboard', function () {
    ['department' => $department, 'user' => $user] = createDashboardFixtures();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.dashboard.index', ['department' => $department->slug]));
});

test('global dashboard shows empty state when user has no department', function () {
    $user = User::factory()->create([
        'department_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('noDepartment', true));
});
