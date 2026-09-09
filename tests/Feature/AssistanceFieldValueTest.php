<?php

use App\Enums\ProgramFieldType;
use App\Models\Assistance;
use App\Models\AssistanceFieldValue;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\ProgramField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     item: Item,
 *     beneficiary: Beneficiary,
 *     mode: ModeOfRequest,
 *     requiredField: ProgramField,
 *     optionalField: ProgramField
 * }
 */
function createAssistanceFieldFixtures(Department $department, Program $program): array
{
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program->item()->attach($item->id);

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-100',
        'name' => 'Ana Reyes',
        'beneficiable_type' => 'App\Models\Individual',
        'beneficiable_id' => 1,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    $requiredField = ProgramField::factory()->forProgram($program)->required()->showInTable()->create([
        'label' => 'Household Size',
        'key' => 'household_size',
        'type' => ProgramFieldType::Number->value,
        'sort_order' => 0,
    ]);

    $optionalField = ProgramField::factory()->forProgram($program)->create([
        'label' => 'Notes',
        'key' => 'notes',
        'type' => ProgramFieldType::Text->value,
        'sort_order' => 1,
    ]);

    return [
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
        'requiredField' => $requiredField,
        'optionalField' => $optionalField,
    ];
}

test('assistance store saves program field values', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    [
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
        'requiredField' => $requiredField,
        'optionalField' => $optionalField,
    ] = createAssistanceFieldFixtures($department, $program);

    $response = $this->actingAs($user)->from(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]))->post(route('user.programs.assistances.store', [
        'department' => $department->slug,
        'program' => $program->id,
    ]), [
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'recorded_at' => '2026-05-20',
        'remark' => 'Initial request',
        'item_details' => [
            [
                'item_id' => $item->id,
                'quantity' => 2,
                'specification' => null,
            ],
        ],
        'field_values' => [
            [
                'program_field_id' => $requiredField->id,
                'value' => '5',
            ],
            [
                'program_field_id' => $optionalField->id,
                'value' => 'Needs follow-up',
            ],
        ],
    ]);

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $assistance = Assistance::query()->first();
    expect($assistance)->not->toBeNull();

    $this->assertDatabaseHas('assistance_field_values', [
        'assistance_id' => $assistance->id,
        'program_field_id' => $requiredField->id,
        'value' => '5',
    ]);

    $this->assertDatabaseHas('assistance_field_values', [
        'assistance_id' => $assistance->id,
        'program_field_id' => $optionalField->id,
        'value' => 'Needs follow-up',
    ]);
});

test('assistance store requires required program fields', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    [
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
        'requiredField' => $requiredField,
    ] = createAssistanceFieldFixtures($department, $program);

    $response = $this->actingAs($user)->from(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]))->post(route('user.programs.assistances.store', [
        'department' => $department->slug,
        'program' => $program->id,
    ]), [
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'recorded_at' => '2026-05-20',
        'item_details' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
            ],
        ],
        'field_values' => [
            [
                'program_field_id' => $requiredField->id,
                'value' => '',
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('field_values');
    expect(Assistance::query()->count())->toBe(0);
});

test('assistance update replaces program field values', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    [
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
        'requiredField' => $requiredField,
        'optionalField' => $optionalField,
    ] = createAssistanceFieldFixtures($department, $program);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-05-20',
        'remark' => null,
        'user_id' => $user->id,
    ]);

    AssistanceFieldValue::create([
        'assistance_id' => $assistance->id,
        'program_field_id' => $requiredField->id,
        'value' => '3',
    ]);

    AssistanceFieldValue::create([
        'assistance_id' => $assistance->id,
        'program_field_id' => $optionalField->id,
        'value' => 'Old note',
    ]);

    $response = $this->actingAs($user)->put(route('user.programs.assistances.update', [
        'department' => $department->slug,
        'program' => $program->id,
        'assistance' => $assistance->id,
    ]), [
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'remark' => 'Updated',
        'item_details' => [
            [
                'item_id' => $item->id,
                'quantity' => 4,
            ],
        ],
        'field_values' => [
            [
                'program_field_id' => $requiredField->id,
                'value' => '8',
            ],
            [
                'program_field_id' => $optionalField->id,
                'value' => '',
            ],
        ],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('assistance_field_values', [
        'assistance_id' => $assistance->id,
        'program_field_id' => $requiredField->id,
        'value' => '8',
    ]);

    $this->assertDatabaseMissing('assistance_field_values', [
        'assistance_id' => $assistance->id,
        'program_field_id' => $optionalField->id,
    ]);
});

test('assistance edit payload includes field values', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    [
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
        'requiredField' => $requiredField,
    ] = createAssistanceFieldFixtures($department, $program);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-05-20',
        'remark' => null,
        'user_id' => $user->id,
    ]);

    $assistance->assistanceItem()->create([
        'item_id' => $item->id,
        'quantity' => 1,
        'specification' => null,
        'is_received' => false,
    ]);

    AssistanceFieldValue::create([
        'assistance_id' => $assistance->id,
        'program_field_id' => $requiredField->id,
        'value' => '4',
    ]);

    $response = $this->actingAs($user)->getJson(route('user.programs.assistances.edit', [
        'department' => $department->slug,
        'program' => $program->id,
        'assistance' => $assistance->id,
    ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.field_values.0.program_field_id', $requiredField->id);
    $response->assertJsonPath('data.field_values.0.value', '4');
});
