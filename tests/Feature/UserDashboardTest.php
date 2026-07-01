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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
): Assistance {
    $beneficiary = Beneficiary::create([
        'cais_number' => $individual->cais_number,
        'name' => $individual->fullName(),
        'beneficiable_type' => Individual::class,
        'beneficiable_id' => $individual->id,
    ]);

    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'date_delivered' => $isReceived ? now()->toDateString() : null,
        'user_id' => User::factory()->create()->id,
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

test('delivered items sum respects is received flag', function () {
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
