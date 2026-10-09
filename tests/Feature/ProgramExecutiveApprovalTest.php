<?php

use App\Enums\PermissionName;
use App\Enums\ProgramApprovalStatus;
use App\Enums\ProgramKind;
use App\Enums\RequestStatusCode;
use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

test('the seeder grants office roles only their permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    $governor = Role::findByName(RoleName::Governor->value);
    $head = Role::findByName(RoleName::DepartmentHead->value);
    $officer = Role::findByName(RoleName::ReleasingOfficer->value);

    expect($governor->permissions->pluck('name')->all())
        ->toContain(PermissionName::ProgramApprove->value)
        ->not->toContain(PermissionName::ProgramSubmit->value)
        ->and($head->permissions->pluck('name')->all())
        ->toContain(PermissionName::ProgramSubmit->value)
        ->not->toContain(PermissionName::ProgramApprove->value)
        ->not->toContain(PermissionName::AssistanceAdvance->value)
        ->and($officer->permissions->pluck('name')->all())
        ->toContain(PermissionName::AssistanceAdvance->value)
        ->not->toContain(PermissionName::ProgramSubmit->value)
        ->not->toContain(PermissionName::ProgramApprove->value);
});

test('the executive program list uses the department program cards', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $governor = officeUser(RoleName::Governor, Department::create(['name' => 'Office Of The Governor']));
    $program = draftProgram($department);
    $program->update(['approval_status' => ProgramApprovalStatus::AwaitingGovernor]);
    $other = Department::create(['name' => 'Provincial Veterinary Office']);
    draftProgram($other)->update([
        'name' => 'Relief Other',
        'approval_status' => ProgramApprovalStatus::AwaitingGovernor,
    ]);

    $this->actingAs($governor)
        ->get(route('executive.programs.index', [
            'search' => 'Relief',
            'approval' => [ProgramApprovalStatus::AwaitingGovernor->value],
            'department' => [$department->id],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('executive/programs/index')
            ->where('search', 'Relief')
            ->where('department', [(string) $department->id])
            ->has('programs.data', 1)
            ->where('programs.data.0.name', 'Relief Program')
            ->where('programs.data.0.descriptions', 'Household relief')
            ->where('programs.data.0.approval_label', 'Awaiting executive')
            ->where('programs.data.0.department.name', 'Provincial Social Welfare And Development Office')
            ->where('programs.data.0.is_closed', false));
});

test('a department head cannot submit a program without a verified beneficiary', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $program = draftProgram($department);

    $this->actingAs($head)
        ->from(route('user.programs.show', [$department, $program]))
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect()
        ->assertSessionHasErrors('program');

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::Draft);
});

test('a department head submits verified beneficiaries for governor approval', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $program = draftProgram($department);
    verifiedAssistance($program, $head, 'CAIS-100', 'Verified Person');

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect(route('user.programs.show', [$department, $program]));

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::AwaitingGovernor);
});

test('governor approval marks only verified requests ready for release', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $governor = officeUser(RoleName::Governor, Department::create(['name' => 'Office Of The Governor']));
    $program = draftProgram($department);
    $verified = verifiedAssistance($program, $head, 'CAIS-100', 'Verified Person');
    $pending = verifiedAssistance($program, $head, 'CAIS-101', 'Pending Person', RequestSubStatusCode::AwaitingReview);

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect();

    $this->actingAs($governor)
        ->post(route('executive.programs.approve', $program))
        ->assertRedirect(route('executive.programs.show', $program));

    $verified->refresh()->load('currentRequestSubStatus.requestStatus');
    $pending->refresh()->load('currentRequestSubStatus.requestStatus');

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::Approved)
        ->and($verified->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ReadyForRelease)
        ->and($verified->currentRequestSubStatus?->requestStatus?->code)->toBe(RequestStatusCode::Approved)
        ->and($pending->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::AwaitingReview);
});

test('returning a program leaves beneficiary statuses unchanged', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $governor = officeUser(RoleName::Governor, Department::create(['name' => 'Office Of The Governor']));
    $program = draftProgram($department);
    $verified = verifiedAssistance($program, $head, 'CAIS-100', 'Verified Person');

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect();

    $this->actingAs($governor)
        ->post(route('executive.programs.return', $program), [
            'remark' => 'Add the missing household list.',
        ])
        ->assertRedirect(route('executive.programs.show', $program));

    $verified->refresh()->load('currentRequestSubStatus');

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::Returned)
        ->and($verified->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Verified);
});

test('a governor can see another department program and its beneficiaries', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $governor = officeUser(RoleName::Governor, Department::create(['name' => 'Office Of The Governor']));
    $program = draftProgram($department);
    verifiedAssistance($program, $head, 'CAIS-100', 'Juan Dela Cruz');

    $this->actingAs($governor)
        ->get(route('executive.programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('executive/programs/show')
            ->where('program.name', 'Relief Program')
            ->where('beneficiaries.data.0.beneficiary_name', 'Juan Dela Cruz')
            ->where('program.department.name', $department->name));
});

test('a releasing officer and a department head cannot approve a program', function (RoleName $role) {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $actor = $role === RoleName::DepartmentHead
        ? $head
        : officeUser($role, $department);
    $program = draftProgram($department);
    verifiedAssistance($program, $head, 'CAIS-100', 'Verified Person');

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect();

    $this->actingAs($actor)
        ->post(route('executive.programs.approve', $program))
        ->assertForbidden();
})->with([
    RoleName::ReleasingOfficer,
    RoleName::DepartmentHead,
]);

test('a releasing officer cannot submit a program for approval', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $officer = officeUser(RoleName::ReleasingOfficer, $department);
    $program = draftProgram($department);

    $this->actingAs($officer)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertForbidden();
});

test('each batch is submitted and approved separately', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $governor = officeUser(RoleName::Governor, Department::create(['name' => 'Office Of The Governor']));
    $scheme = Program::factory()->scheme()->create([
        'name' => 'Relief Scheme',
        'department_id' => $department->id,
        'approval_status' => ProgramApprovalStatus::Draft,
    ]);
    $batch = Program::factory()->batch($scheme)->create([
        'approval_status' => ProgramApprovalStatus::Draft,
    ]);
    $otherBatch = Program::factory()->batch($scheme)->create([
        'approval_status' => ProgramApprovalStatus::Draft,
    ]);
    $verified = verifiedAssistance($batch, $head, 'CAIS-200', 'Batch Beneficiary');
    $otherVerified = verifiedAssistance($otherBatch, $head, 'CAIS-201', 'Other Batch Beneficiary');

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $scheme]))
        ->assertForbidden();

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $batch]))
        ->assertRedirect(route('user.programs.show', [$department, $batch]));

    expect($scheme->refresh()->approval_status)->toBe(ProgramApprovalStatus::Draft)
        ->and($batch->refresh()->approval_status)->toBe(ProgramApprovalStatus::AwaitingGovernor)
        ->and($otherBatch->refresh()->approval_status)->toBe(ProgramApprovalStatus::Draft);

    $this->actingAs($governor)
        ->post(route('executive.programs.approve', $batch))
        ->assertRedirect();

    $verified->refresh()->load('currentRequestSubStatus');
    $otherVerified->refresh()->load('currentRequestSubStatus');

    expect($batch->refresh()->approval_status)->toBe(ProgramApprovalStatus::Approved)
        ->and($otherBatch->refresh()->approval_status)->toBe(ProgramApprovalStatus::Draft)
        ->and($verified->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::ReadyForRelease)
        ->and($otherVerified->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Verified);
});

function officeUser(RoleName $role, Department $department): User
{
    seedRolesAndPermissions();

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);
    $user->syncRoles([$role->value]);

    return $user->fresh() ?? $user;
}

function draftProgram(Department $department): Program
{
    return Program::create([
        'name' => 'Relief Program',
        'descriptions' => 'Household relief',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
        'kind' => ProgramKind::Standalone,
        'approval_status' => ProgramApprovalStatus::Draft,
    ]);
}

function verifiedAssistance(
    Program $program,
    User $encoder,
    string $caisNumber,
    string $name,
    RequestSubStatusCode $status = RequestSubStatusCode::Verified,
): Assistance {
    $beneficiary = Beneficiary::create([
        'cais_number' => $caisNumber,
        'name' => $name,
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => (Beneficiary::query()->max('beneficiable_id') ?? 0) + 1,
    ]);
    $mode = ModeOfRequest::query()->first() ?? ModeOfRequest::create(['name' => 'Walk In']);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $encoder->id,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId($status),
        'remark' => null,
        'recorded_at' => now(),
    ]);

    return $assistance->refresh();
}
