<?php

use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

test('an encoder can create assistance on a department program', function () {
    $department = Department::create(['name' => 'Department A']);
    $encoder = officeRoleUser($department, RoleName::Encoder);
    $program = encodableProgram($department);
    ['item' => $item, 'beneficiary' => $beneficiary, 'mode' => $mode] = assistanceFixtures($department, $program);

    $this->actingAs($encoder)
        ->from(route('user.programs.show', [$department, $program]))
        ->post(route('user.programs.assistances.store', [$department, $program]), [
            'beneficiary_id' => $beneficiary->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 2,
                ],
            ],
        ])
        ->assertRedirect();

    expect(Assistance::query()->where('program_id', $program->id)->where('user_id', $encoder->id)->exists())->toBeTrue();
});

test('a verifier cannot create assistance', function () {
    $department = Department::create(['name' => 'Department A']);
    $verifier = officeRoleUser($department, RoleName::Verifier);
    $program = encodableProgram($department);
    ['item' => $item, 'beneficiary' => $beneficiary, 'mode' => $mode] = assistanceFixtures($department, $program);

    $this->actingAs($verifier)
        ->post(route('user.programs.assistances.store', [$department, $program]), [
            'beneficiary_id' => $beneficiary->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 2,
                ],
            ],
        ])
        ->assertForbidden();

    expect(Assistance::query()->where('program_id', $program->id)->exists())->toBeFalse();
});

test('a verifier can mark assistance verified and an encoder cannot', function () {
    $department = Department::create(['name' => 'Department A']);
    $verifier = officeRoleUser($department, RoleName::Verifier);
    $encoder = officeRoleUser($department, RoleName::Encoder);
    $program = encodableProgram($department);
    ['beneficiary' => $beneficiary, 'mode' => $mode] = assistanceFixtures($department, $program);
    $verifiedId = catalogReasonId(RequestSubStatusCode::Verified);
    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $encoder->id,
    ]);
    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now()->subDay()->toDateTimeString(),
    ]);

    $payload = [
        'request_sub_status_id' => $verifiedId,
        'recorded_at' => now()->toDateString(),
        'remark' => 'Documents checked',
    ];

    $this->actingAs($encoder)
        ->patch(route('user.programs.assistances.status.update', [$department, $program, $assistance]), $payload)
        ->assertForbidden();

    $this->actingAs($verifier)
        ->from(route('user.programs.show', [$department, $program]))
        ->patch(route('user.programs.assistances.status.update', [$department, $program, $assistance]), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($assistance->refresh()->current_request_sub_status_id)->toBe($verifiedId);
});

test('a releasing officer can open a distribution batch', function () {
    $fixture = distributionFixture();

    $this->actingAs($fixture['officer'])
        ->post(route('user.distributions.store', $fixture['department']), distributionPayload($fixture))
        ->assertRedirect();

    expect(DistributionBatch::query()->where('program_id', $fixture['program']->id)->exists())->toBeTrue();
});

test('an inventory officer can receive stock and an encoder cannot', function () {
    $department = Department::create(['name' => 'Department A']);
    $officer = officeRoleUser($department, RoleName::InventoryOfficer);
    $encoder = officeRoleUser($department, RoleName::Encoder);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $payload = [
        'quantity' => 20,
        'type' => StockMovementType::OpeningBalance->value,
    ];

    $this->actingAs($encoder)
        ->post(route('user.items.stock.receipts.store', [$department, $item]), $payload)
        ->assertForbidden();

    $this->actingAs($officer)
        ->post(route('user.items.stock.receipts.store', [$department, $item]), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');
});

function officeRoleUser(Department $department, RoleName $role): User
{
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->syncRoles([$role->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh() ?? $user;
}

function encodableProgram(Department $department): Program
{
    return Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
}

/**
 * @return array{item: Item, beneficiary: Beneficiary, mode: ModeOfRequest}
 */
function assistanceFixtures(Department $department, Program $program): array
{
    $unit = ItemUnitMeasurement::query()->firstOrCreate(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program->item()->attach($item->id);
    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-'.fake()->unique()->numerify('###'),
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $mode = ModeOfRequest::query()->firstOrCreate(['name' => 'Walk In']);

    return compact('item', 'beneficiary', 'mode');
}
