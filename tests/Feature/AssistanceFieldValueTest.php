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
use Illuminate\Testing\TestResponse;

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

/**
 * @return array{
 *     department: Department,
 *     user: User,
 *     program: Program,
 *     item: Item,
 *     beneficiary: Beneficiary,
 *     mode: ModeOfRequest
 * }
 */
function createAssistanceEncodeContext(): array
{
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

    return compact('department', 'user', 'program', 'item', 'beneficiary', 'mode');
}

/**
 * @param  list<array{program_field_id: int, value?: string|null}>  $fieldValues
 */
function storeAssistanceWithFieldValues(
    User $user,
    Department $department,
    Program $program,
    Item $item,
    Beneficiary $beneficiary,
    ModeOfRequest $mode,
    array $fieldValues,
): TestResponse {
    return test()->actingAs($user)->from(route('user.programs.show', [
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
        'field_values' => $fieldValues,
    ]);
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

test('assistance store rejects invalid program field values', function (
    string $type,
    ?array $options,
    string $value,
    string $message,
) {
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
    ] = createAssistanceEncodeContext();

    $field = ProgramField::factory()->forProgram($program)->create([
        'label' => 'Custom',
        'key' => 'custom',
        'type' => $type,
        'options' => $options,
    ]);

    $response = storeAssistanceWithFieldValues(
        $user,
        $department,
        $program,
        $item,
        $beneficiary,
        $mode,
        [
            [
                'program_field_id' => $field->id,
                'value' => $value,
            ],
        ],
    );

    $response->assertRedirect();
    $response->assertSessionHasErrors(['field_values' => $message]);
    expect(Assistance::query()->count())->toBe(0);
})->with([
    'non-numeric number' => [
        ProgramFieldType::Number->value,
        null,
        'abc',
        'The Custom field must be a number.',
    ],
    'unparseable date' => [
        ProgramFieldType::Date->value,
        null,
        'not-a-date',
        'The Custom field must be a valid date.',
    ],
    'select not in options' => [
        ProgramFieldType::Select->value,
        ['Farming', 'Fishing'],
        'Mining',
        'The selected Custom is invalid.',
    ],
    'invalid boolean token' => [
        ProgramFieldType::Boolean->value,
        null,
        'maybe',
        'The Custom field must be yes or no.',
    ],
]);

test('assistance store requires a required field when it is omitted', function () {
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
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors([
        'field_values' => "The {$requiredField->label} field is required.",
    ]);
    expect(Assistance::query()->count())->toBe(0);
});

test('assistance store accepts no for a required boolean field', function () {
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
    ] = createAssistanceEncodeContext();

    $field = ProgramField::factory()->forProgram($program)->required()->create([
        'label' => 'Eligible',
        'key' => 'eligible',
        'type' => ProgramFieldType::Boolean->value,
    ]);

    $response = storeAssistanceWithFieldValues(
        $user,
        $department,
        $program,
        $item,
        $beneficiary,
        $mode,
        [
            [
                'program_field_id' => $field->id,
                'value' => '0',
            ],
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $assistance = Assistance::query()->first();
    expect($assistance)->not->toBeNull();

    $this->assertDatabaseHas('assistance_field_values', [
        'assistance_id' => $assistance->id,
        'program_field_id' => $field->id,
        'value' => '0',
    ]);
});

test('assistance store rejects a field that belongs to another program', function () {
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
    ] = createAssistanceEncodeContext();

    $otherProgram = Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    ProgramField::factory()->forProgram($program)->create([
        'label' => 'Notes',
        'key' => 'notes',
        'type' => ProgramFieldType::Text->value,
    ]);

    $foreignField = ProgramField::factory()->forProgram($otherProgram)->create([
        'label' => 'School',
        'key' => 'school',
        'type' => ProgramFieldType::Text->value,
    ]);

    $response = storeAssistanceWithFieldValues(
        $user,
        $department,
        $program,
        $item,
        $beneficiary,
        $mode,
        [
            [
                'program_field_id' => $foreignField->id,
                'value' => 'Central High',
            ],
        ],
    );

    $response->assertRedirect();
    $response->assertSessionHasErrors([
        'field_values.0.program_field_id' => 'The selected custom field is invalid.',
    ]);
    expect(Assistance::query()->count())->toBe(0);
    expect(AssistanceFieldValue::query()->count())->toBe(0);
});

test('program show exposes program fields in sort order', function () {
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
    ] = createAssistanceEncodeContext();

    ProgramField::factory()->forProgram($program)->create([
        'label' => 'Later',
        'key' => 'later',
        'type' => ProgramFieldType::Text->value,
        'sort_order' => 1,
    ]);
    ProgramField::factory()->forProgram($program)->create([
        'label' => 'Earlier',
        'key' => 'earlier',
        'type' => ProgramFieldType::Text->value,
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($user)->get(
        route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]),
        inertiaPartialHeaders('user/programs/show', ['program_fields']),
    );

    $response->assertOk();
    $response->assertJsonPath('props.program_fields.0.key', 'earlier');
    $response->assertJsonPath('props.program_fields.0.label', 'Earlier');
    $response->assertJsonPath('props.program_fields.1.key', 'later');
    $response->assertJsonPath('props.program_fields.1.label', 'Later');
});

test('assistance table displays boolean field values as yes or no', function () {
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
    ] = createAssistanceEncodeContext();

    $field = ProgramField::factory()->forProgram($program)->showInTable()->create([
        'label' => 'Eligible',
        'key' => 'eligible',
        'type' => ProgramFieldType::Boolean->value,
    ]);

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
        'program_field_id' => $field->id,
        'value' => '1',
    ]);

    $response = $this->actingAs($user)->get(
        route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]),
        inertiaPartialHeaders('user/programs/show', ['assistances']),
    );

    $response->assertOk();
    $response->assertJsonPath('props.assistances.data.0.field_values.eligible', 'Yes');
});
