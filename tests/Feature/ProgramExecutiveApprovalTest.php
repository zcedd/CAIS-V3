<?php

use App\Enums\PermissionName;
use App\Enums\ProgramApprovalStatus;
use App\Enums\ProgramKind;
use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\ProgramDocumentRequirement;
use App\Models\ProgramField;
use App\Models\User;
use App\Notifications\ProgramDecisionNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

test('the seeder grants office roles only their permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    $governor = Role::findByName(RoleName::Executive->value);
    $administrator = Role::findByName(RoleName::Administrator->value);
    $head = Role::findByName(RoleName::DepartmentHead->value);
    $officer = Role::findByName(RoleName::ReleasingOfficer->value);
    $encoder = Role::findByName(RoleName::Encoder->value);
    $verifier = Role::findByName(RoleName::Verifier->value);
    $inventoryOfficer = Role::findByName(RoleName::InventoryOfficer->value);

    expect($governor->permissions->pluck('name')->all())
        ->toContain(PermissionName::ProgramApprove->value)
        ->not->toContain(PermissionName::ProgramSubmit->value)
        ->not->toContain(PermissionName::ProgramEndorse->value)
        ->and($administrator->permissions->pluck('name')->all())
        ->toContain(PermissionName::ProgramEndorse->value)
        ->not->toContain(PermissionName::ProgramApprove->value)
        ->and($head->permissions->pluck('name')->all())
        ->toContain(PermissionName::ProgramSubmit->value)
        ->not->toContain(PermissionName::ProgramApprove->value)
        ->not->toContain(PermissionName::AssistanceAdvance->value)
        ->and($officer->permissions->pluck('name')->all())
        ->toContain(PermissionName::DistributionCreate->value)
        ->toContain(PermissionName::DistributionExecute->value)
        ->not->toContain(PermissionName::DistributionApprove->value)
        ->not->toContain(PermissionName::ProgramSubmit->value)
        ->not->toContain(PermissionName::ProgramApprove->value)
        ->and($encoder->permissions->pluck('name')->all())
        ->toContain(PermissionName::AssistanceCreate->value)
        ->not->toContain(PermissionName::AssistanceAdvance->value)
        ->and($verifier->permissions->pluck('name')->all())
        ->toContain(PermissionName::AssistanceAdvance->value)
        ->not->toContain(PermissionName::AssistanceCreate->value)
        ->and($inventoryOfficer->permissions->pluck('name')->all())
        ->toContain(PermissionName::ItemUpdate->value)
        ->not->toContain(PermissionName::AssistanceCreate->value);
});

test('the executive program list uses the department program cards', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $governor = officeUser(RoleName::Executive, Department::create(['name' => 'Office Of The Governor']));
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

test('a department head submits a program without beneficiaries', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $program = draftProgram($department);

    $this->actingAs($head)
        ->from(route('user.programs.show', [$department, $program]))
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect(route('user.programs.show', [$department, $program]));

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::AwaitingAdministrator);
});

test('a department head submits a program that already has beneficiaries', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $program = draftProgram($department);
    verifiedAssistance($program, $head, 'CAIS-100', 'Verified Person');

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect();

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::AwaitingAdministrator);
});

test('executive approval leaves beneficiary statuses unchanged', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $governor = officeUser(RoleName::Executive, Department::create(['name' => 'Office Of The Governor']));
    $program = draftProgram($department);
    $verified = verifiedAssistance($program, $head, 'CAIS-100', 'Verified Person');
    $pending = verifiedAssistance($program, $head, 'CAIS-101', 'Pending Person', RequestSubStatusCode::AwaitingReview);

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect();

    $this->actingAs(administratorUser())
        ->post(route('administrator.programs.endorse', $program))
        ->assertRedirect();

    $this->actingAs($governor)
        ->post(route('executive.programs.approve', $program))
        ->assertRedirect();

    $verified->refresh()->load('currentRequestSubStatus');
    $pending->refresh()->load('currentRequestSubStatus');

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::Approved)
        ->and($verified->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Verified)
        ->and($pending->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::AwaitingReview);
});

test('returning a program leaves beneficiary statuses unchanged', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $governor = officeUser(RoleName::Executive, Department::create(['name' => 'Office Of The Governor']));
    $program = draftProgram($department);
    $verified = verifiedAssistance($program, $head, 'CAIS-100', 'Verified Person');

    $this->actingAs($head)
        ->post(route('user.programs.approval.store', [$department, $program]))
        ->assertRedirect();

    $this->actingAs(administratorUser())
        ->post(route('administrator.programs.endorse', $program))
        ->assertRedirect();

    $this->actingAs($governor)
        ->post(route('executive.programs.return', $program), [
            'remark' => 'Add the missing household list.',
        ])
        ->assertRedirect();

    $verified->refresh()->load('currentRequestSubStatus');

    expect($program->refresh()->approval_status)->toBe(ProgramApprovalStatus::Returned)
        ->and($verified->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Verified);
});

test('an executive and an administrator see the full program', function (string $audience) {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $viewer = $audience === 'executive'
        ? officeUser(RoleName::Executive, Department::create(['name' => 'Office Of The Governor']))
        : administratorUser();
    $showRoute = $audience === 'executive'
        ? 'executive.programs.show'
        : 'administrator.programs.show';
    $program = draftProgram($department);
    verifiedAssistance($program, $head, 'CAIS-100', 'Juan Dela Cruz');
    $fund = Fund::factory()->create([
        'name' => 'Calamity Fund',
        'year' => '2026',
        'department_id' => $department->id,
    ]);
    $item = Item::factory()->forDepartment($department)->create([
        'name' => 'Rice',
    ]);
    $program->fund()->attach($fund);
    $program->item()->attach($item);
    ProgramField::factory()->forProgram($program)->create([
        'label' => 'Household size',
        'is_required' => true,
    ]);
    $documentType = DocumentType::factory()->create([
        'name' => 'Barangay certificate',
    ]);
    ProgramDocumentRequirement::factory()->forProgram($program)->create([
        'document_type_id' => $documentType->id,
    ]);
    $program->eligibilityRule()->create([
        'cooldown_days' => 30,
        'require_pwd' => true,
        'require_4ps' => false,
        'require_solo_parent' => false,
        'require_indigenous' => false,
    ]);

    $this->actingAs($viewer)
        ->get(route($showRoute, $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('executive/programs/show')
            ->where('program.name', 'Relief Program')
            ->where('program.descriptions', 'Household relief')
            ->where('program.start_at', now()->format('M d, Y'))
            ->where('program.distribution_mode_label', 'Batch distribution')
            ->where('program.workflow_name', 'Department default')
            ->where('program.funds.0.name', 'Calamity Fund')
            ->where('program.items.0.name', 'Rice')
            ->where('program.stock.0.item_name', 'Rice')
            ->where('program.fields.0.label', 'Household size')
            ->where('program.fields.0.is_required', true)
            ->where('program.document_requirements.0.name', 'Barangay certificate')
            ->where('program.document_requirements.0.required_before_label', 'Before verification')
            ->where('program.eligibility.cooldown_days', 30)
            ->where('program.eligibility.requirements.0', 'PWD only')
            ->where('program.department.name', $department->name)
            ->missing('beneficiaries'));

    $this->actingAs($viewer)
        ->get(
            route($showRoute, $program),
            inertiaPartialHeaders('executive/programs/show', ['assistances', 'summary']),
        )
        ->assertOk()
        ->assertJsonPath('props.assistances.data.0.beneficiary_name', 'Juan Dela Cruz')
        ->assertJsonPath('props.assistances.data.0.cais_number', 'CAIS-100')
        ->assertJsonPath('props.assistances.data.0.status', 'Verified')
        ->assertJsonPath('props.summary.total_requests', 1);
})->with(['executive', 'administrator']);

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
    $governor = officeUser(RoleName::Executive, Department::create(['name' => 'Office Of The Governor']));
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
        ->assertRedirect();

    expect($scheme->refresh()->approval_status)->toBe(ProgramApprovalStatus::Draft)
        ->and($batch->refresh()->approval_status)->toBe(ProgramApprovalStatus::AwaitingAdministrator)
        ->and($otherBatch->refresh()->approval_status)->toBe(ProgramApprovalStatus::Draft);

    $this->actingAs(administratorUser())
        ->post(route('administrator.programs.endorse', $batch))
        ->assertRedirect();

    $this->actingAs($governor)
        ->post(route('executive.programs.approve', $batch))
        ->assertRedirect();

    $verified->refresh()->load('currentRequestSubStatus');
    $otherVerified->refresh()->load('currentRequestSubStatus');

    expect($batch->refresh()->approval_status)->toBe(ProgramApprovalStatus::Approved)
        ->and($otherBatch->refresh()->approval_status)->toBe(ProgramApprovalStatus::Draft)
        ->and($verified->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Verified)
        ->and($otherVerified->currentRequestSubStatus?->code)->toBe(RequestSubStatusCode::Verified);
});

test('only a department head can endorse a program from the department index', function () {
    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $program = draftProgram($department);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $staff = officeUser(RoleName::Head, $department);

    $this->actingAs($head)
        ->get(route('user.programs.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('programs.data.0.id', $program->id)
            ->where('programs.data.0.can_submit_approval', true));

    $this->actingAs($staff)
        ->get(route('user.programs.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('programs.data.0.can_submit_approval', false));
});

test('approving a program notifies the department head', function () {
    Notification::fake();

    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $secondHead = officeUser(RoleName::DepartmentHead, $department);
    $encoder = officeUser(RoleName::Encoder, $department);
    $otherHead = officeUser(RoleName::DepartmentHead, Department::create(['name' => 'Provincial Veterinary Office']));
    $governor = officeUser(RoleName::Executive, Department::create(['name' => 'Office Of The Governor']));
    $program = draftProgram($department);
    $program->update(['approval_status' => ProgramApprovalStatus::AwaitingGovernor]);

    $this->actingAs($governor)
        ->post(route('executive.programs.approve', $program))
        ->assertRedirect();

    $expected = [
        'title' => 'Program approved',
        'message' => $governor->name.' approved '.$program->name.'.',
        'program_id' => $program->id,
        'url' => route('user.programs.show', [$department, $program]),
    ];

    Notification::assertSentTo(
        $head,
        ProgramDecisionNotification::class,
        fn (ProgramDecisionNotification $notification): bool => programDecisionMatches($notification, $head, $expected),
    );
    Notification::assertSentTo(
        $secondHead,
        ProgramDecisionNotification::class,
        fn (ProgramDecisionNotification $notification): bool => programDecisionMatches($notification, $secondHead, $expected),
    );
    Notification::assertNotSentTo($encoder, ProgramDecisionNotification::class);
    Notification::assertNotSentTo($otherHead, ProgramDecisionNotification::class);
    Notification::assertNotSentTo($governor, ProgramDecisionNotification::class);
});

test('returning a program notifies the department head', function () {
    Notification::fake();

    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $governor = officeUser(RoleName::Executive, Department::create(['name' => 'Office Of The Governor']));
    $program = draftProgram($department);
    $program->update(['approval_status' => ProgramApprovalStatus::AwaitingGovernor]);

    $this->actingAs($governor)
        ->post(route('executive.programs.return', $program), [
            'remark' => 'Add the missing household list.',
        ])
        ->assertRedirect();

    Notification::assertSentTo(
        $head,
        ProgramDecisionNotification::class,
        fn (ProgramDecisionNotification $notification): bool => programDecisionMatches($notification, $head, [
            'title' => 'Program returned',
            'message' => $governor->name.' returned '.$program->name.'. Add the missing household list.',
            'program_id' => $program->id,
            'url' => route('user.programs.show', [$department, $program]),
        ]),
    );
});

test('an administrator return notifies the department head', function () {
    Notification::fake();

    $department = Department::create(['name' => 'Provincial Social Welfare And Development Office']);
    $head = officeUser(RoleName::DepartmentHead, $department);
    $program = draftProgram($department);
    $program->update(['approval_status' => ProgramApprovalStatus::AwaitingAdministrator]);

    $this->actingAs(administratorUser())
        ->post(route('administrator.programs.return', $program), [
            'remark' => 'Clarify the fund source.',
        ])
        ->assertRedirect();

    Notification::assertSentTo($head, ProgramDecisionNotification::class, function (ProgramDecisionNotification $notification) use ($head): bool {
        $data = $notification->toArray($head);

        return $data['title'] === 'Program returned'
            && str_contains((string) $data['message'], 'Clarify the fund source.');
    });
});

function administratorUser(): User
{
    seedRolesAndPermissions();

    $user = User::factory()->create([
        'department_id' => null,
    ]);
    $user->syncRoles([RoleName::Administrator->value]);

    return $user->fresh() ?? $user;
}

/**
 * @param  array{title: string, message: string, program_id: int, url: string}  $expected
 */
function programDecisionMatches(ProgramDecisionNotification $notification, User $head, array $expected): bool
{
    $data = $notification->toArray($head);

    return $data['title'] === $expected['title']
        && $data['message'] === $expected['message']
        && $data['program_id'] === $expected['program_id']
        && $data['url'] === $expected['url'];
}

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
