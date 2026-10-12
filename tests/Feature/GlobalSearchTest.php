<?php

use App\Enums\ProgramApprovalStatus;
use App\Enums\RoleName;
use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function searchBeneficiary(string $firstName, string $lastName, string $caisNumber): Beneficiary
{
    $individual = Individual::factory()->create([
        'first_name' => $firstName,
        'middle_name' => null,
        'last_name' => $lastName,
        'suffix' => null,
        'cais_number' => $caisNumber,
    ]);

    $beneficiary = $individual->beneficiaryRecord()->first();

    expect($beneficiary)->toBeInstanceOf(Beneficiary::class);

    return $beneficiary;
}

test('guests are redirected to login when they search', function () {
    $department = Department::create(['name' => 'Social Welfare']);

    $this->get(route('user.search', [
        'department' => $department->slug,
        'q' => 'rice',
    ]))->assertRedirect(route('login'));
});

test('users from another department cannot search', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $other = Department::create(['name' => 'Health']);
    $user = User::factory()->create(['department_id' => $other->id]);
    grantResourceRoles($user);

    $this->actingAs($user)
        ->getJson(route('user.search', [
            'department' => $department->slug,
            'q' => 'rice',
        ]))
        ->assertForbidden();
});

test('search requires a query', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    $this->actingAs($user)
        ->getJson(route('user.search', ['department' => $department->slug]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

test('search returns matching records for the current department', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    $other = Department::create(['name' => 'Health']);

    $program = Program::factory()->create([
        'department_id' => $department->id,
        'name' => 'Globalsearchneedle Relief',
        'is_closed' => false,
        'approval_status' => ProgramApprovalStatus::Approved,
    ]);
    Program::factory()->create([
        'department_id' => $other->id,
        'name' => 'Globalsearchneedle Other Program',
    ]);

    $beneficiary = searchBeneficiary('Globalsearchneedle', 'Person', 'CAIS-GS-100');

    $item = Item::factory()->create([
        'department_id' => $department->id,
        'name' => 'Globalsearchneedle Rice',
    ]);
    Item::factory()->create([
        'department_id' => $other->id,
        'name' => 'Globalsearchneedle Other Rice',
    ]);

    $fund = Fund::factory()->create([
        'department_id' => $department->id,
        'name' => 'Globalsearchneedle Fund',
    ]);
    Fund::factory()->create([
        'department_id' => $other->id,
        'name' => 'Globalsearchneedle Other Fund',
    ]);

    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-04-01',
        'user_id' => $user->id,
    ]);
    $otherProgram = Program::factory()->create([
        'department_id' => $other->id,
        'name' => 'Other Dept Program',
    ]);
    Assistance::create([
        'program_id' => $otherProgram->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-04-02',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('user.search', [
            'department' => $department->slug,
            'q' => 'Globalsearchneedle',
        ]))
        ->assertOk()
        ->assertJsonPath('data.programs.0.id', $program->id)
        ->assertJsonPath('data.programs.0.name', 'Globalsearchneedle Relief')
        ->assertJsonPath('data.programs.0.parent_name', null)
        ->assertJsonPath('data.programs.0.is_open', true)
        ->assertJsonPath('data.programs.0.approval_status', ProgramApprovalStatus::Approved->value)
        ->assertJsonPath('data.programs.0.url', route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->assertJsonCount(1, 'data.programs')
        ->assertJsonPath('data.beneficiaries.0.id', $beneficiary->id)
        ->assertJsonPath('data.beneficiaries.0.cais_number', 'CAIS-GS-100')
        ->assertJsonPath('data.beneficiaries.0.type', 'individual')
        ->assertJsonPath('data.beneficiaries.0.url', route('user.beneficiaries.show', [
            'department' => $department->slug,
            'beneficiary' => $beneficiary->id,
        ]))
        ->assertJsonPath('data.requests.0.id', $assistance->id)
        ->assertJsonPath('data.requests.0.beneficiary_name', 'Globalsearchneedle Person')
        ->assertJsonPath('data.requests.0.cais_number', 'CAIS-GS-100')
        ->assertJsonPath('data.requests.0.program_name', 'Globalsearchneedle Relief')
        ->assertJsonPath('data.requests.0.date_requested', '2026-04-01')
        ->assertJsonPath('data.requests.0.url', route('user.assistances.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertJsonCount(1, 'data.requests')
        ->assertJsonPath('data.items.0.id', $item->id)
        ->assertJsonPath('data.items.0.url', route('user.items.index', [
            'department' => $department->slug,
            'search' => $item->name,
        ]))
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.funds.0.id', $fund->id)
        ->assertJsonPath('data.funds.0.url', route('user.funds.index', [
            'department' => $department->slug,
            'search' => $fund->name,
        ]))
        ->assertJsonCount(1, 'data.funds');
});

test('a request is found by beneficiary cais number and by numeric id', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $byCais = searchBeneficiary('Plain', 'Person', 'CAISLOOKUPAA');
    $byId = searchBeneficiary('Another', 'Person', 'CAISOTHERBB');
    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    $caisAssistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $byCais->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    $idAssistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $byId->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('user.search', [
            'department' => $department->slug,
            'q' => 'CAISLOOKUPAA',
            'scope' => 'requests',
        ]))
        ->assertOk()
        ->assertJsonPath('data.requests.0.id', $caisAssistance->id)
        ->assertJsonMissingPath('data.programs');

    $this->actingAs($user)
        ->getJson(route('user.search', [
            'department' => $department->slug,
            'q' => (string) $idAssistance->id,
            'scope' => 'requests',
        ]))
        ->assertOk()
        ->assertJsonPath('data.requests.0.id', $idAssistance->id);
});

test('a result group is omitted when the user cannot view it', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();
    $roles = array_values(array_filter(
        RoleName::resourceRoleValues(),
        fn (string $role): bool => $role !== RoleName::Item->value,
    ));
    grantResourceRoles($user, ...$roles);

    Item::factory()->create([
        'department_id' => $department->id,
        'name' => 'Hidden Rice',
    ]);
    Program::factory()->create([
        'department_id' => $department->id,
        'name' => 'Visible Program',
    ]);

    $this->actingAs($user)
        ->getJson(route('user.search', [
            'department' => $department->slug,
            'q' => 'Rice',
        ]))
        ->assertOk()
        ->assertJsonMissingPath('data.items');

    $this->actingAs($user)
        ->getJson(route('user.search', [
            'department' => $department->slug,
            'q' => 'Visible',
        ]))
        ->assertOk()
        ->assertJsonPath('data.programs.0.name', 'Visible Program')
        ->assertJsonMissingPath('data.items');
});

test('a scoped search returns only that group', function () {
    ['department' => $department, 'user' => $user] = createBeneficiaryDepartmentUser();

    Program::factory()->create([
        'department_id' => $department->id,
        'name' => 'Scopedneedle Program',
    ]);
    searchBeneficiary('Scopedneedle', 'Person', 'CAIS-SCOPE-1');

    $this->actingAs($user)
        ->getJson(route('user.search', [
            'department' => $department->slug,
            'q' => 'Scopedneedle',
            'scope' => 'beneficiaries',
        ]))
        ->assertOk()
        ->assertJsonPath('data.beneficiaries.0.name', 'Scopedneedle Person')
        ->assertJsonMissingPath('data.programs')
        ->assertJsonMissingPath('data.requests')
        ->assertJsonMissingPath('data.items')
        ->assertJsonMissingPath('data.funds');
});
