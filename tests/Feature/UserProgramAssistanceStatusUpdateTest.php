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

test('updating to delivered status requires and marks the selected assistance item as received', function () {
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

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program->item()->attach($item->id);

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

    $assistanceItem = AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 2,
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
            'delivered_assistance_item_id' => $assistanceItem->id,
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $assistanceItem->refresh();
    $assistance->refresh();

    expect($assistanceItem->is_received)->toBeTrue()
        ->and($assistance->date_delivered)->toBe('2026-05-15');
});

test('delivered status update requires a delivered assistance item', function () {
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
    $response->assertSessionHasErrors('delivered_assistance_item_id');
});
