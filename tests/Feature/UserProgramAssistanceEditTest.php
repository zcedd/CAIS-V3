<?php

use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can fetch assistance edit payload for their department program', function () {
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
        'remark' => 'Needs follow up',
        'user_id' => $user->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'specification' => '25 kg',
        'is_received' => false,
    ]);

    $response = $this->actingAs($user)->getJson(
        route('user.programs.assistances.edit', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]),
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $assistance->id)
        ->assertJsonPath('data.beneficiary_id', $beneficiary->id)
        ->assertJsonPath('data.beneficiary.name', 'Juan Dela Cruz')
        ->assertJsonPath('data.mode_of_request_id', $mode->id)
        ->assertJsonPath('data.remark', 'Needs follow up')
        ->assertJsonPath('data.item_details.0.item_id', $item->id)
        ->assertJsonPath('data.item_details.0.quantity', 2)
        ->assertJsonPath('data.item_details.0.specification', '25 kg');
});
