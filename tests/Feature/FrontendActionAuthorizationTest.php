<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('program index shares view access without create permission for a releasing officer', function () {
    $department = Department::create(['name' => 'Department A']);
    $officer = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::ReleasingOfficer->value,
    );

    $this->actingAs($officer)
        ->get(route('user.programs.index', $department))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $permissions = $page->toArray()['props']['auth']['permissions'];

            expect($permissions)
                ->toContain(PermissionName::ProgramViewAny->value)
                ->toContain(PermissionName::AssistanceCreate->value)
                ->not->toContain(PermissionName::ProgramCreate->value)
                ->not->toContain(PermissionName::ProgramUpdate->value)
                ->not->toContain(PermissionName::AssistanceDelete->value);
        });
});

test('a releasing officer cannot create a program', function () {
    $department = Department::create(['name' => 'Department A']);
    $officer = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::ReleasingOfficer->value,
    );

    $this->actingAs($officer)
        ->post(route('user.programs.store', $department), [
            'name' => 'Should not exist',
            'descriptions' => 'Unauthorized program',
            'start_at' => now()->toDateString(),
        ])
        ->assertForbidden();

    expect(Program::query()->where('name', 'Should not exist')->exists())->toBeFalse();
});

test('distribution show shares only the abilities the current user holds', function () {
    $fixture = distributionFixture();
    $batch = storeDistribution($fixture);

    $this->actingAs($fixture['creator'])
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.update', true)
            ->where('can.validate', true)
            ->where('can.submit', true)
            ->where('can.cancel', true)
            ->where('can.approve', false)
            ->where('can.reject', false)
            ->where('can.execute', false)
            ->where('can.complete', false));

    $this->actingAs($fixture['officer'])
        ->get(route('user.distributions.show', [$fixture['department'], $batch]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.update', true)
            ->where('can.approve', false)
            ->where('can.execute', true)
            ->where('can.complete', true)
            ->where('can.cancel', true));
});

test('a user without assign permission cannot bulk assign assistance', function () {
    $department = Department::create(['name' => 'Department A']);
    $viewer = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::Beneficiary->value,
    );
    $assignee = User::factory()->create(['department_id' => $department->id]);
    $program = Program::factory()->forDepartment($department)->create();
    $individual = Individual::factory()->create();
    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $viewer->id,
    ]);

    $this->actingAs($viewer)
        ->patch(route('user.queue.assign', $department), [
            'assistance_ids' => [$assistance->id],
            'assigned_to_id' => $assignee->id,
        ])
        ->assertForbidden();

    expect($assistance->refresh()->assigned_to_id)->toBeNull();
});

test('a department head can bulk assign assistance in their department', function () {
    $department = Department::create(['name' => 'Department A']);
    $head = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::DepartmentHead->value,
    );
    $assignee = User::factory()->create(['department_id' => $department->id]);
    $program = Program::factory()->forDepartment($department)->create();
    $individual = Individual::factory()->create();
    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $individual->beneficiaryRecord->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $head->id,
    ]);

    $this->actingAs($head)
        ->from(route('user.queue.index', $department))
        ->patch(route('user.queue.assign', $department), [
            'assistance_ids' => [$assistance->id],
            'assigned_to_id' => $assignee->id,
        ])
        ->assertRedirect();

    expect($assistance->refresh()->assigned_to_id)->toBe($assignee->id);
});
