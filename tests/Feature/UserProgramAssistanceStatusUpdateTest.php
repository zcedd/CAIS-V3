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

test('authenticated users can update assistance status for their department program', function () {
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

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
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

    $inProgressSubStatusId = RequestSubStatus::query()
        ->where('name', 'In Progress')
        ->value('id');

    $verifiedSubStatusId = RequestSubStatus::query()
        ->where('name', 'Verified')
        ->value('id');

    expect($inProgressSubStatusId)->not->toBeNull()
        ->and($verifiedSubStatusId)->not->toBeNull();

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $inProgressSubStatusId,
        'remark' => null,
        'recorded_at' => '2026-05-01 00:00:00',
    ]);

    $response = $this->actingAs($user)->patch(
        route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]),
        [
            'request_sub_status_id' => $verifiedSubStatusId,
            'recorded_at' => '2026-05-10',
            'remark' => 'Verified after review',
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $latestSubStatus = AssistanceRequestSubStatus::query()
        ->where('assistance_id', $assistance->id)
        ->where('request_sub_status_id', $verifiedSubStatusId)
        ->latest('recorded_at')
        ->first();

    expect($latestSubStatus)->not->toBeNull()
        ->and($latestSubStatus->remark)->toBe('Verified after review')
        ->and(Carbon::parse($latestSubStatus->recorded_at)->toDateString())->toBe('2026-05-10');
});

test('updating assistance status preserves the recorded at time', function () {
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

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-002',
        'name' => 'Maria Santos',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 2,
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

    $inProgressSubStatusId = RequestSubStatus::query()
        ->where('name', 'In Progress')
        ->value('id');

    $verifiedSubStatusId = RequestSubStatus::query()
        ->where('name', 'Verified')
        ->value('id');

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => $inProgressSubStatusId,
        'remark' => null,
        'recorded_at' => '2026-05-01 00:00:00',
    ]);

    $this->actingAs($user)->patch(
        route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]),
        [
            'request_sub_status_id' => $verifiedSubStatusId,
            'recorded_at' => '2026-05-10 14:30:00',
            'remark' => 'Verified in the afternoon',
        ],
    )->assertRedirect();

    $latestSubStatus = AssistanceRequestSubStatus::query()
        ->where('assistance_id', $assistance->id)
        ->where('request_sub_status_id', $verifiedSubStatusId)
        ->latest('recorded_at')
        ->first();

    expect($latestSubStatus)->not->toBeNull()
        ->and(Carbon::parse($latestSubStatus->recorded_at)->toDateTimeString())->toBe('2026-05-10 14:30:00');
});

test('updating to delivered status requires and marks the selected assistance items as received', function () {
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

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $riceItem = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $milkItem = Item::create([
        'name' => 'Milk',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program->item()->attach([$riceItem->id, $milkItem->id]);

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
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

    $riceAssistanceItem = AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $riceItem->id,
        'quantity' => 2,
        'specification' => 'Premium',
        'is_received' => false,
    ]);

    $milkAssistanceItem = AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $milkItem->id,
        'quantity' => 5,
        'specification' => null,
        'is_received' => false,
    ]);

    $deliveredSubStatusId = RequestSubStatus::query()
        ->where('name', 'Successfully Delivered')
        ->value('id');

    expect($deliveredSubStatusId)->not->toBeNull();

    $response = $this->actingAs($user)->patch(
        route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]),
        [
            'request_sub_status_id' => $deliveredSubStatusId,
            'recorded_at' => '2026-05-15',
            'remark' => 'Handed over to beneficiary',
            'delivered_items' => [
                [
                    'assistance_item_id' => $riceAssistanceItem->id,
                    'quantity' => 2,
                    'specification' => 'Premium grade',
                ],
                [
                    'assistance_item_id' => $milkAssistanceItem->id,
                    'quantity' => 3,
                    'specification' => 'Powdered',
                ],
            ],
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $riceAssistanceItem->refresh();
    $milkAssistanceItem->refresh();
    $assistance->refresh();

    $deliveredMilkItem = AssistanceItem::query()
        ->where('assistance_id', $assistance->id)
        ->where('item_id', $milkItem->id)
        ->where('is_received', true)
        ->first();

    expect($riceAssistanceItem->is_received)->toBeTrue()
        ->and($riceAssistanceItem->quantity)->toBe(2)
        ->and($riceAssistanceItem->specification)->toBe('Premium grade')
        ->and($milkAssistanceItem->is_received)->toBeFalse()
        ->and($milkAssistanceItem->quantity)->toBe(2)
        ->and($deliveredMilkItem)->not->toBeNull()
        ->and($deliveredMilkItem->quantity)->toBe(3)
        ->and($deliveredMilkItem->specification)->toBe('Powdered')
        ->and($assistance->date_delivered)->toBe('2026-05-15');
});

test('delivered status update requires delivered items', function () {
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

    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
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

    $deliveredSubStatusId = RequestSubStatus::query()
        ->where('name', 'Successfully Delivered')
        ->value('id');

    $response = $this->actingAs($user)->from(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]))->patch(
        route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]),
        [
            'request_sub_status_id' => $deliveredSubStatusId,
            'recorded_at' => '2026-05-15',
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));
    $response->assertSessionHasErrors('delivered_items');
});
