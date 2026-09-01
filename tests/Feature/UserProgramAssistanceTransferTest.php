<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTransferAssistance(
    Program $program,
    User $user,
    Item $item,
    string $caisNumber = 'CAIS-001',
    string $name = 'Juan Dela Cruz',
): Assistance {
    $nameParts = preg_split('/\s+/', trim($name)) ?: ['Juan', 'Cruz'];
    $firstName = $nameParts[0];
    $lastName = implode(' ', array_slice($nameParts, 1)) ?: 'Cruz';

    $individual = Individual::factory()->create([
        'cais_number' => $caisNumber,
        'first_name' => $firstName,
        'last_name' => $lastName,
    ]);

    $beneficiary = $individual->beneficiaryRecord;
    $beneficiary->update([
        'cais_number' => $caisNumber,
        'name' => $name,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-05-01',
        'remark' => null,
        'user_id' => $user->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'specification' => '25 kg',
        'is_received' => false,
    ]);

    return $assistance;
}

test('authenticated users can transfer assistance to another open program in their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $sourceProgram = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $targetProgram = Program::create([
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

    $sourceProgram->item()->attach($item->id);
    $targetProgram->item()->attach($item->id);

    $assistance = createTransferAssistance($sourceProgram, $user, $item);

    $response = $this->actingAs($user)->from(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]))->patch(
        route('user.programs.assistances.transfer', [
            'department' => $department->slug,
            'program' => $sourceProgram->id,
            'assistance' => $assistance->id,
        ]),
        [
            'target_program_id' => $targetProgram->id,
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]));

    expect($assistance->fresh()->program_id)->toBe($targetProgram->id);
});

test('authenticated users can bulk transfer assistance records to another open program', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $sourceProgram = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $targetProgram = Program::create([
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

    $sourceProgram->item()->attach($item->id);
    $targetProgram->item()->attach($item->id);

    $firstAssistance = createTransferAssistance($sourceProgram, $user, $item, 'CAIS-001', 'Juan Dela Cruz');
    $secondAssistance = createTransferAssistance($sourceProgram, $user, $item, 'CAIS-002', 'Maria Santos');

    $response = $this->actingAs($user)->from(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]))->patch(
        route('user.programs.assistances.bulk-transfer', [
            'department' => $department->slug,
            'program' => $sourceProgram->id,
        ]),
        [
            'assistance_ids' => [$firstAssistance->id, $secondAssistance->id],
            'target_program_id' => $targetProgram->id,
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]));

    expect($firstAssistance->fresh()->program_id)->toBe($targetProgram->id)
        ->and($secondAssistance->fresh()->program_id)->toBe($targetProgram->id);
});

test('assistance cannot be transferred when target program is missing required items', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $sourceProgram = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $targetProgram = Program::create([
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

    $sourceProgram->item()->attach($item->id);

    $assistance = createTransferAssistance($sourceProgram, $user, $item);

    $response = $this->actingAs($user)->from(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]))->patch(
        route('user.programs.assistances.transfer', [
            'department' => $department->slug,
            'program' => $sourceProgram->id,
            'assistance' => $assistance->id,
        ]),
        [
            'target_program_id' => $targetProgram->id,
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]))
        ->assertSessionHasErrors('target_program_id');

    expect($assistance->fresh()->program_id)->toBe($sourceProgram->id);
});

test('assistance cannot be transferred from a closed program', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $sourceProgram = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => true,
        'is_organization' => false,
    ]);

    $targetProgram = Program::create([
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

    $sourceProgram->item()->attach($item->id);
    $targetProgram->item()->attach($item->id);

    $assistance = createTransferAssistance($sourceProgram, $user, $item);

    $response = $this->actingAs($user)->from(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]))->patch(
        route('user.programs.assistances.transfer', [
            'department' => $department->slug,
            'program' => $sourceProgram->id,
            'assistance' => $assistance->id,
        ]),
        [
            'target_program_id' => $targetProgram->id,
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]))
        ->assertSessionHasErrors('program');

    expect($assistance->fresh()->program_id)->toBe($sourceProgram->id);
});

test('program show page includes transfer program options for other open programs', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $sourceProgram = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $openTargetProgram = Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    Program::create([
        'name' => 'Closed Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => true,
        'is_organization' => false,
    ]);

    $response = $this->actingAs($user)->get(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $sourceProgram->id,
    ]));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('user/programs/show')
            ->loadDeferredProps('table', fn ($reload) => $reload
                ->has('transfer_program_options', 1)
                ->where('transfer_program_options.0.id', $openTargetProgram->id)
                ->where('transfer_program_options.0.name', 'Beta Program')));
});
