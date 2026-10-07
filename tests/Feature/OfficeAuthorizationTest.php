<?php

use App\Enums\ProgramApprovalStatus;
use App\Enums\RoleName;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\ProgramApprovalEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function officeUser(RoleName $office, ?Department $department = null): User
{
    seedRolesAndPermissions();

    $user = User::factory()->create([
        'department_id' => $department?->id,
    ]);

    $user->syncRoles([$office->value]);

    return $user->fresh() ?? $user;
}

test('a department head lands on the department dashboard', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = officeUser(RoleName::DepartmentHead, $department);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.dashboard.index', $department));
});

test('a releasing officer lands on the queue', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = officeUser(RoleName::ReleasingOfficer, $department);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.queue.index', $department));
});

test('a governor lands on the approval inbox', function () {
    $user = officeUser(RoleName::Governor);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('governor.programs.index'));
});

test('a super admin sees the office portal', function () {
    $user = officeUser(RoleName::SuperAdmin);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('portal'));

    $this->actingAs($user)
        ->get(route('portal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/index')
            ->has('offices', 4));
});

test('a super admin keeps the office they entered', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = officeUser(RoleName::SuperAdmin);

    $this->actingAs($user)
        ->post(route('portal.enter'), [
            'office' => RoleName::DepartmentHead->value,
            'department_id' => $department->id,
        ])
        ->assertRedirect(route('user.dashboard.index', $department));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.dashboard.index', $department));
});

test('an account with one office cannot open the portal', function () {
    $user = officeUser(RoleName::Governor);

    $this->actingAs($user)
        ->get(route('portal'))
        ->assertRedirect(route('governor.programs.index'));
});

test('a legacy department user still lands on the department dashboard', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.dashboard.index', $department));
});

test('programs move from draft to governor approval and can be returned', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $governor = officeUser(RoleName::Governor);
    $program = Program::factory()->forDepartment($department)->create([
        'approval_status' => ProgramApprovalStatus::Draft,
    ]);

    $this->actingAs($head)
        ->post(route('user.programs.submit', [$department, $program]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::Proposed);

    $this->actingAs($head)
        ->post(route('user.programs.endorse', [$department, $program]))
        ->assertRedirect();

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::AwaitingGovernor);

    $this->actingAs($governor)
        ->post(route('governor.programs.return', $program), [
            'comment' => 'Add the barangay quota.',
        ])
        ->assertRedirect(route('governor.programs.index'));

    $program->refresh();

    expect($program->approval_status)->toBe(ProgramApprovalStatus::Returned)
        ->and($program->latestReturnComment())->toBe('Add the barangay quota.')
        ->and(ProgramApprovalEvent::query()->where('program_id', $program->id)->where('comment', 'Add the barangay quota.')->exists())->toBeTrue();

    $this->actingAs($head)
        ->post(route('user.programs.submit', [$department, $program]))
        ->assertRedirect();

    $this->actingAs($head)
        ->post(route('user.programs.endorse', [$department, $program]))
        ->assertRedirect();

    $this->actingAs($governor)
        ->post(route('governor.programs.approve', $program))
        ->assertRedirect(route('governor.programs.index'));

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::Approved)
        ->and($program->isEncodable())->toBeTrue();
});

test('the wrong office cannot move a program through approval', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $officer = officeUser(RoleName::ReleasingOfficer, $department);
    $governor = officeUser(RoleName::Governor);
    $program = Program::factory()->forDepartment($department)->create([
        'approval_status' => ProgramApprovalStatus::Proposed,
    ]);

    $this->actingAs($officer)
        ->post(route('user.programs.endorse', [$department, $program]))
        ->assertForbidden();

    $this->actingAs($head)
        ->post(route('governor.programs.approve', $program))
        ->assertForbidden();

    $this->actingAs($governor)
        ->post(route('user.programs.endorse', [$department, $program]))
        ->assertForbidden();

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::Proposed);
});

test('assistance cannot be encoded until the program is approved', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $officer = officeUser(RoleName::ReleasingOfficer, $department);
    $governor = officeUser(RoleName::Governor);
    $program = Program::factory()->forDepartment($department)->create([
        'approval_status' => ProgramApprovalStatus::Draft,
        'is_closed' => false,
    ]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program->item()->attach($item->id);
    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-900',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $mode = ModeOfRequest::create(['name' => 'Walk In']);
    $payload = [
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'recorded_at' => now()->toDateString(),
        'item_details' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
            ],
        ],
    ];

    $this->actingAs($officer)
        ->post(route('user.programs.assistances.store', [$department, $program]), $payload)
        ->assertForbidden();

    $this->actingAs($governor)
        ->post(route('user.programs.assistances.store', [$department, $program]), $payload)
        ->assertForbidden();

    $program->update(['approval_status' => ProgramApprovalStatus::Approved]);

    $this->actingAs($officer)
        ->post(route('user.programs.assistances.store', [$department, $program]), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('assistances', [
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
    ]);
});

test('an approved program keeps its definition locked', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $program = Program::factory()->forDepartment($department)->create([
        'name' => 'Rice aid',
        'descriptions' => 'Details',
        'approval_status' => ProgramApprovalStatus::Approved,
    ]);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => ItemUnitMeasurement::create(['name' => 'kg'])->id,
    ]);
    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);
    $program->item()->attach($item->id);
    $program->fund()->attach($fund->id);

    $this->actingAs($head)
        ->from(route('user.programs.show', [$department, $program]))
        ->put(route('user.programs.update', [$department, $program]), [
            'name' => 'Changed name',
            'descriptions' => 'Details',
            'start_at' => now()->toDateString(),
            'item_ids' => [$item->id],
            'fund_ids' => [$fund->id],
        ])
        ->assertSessionHasErrors('name');

    expect($program->refresh()->name)->toBe('Rice aid');
});

test('existing approved programs stay encodable', function () {
    $program = Program::factory()->create([
        'is_closed' => false,
    ]);

    expect($program->approval_status)->toBe(ProgramApprovalStatus::Approved)
        ->and($program->isEncodable())->toBeTrue();
});
