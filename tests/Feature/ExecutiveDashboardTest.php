<?php

use App\Enums\RoleName;
use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('the executive dashboard totals every department and can filter to one', function () {
    $this->seed(RolePermissionSeeder::class);

    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);
    $executive = User::factory()->create(['department_id' => $departmentA->id]);
    $executive->syncRoles([RoleName::Governor->value]);

    $programA = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $departmentA->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $programB = Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    executiveDashboardAssistance($programA);
    executiveDashboardAssistance($programB);

    $this->actingAs($executive)
        ->get(route('executive.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('executive/dashboard/index')
            ->loadDeferredProps(['kpis', 'filters', 'programs'], fn (Assert $reload) => $reload
                ->where('summary.total_requests', 2)
                ->where('filters.department', [])
                ->has('filterOptions.departments', 2)
                ->has('programsTable', 2)));

    $this->actingAs($executive)
        ->get(route('executive.dashboard', [
            'department' => [$departmentA->id],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['kpis', 'filters'], fn (Assert $reload) => $reload
                ->where('summary.total_requests', 1)
                ->where('filters.department', [(string) $departmentA->id])
                ->has('filterOptions.programs', 1)));
});

test('a department user cannot open the executive dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    $this->actingAs($user)
        ->get(route('executive.dashboard'))
        ->assertForbidden();
});

function executiveDashboardAssistance(Program $program): void
{
    $individual = Individual::factory()->create();
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

    Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'was_delivered' => false,
        'user_id' => User::factory()->create([
            'department_id' => $program->department_id,
        ])->id,
    ]);
}
