<?php

use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users cannot access another department url', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.dashboard.index', $departmentB))
        ->assertForbidden();
});

test('program update cannot attach funds or items from another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $fundA = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $departmentA->id,
    ]);

    $fundB = Fund::create([
        'name' => 'Foreign Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $departmentB->id,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $itemA = Item::create([
        'name' => 'Rice',
        'department_id' => $departmentA->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $itemB = Item::create([
        'name' => 'Noodles',
        'department_id' => $departmentB->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentA->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $program->fund()->attach($fundA->id);
    $program->item()->attach($itemA->id);

    $this->actingAs($user)->put(route('user.programs.update', [
        'department' => $departmentA->slug,
        'program' => $program->id,
    ]), [
        'name' => 'Updated Program',
        'descriptions' => 'Updated description',
        'start_at' => '2026-02-01',
        'fund_ids' => [$fundB->id],
        'item_ids' => [$itemB->id],
    ])->assertSessionHasErrors(['fund_ids.0', 'item_ids.0']);

    $program->refresh();

    $this->assertDatabaseHas('fund_program', [
        'program_id' => $program->id,
        'fund_id' => $fundA->id,
    ]);

    $this->assertDatabaseMissing('fund_program', [
        'program_id' => $program->id,
        'fund_id' => $fundB->id,
    ]);
});
