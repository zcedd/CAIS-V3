<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
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
            ->where('summary.total_requests', 1)
            ->has('requestStatusChart')
            ->has('deliveredItemsChart')
            ->has('programsTable', 2)
            ->has('filterOptions.programs', 2)
            ->where('filters.year', [(string) now()->year])
            ->where('filters.quarter', [])
            ->where('filters.program', []));
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
            ->where('summary.total_requests', 1)
            ->where('filters.program', [(string) $program->id]));
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
            ->where('summary.total_requests', 1)
            ->where('filters.year', [(string) now()->year]));
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
            ->where('summary.total_requests', 1)
            ->where('filters.year', ['2024']));
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
            ->where('summary.total_requests', 1)
            ->where('filters.year', ['2024'])
            ->where('filters.quarter', ['1']));
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
            ->where('summary.total_requests', 1)
            ->where('filters.year', ['2024'])
            ->where('filters.quarter', ['1']));
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
            ->where('summary.total_requests', 1)
            ->where('filters.sex', ['Male']));
});

test('request status chart counts each assistance once using latest status', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individual = Individual::factory()->create(['sex' => 'Male']);
    $assistance = createAssistanceForIndividual($program, $individual, $item);

    $requestStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'In Progress',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $olderSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Awaiting Review',
        'request_status_id' => $requestStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $latestSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Under Verification',
        'request_status_id' => $requestStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
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

    $this->actingAs($user)
        ->get(route('user.dashboard.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_requests', 1)
            ->where('requestStatusChart', fn (array $chart): bool => collect($chart)->sum('count') === 1));
});

test('delivered requests count assistances with a delivered status in history even when latest status is closed', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individual = Individual::factory()->create(['sex' => 'Male']);
    $assistance = createAssistanceForIndividual($program, $individual, $item, isReceived: true, quantity: 5);

    $deliveredStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Delivered',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $closedStatusId = DB::table('request_statuses')->insertGetId([
        'name' => 'Closed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $deliveredSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Successfully Delivered',
        'request_status_id' => $deliveredStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $closedSubStatusId = DB::table('request_sub_statuses')->insertGetId([
        'name' => 'Closed after Resolution',
        'request_status_id' => $closedStatusId,
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
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

    $this->actingAs($user)
        ->get(route('user.dashboard.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.delivered_requests', 1)
            ->where('summary.total_delivered_items', 5)
            ->where('summary.total_requests', 1));
});

test('delivered items are only counted for assistances with a delivered status', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'item' => $item] = createDashboardFixtures();

    $individual = Individual::factory()->create(['sex' => 'Male']);

    createAssistanceForIndividual($program, $individual, $item, isReceived: true, quantity: 5);
    createAssistanceForIndividual($program, $individual, $item, isReceived: false, quantity: 10);

    $this->actingAs($user)
        ->get(route('user.dashboard.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_delivered_items', 5)
            ->where('summary.total_requests', 2));
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
    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Female']), $item, dateRequested: '2024-07-15');
    createAssistanceForIndividual($program, Individual::factory()->create(['sex' => 'Male']), $item, dateRequested: '2025-02-15');

    $summary = app(DashboardService::class)->summary($department, [
        'year' => [2024],
        'quarter' => ['1'],
    ]);

    expect($summary['total_requests'])->toBe(1);
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
