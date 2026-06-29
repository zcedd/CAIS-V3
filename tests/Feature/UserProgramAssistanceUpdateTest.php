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

test('authenticated users can update assistance for their department program', function () {
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

    $newBeneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-002',
        'name' => 'Maria Santos',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 2,
    ]);

    $mode = ModeOfRequest::create(['name' => 'Walk In']);
    $newMode = ModeOfRequest::create(['name' => 'Call']);

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => '2026-05-01',
        'remark' => 'Old remark',
        'user_id' => $user->id,
    ]);

    $inProgressSubStatusId = RequestSubStatus::query()
        ->where('name', 'In Progress')
        ->value('id');

    if ($inProgressSubStatusId !== null) {
        AssistanceRequestSubStatus::query()->create([
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $inProgressSubStatusId,
            'remark' => null,
            'recorded_at' => '2026-05-01 00:00:00',
        ]);
    }

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'specification' => 'old',
        'is_received' => false,
    ]);

    $response = $this->actingAs($user)->put(
        route('user.programs.assistances.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]),
        [
            'beneficiary_id' => $newBeneficiary->id,
            'mode_of_request_id' => $newMode->id,
            'remark' => 'Updated remark',
            'item_details' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 4,
                    'specification' => 'new spec',
                ],
            ],
        ],
    );

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));

    $assistance->refresh();

    expect($assistance->beneficiary_id)->toBe($newBeneficiary->id)
        ->and($assistance->mode_of_request_id)->toBe($newMode->id)
        ->and(Carbon::parse($assistance->date_requested)->toDateString())->toBe('2026-05-01')
        ->and($assistance->remark)->toBe('Updated remark');

    if ($inProgressSubStatusId !== null) {
        $subStatus = AssistanceRequestSubStatus::query()
            ->where('assistance_id', $assistance->id)
            ->where('request_sub_status_id', $inProgressSubStatusId)
            ->first();

        expect($subStatus)->not->toBeNull()
            ->and(Carbon::parse($subStatus->recorded_at)->toDateString())->toBe('2026-05-01');
    }

    $assistanceItem = AssistanceItem::query()
        ->where('assistance_id', $assistance->id)
        ->first();

    expect($assistanceItem)->not->toBeNull()
        ->and($assistanceItem->quantity)->toBe(4)
        ->and($assistanceItem->specification)->toBe('new spec');
});
