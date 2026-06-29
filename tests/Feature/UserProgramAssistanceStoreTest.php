<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\RequestSubStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * @return array{item: Item, beneficiary: Beneficiary, mode: ModeOfRequest}
 */
function createAssistanceStoreFixtures(Department $department, Program $program): array
{
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program->item()->attach($item->id);

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\Models\Individual',
        'beneficiable_id' => 1,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    return [
        'item' => $item,
        'beneficiary' => $beneficiary,
        'mode' => $mode,
    ];
}

test('guests cannot create program assistance', function () {
    $department = Department::create(['name' => 'Department A']);
    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    ['item' => $item, 'beneficiary' => $beneficiary, 'mode' => $mode] = createAssistanceStoreFixtures(
        $department,
        $program,
    );

    $response = $this->post(route('user.programs.assistances.store', [
        'department' => $department->slug,
        'program' => $program->id,
    ]), [
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'recorded_at' => now()->toDateString(),
        'item_details' => [
            [
                'item_id' => $item->id,
                'quantity' => 2,
                'specification' => '25kg sack',
            ],
        ],
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated users can create assistance for their department program', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    ['item' => $item, 'beneficiary' => $beneficiary, 'mode' => $mode] = createAssistanceStoreFixtures(
        $department,
        $program,
    );

    $response = $this->actingAs($user)->post(route('user.programs.assistances.store', [
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
                'quantity' => 3,
                'specification' => 'priority',
            ],
        ],
    ]);

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $assistance = Assistance::query()->first();

    expect($assistance)->not->toBeNull()
        ->and($assistance->program_id)->toBe($program->id)
        ->and($assistance->beneficiary_id)->toBe($beneficiary->id)
        ->and($assistance->mode_of_request_id)->toBe($mode->id)
        ->and(Carbon::parse($assistance->date_requested)->toDateString())->toBe('2026-05-20')
        ->and($assistance->remark)->toBe('Initial request')
        ->and($assistance->user_id)->toBe($user->id);

    $assistanceItem = AssistanceItem::query()
        ->where('assistance_id', $assistance->id)
        ->first();

    expect($assistanceItem)->not->toBeNull()
        ->and($assistanceItem->item_id)->toBe($item->id)
        ->and($assistanceItem->quantity)->toBe(3)
        ->and($assistanceItem->specification)->toBe('priority');

    $inProgressSubStatusId = RequestSubStatus::query()
        ->where('name', 'In Progress')
        ->value('id');

    if ($inProgressSubStatusId !== null) {
        expect(
            AssistanceRequestSubStatus::query()
                ->where('assistance_id', $assistance->id)
                ->where('request_sub_status_id', $inProgressSubStatusId)
                ->exists(),
        )->toBeTrue();
    }
});

test('users cannot create assistance for a closed program', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $program = Program::create([
        'name' => 'Closed Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => true,
        'is_organization' => false,
    ]);

    ['item' => $item, 'beneficiary' => $beneficiary, 'mode' => $mode] = createAssistanceStoreFixtures(
        $department,
        $program,
    );

    $this->actingAs($user)
        ->post(route('user.programs.assistances.store', [
            'department' => $department->slug,
            'program' => $program->id,
        ]), [
            'beneficiary_id' => $beneficiary->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'specification' => null,
                ],
            ],
        ])
        ->assertSessionHasErrors('program');

    expect(Assistance::query()->count())->toBe(0);
});

test('users cannot create assistance for another department program', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $program = Program::create([
        'name' => 'Other Program',
        'descriptions' => '',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    ['item' => $item, 'beneficiary' => $beneficiary, 'mode' => $mode] = createAssistanceStoreFixtures(
        $departmentB,
        $program,
    );

    $this->actingAs($user)
        ->post(route('user.programs.assistances.store', [
            'department' => $departmentA->slug,
            'program' => $program->id,
        ]), [
            'beneficiary_id' => $beneficiary->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateString(),
            'item_details' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'specification' => null,
                ],
            ],
        ])
        ->assertNotFound();

    expect(Assistance::query()->count())->toBe(0);
});
