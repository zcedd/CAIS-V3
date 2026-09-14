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
use App\Models\ProgramEligibilityRule;
use App\Models\ProgramItemCap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     department: Department,
 *     user: User,
 *     program: Program,
 *     item: Item,
 *     beneficiary: Beneficiary,
 *     individual: Individual,
 *     mode: ModeOfRequest
 * }
 */
function createEligibilityHttpFixtures(array $individualAttributes = []): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    $program = Program::create([
        'name' => 'Relief',
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
    $program->item()->attach($item->id);

    $individual = Individual::factory()->create($individualAttributes);
    $beneficiary = $individual->beneficiaryRecord;

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    return compact('department', 'user', 'program', 'item', 'beneficiary', 'individual', 'mode');
}

test('encoding without a reason is rejected when an open request exists', function () {
    $fixtures = createEligibilityHttpFixtures();

    Assistance::query()->create([
        'program_id' => $fixtures['program']->id,
        'beneficiary_id' => $fixtures['beneficiary']->id,
        'date_requested' => now()->toDateString(),
        'was_delivered' => false,
        'user_id' => $fixtures['user']->id,
    ]);

    $this->actingAs($fixtures['user'])
        ->from(route('user.programs.show', [
            'department' => $fixtures['department']->slug,
            'program' => $fixtures['program']->id,
        ]))
        ->post(route('user.programs.assistances.store', [
            'department' => $fixtures['department']->slug,
            'program' => $fixtures['program']->id,
        ]), [
            'beneficiary_id' => $fixtures['beneficiary']->id,
            'mode_of_request_id' => $fixtures['mode']->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                ['item_id' => $fixtures['item']->id, 'quantity' => 1],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('eligibility_override_reason');
});

test('encoding succeeds with an override reason when an open request exists', function () {
    $fixtures = createEligibilityHttpFixtures();

    Assistance::query()->create([
        'program_id' => $fixtures['program']->id,
        'beneficiary_id' => $fixtures['beneficiary']->id,
        'date_requested' => now()->toDateString(),
        'was_delivered' => false,
        'user_id' => $fixtures['user']->id,
    ]);

    $this->actingAs($fixtures['user'])
        ->post(route('user.programs.assistances.store', [
            'department' => $fixtures['department']->slug,
            'program' => $fixtures['program']->id,
        ]), [
            'beneficiary_id' => $fixtures['beneficiary']->id,
            'mode_of_request_id' => $fixtures['mode']->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                ['item_id' => $fixtures['item']->id, 'quantity' => 1],
            ],
            'eligibility_override_reason' => 'Supervisor approved a second pack.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('assistances', [
        'beneficiary_id' => $fixtures['beneficiary']->id,
        'eligibility_override_reason' => 'Supervisor approved a second pack.',
    ]);
});

test('encoding is hard-blocked when the program requires pwd', function () {
    $fixtures = createEligibilityHttpFixtures(['pwd' => false]);

    ProgramEligibilityRule::query()->create([
        'program_id' => $fixtures['program']->id,
        'require_pwd' => true,
    ]);

    $this->actingAs($fixtures['user'])
        ->post(route('user.programs.assistances.store', [
            'department' => $fixtures['department']->slug,
            'program' => $fixtures['program']->id,
        ]), [
            'beneficiary_id' => $fixtures['beneficiary']->id,
            'mode_of_request_id' => $fixtures['mode']->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                ['item_id' => $fixtures['item']->id, 'quantity' => 1],
            ],
            'eligibility_override_reason' => 'Should not bypass a hard block.',
        ])
        ->assertSessionHasErrors('beneficiary_id');
});

test('encoding warns when a yearly item cap would be exceeded', function () {
    $fixtures = createEligibilityHttpFixtures();

    ProgramItemCap::query()->create([
        'program_id' => $fixtures['program']->id,
        'item_id' => $fixtures['item']->id,
        'max_released_per_year' => 2,
    ]);

    $delivered = Assistance::query()->create([
        'program_id' => $fixtures['program']->id,
        'beneficiary_id' => $fixtures['beneficiary']->id,
        'date_requested' => now()->toDateString(),
        'date_delivered' => now()->toDateString(),
        'was_delivered' => true,
        'user_id' => $fixtures['user']->id,
    ]);

    AssistanceItem::query()->create([
        'assistance_id' => $delivered->id,
        'item_id' => $fixtures['item']->id,
        'quantity' => 2,
        'is_received' => true,
    ]);

    $this->actingAs($fixtures['user'])
        ->post(route('user.programs.assistances.store', [
            'department' => $fixtures['department']->slug,
            'program' => $fixtures['program']->id,
        ]), [
            'beneficiary_id' => $fixtures['beneficiary']->id,
            'mode_of_request_id' => $fixtures['mode']->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                ['item_id' => $fixtures['item']->id, 'quantity' => 1],
            ],
        ])
        ->assertSessionHasErrors('eligibility_override_reason');
});

test('eligibility preview returns history and findings', function () {
    $fixtures = createEligibilityHttpFixtures();

    Assistance::query()->create([
        'program_id' => $fixtures['program']->id,
        'beneficiary_id' => $fixtures['beneficiary']->id,
        'date_requested' => now()->toDateString(),
        'was_delivered' => false,
        'user_id' => $fixtures['user']->id,
    ]);

    $this->actingAs($fixtures['user'])
        ->getJson(route('user.programs.assistances.eligibility', [
            'department' => $fixtures['department']->slug,
            'program' => $fixtures['program']->id,
            'beneficiary_id' => $fixtures['beneficiary']->id,
        ]))
        ->assertOk()
        ->assertJsonPath('data.findings.0.code', 'open_request')
        ->assertJsonCount(1, 'data.history');
});

test('transfer into a program with an open request requires a reason', function () {
    $fixtures = createEligibilityHttpFixtures();

    $target = Program::create([
        'name' => 'Target',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $fixtures['department']->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $target->item()->attach($fixtures['item']->id);

    Assistance::query()->create([
        'program_id' => $target->id,
        'beneficiary_id' => $fixtures['beneficiary']->id,
        'date_requested' => now()->toDateString(),
        'was_delivered' => false,
        'user_id' => $fixtures['user']->id,
    ]);

    $source = Assistance::query()->create([
        'program_id' => $fixtures['program']->id,
        'beneficiary_id' => $fixtures['beneficiary']->id,
        'date_requested' => now()->toDateString(),
        'was_delivered' => false,
        'user_id' => $fixtures['user']->id,
    ]);

    AssistanceItem::query()->create([
        'assistance_id' => $source->id,
        'item_id' => $fixtures['item']->id,
        'quantity' => 1,
        'is_received' => false,
    ]);

    $this->actingAs($fixtures['user'])
        ->patch(route('user.programs.assistances.transfer', [
            'department' => $fixtures['department']->slug,
            'program' => $fixtures['program']->id,
            'assistance' => $source->id,
        ]), [
            'target_program_id' => $target->id,
            'reason' => 'Moved between programs',
        ])
        ->assertSessionHasErrors('eligibility_override_reason');
});

test('encoding without a reason is rejected during cooldown', function () {
    $fixtures = createEligibilityHttpFixtures();

    ProgramEligibilityRule::query()->create([
        'program_id' => $fixtures['program']->id,
        'cooldown_days' => 90,
    ]);

    Assistance::query()->create([
        'program_id' => $fixtures['program']->id,
        'beneficiary_id' => $fixtures['beneficiary']->id,
        'date_requested' => now()->subDays(20)->toDateString(),
        'date_delivered' => now()->subDays(10)->toDateString(),
        'was_delivered' => true,
        'user_id' => $fixtures['user']->id,
    ]);

    $this->actingAs($fixtures['user'])
        ->post(route('user.programs.assistances.store', [
            'department' => $fixtures['department']->slug,
            'program' => $fixtures['program']->id,
        ]), [
            'beneficiary_id' => $fixtures['beneficiary']->id,
            'mode_of_request_id' => $fixtures['mode']->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                ['item_id' => $fixtures['item']->id, 'quantity' => 1],
            ],
        ])
        ->assertSessionHasErrors('eligibility_override_reason');
});
