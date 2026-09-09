<?php

use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use App\Support\RoleName;
use App\Support\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff without the assistance role cannot create or delete assistance', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::Program->value,
    );
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $mode = ModeOfRequest::create(['name' => 'Walk In']);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('user.programs.assistances.store', [
            'department' => $department->slug,
            'program' => $program->id,
        ]), [
            'beneficiary_id' => $beneficiary->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateString(),
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('user.programs.assistances.destroy', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertForbidden();
});

test('staff with the assistance role can delete assistance in their department', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::Assistance->value,
        RoleName::Program->value,
    );
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $mode = ModeOfRequest::create(['name' => 'Walk In']);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->from(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->delete(route('user.programs.assistances.destroy', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertRedirect();

    expect(Assistance::query()->find($assistance->id))->toBeNull();
});

test('staff cannot create assistance for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);
    $user = grantResourceRoles(
        User::factory()->create(['department_id' => $departmentA->id]),
        RoleName::Assistance->value,
        RoleName::Program->value,
    );
    $program = Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $this->actingAs($user)
        ->post(route('user.programs.assistances.store', [
            'department' => $departmentB->slug,
            'program' => $program->id,
        ]), [
            'beneficiary_id' => 1,
            'mode_of_request_id' => 1,
            'recorded_at' => now()->toDateString(),
        ])
        ->assertForbidden();
});

test('staff without the beneficiary role cannot create a beneficiary', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    grantResourceRoles($user, RoleName::Assistance->value);

    $this->actingAs($user)
        ->post(route('user.beneficiaries.individuals.store', [
            'department' => $department->slug,
        ]), [
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'sex' => 'Male',
        ])
        ->assertForbidden();
});

test('staff with the beneficiary role can create an individual beneficiary', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    grantResourceRoles($user, RoleName::Beneficiary->value);
    seedCivilStatusAndIdentification();
    $barangayId = createAddressBarangay();

    $this->actingAs($user)
        ->post(route('user.beneficiaries.individuals.store', [
            'department' => $department->slug,
        ]), [
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'sex' => 'Male',
            ...addressCascadePayload($barangayId),
        ])
        ->assertRedirect();

    expect(Beneficiary::query()->where('name', 'Juan Cruz')->exists())->toBeTrue();
});

test('staff with the workflow role can create a workflow', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::Workflow->value,
    );

    $this->actingAs($user)
        ->from(route('user.workflows.index', $department->slug))
        ->post(route('user.workflows.store', $department->slug), [
            'name' => 'Walk-in relief',
            'template' => WorkflowTemplate::WalkIn->value,
            'is_default' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('super admin can act in another department without resource roles', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);
    $user = assignSuperAdminRole(User::factory()->create([
        'department_id' => $departmentA->id,
    ]));
    $program = Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $this->actingAs($user)
        ->get(route('user.workflows.index', $departmentB->slug))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('user.programs.assistances.export', [
            'department' => $departmentB->slug,
            'program' => $program->id,
        ]))
        ->assertSuccessful();
});
