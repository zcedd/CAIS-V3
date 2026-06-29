<?php

use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{fund: Fund, item: Item}
 */
function createDepartmentFundAndItem(Department $department): array
{
    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    return ['fund' => $fund, 'item' => $item];
}

test('guests cannot create programs', function () {
    $department = Department::create(['name' => 'Department A']);
    ['fund' => $fund, 'item' => $item] = createDepartmentFundAndItem($department);

    $response = $this->post(route('user.programs.store', ['department' => $department->slug]), [
        'name' => 'New Program',
        'descriptions' => 'Description',
        'start_at' => now()->toDateString(),
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated users can create programs for their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    ['fund' => $fund, 'item' => $item] = createDepartmentFundAndItem($department);

    $response = $this->actingAs($user)->post(route('user.programs.store', ['department' => $department->slug]), [
        'name' => 'New Program',
        'descriptions' => 'A new assistance program',
        'start_at' => '2026-01-01',
        'end_at' => '2026-12-31',
        'is_organization' => true,
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
    ]);

    $response->assertRedirect(route('user.programs.index', ['department' => $department->slug]));
    $response->assertSessionHas('success');

    $program = Program::query()->where('name', 'New Program')->first();

    expect($program)->not->toBeNull();

    $this->assertDatabaseHas('programs', [
        'id' => $program->id,
        'name' => 'New Program',
        'descriptions' => 'A new assistance program',
        'department_id' => $department->id,
        'is_organization' => true,
        'is_closed' => false,
    ]);

    $this->assertDatabaseHas('fund_program', [
        'program_id' => $program->id,
        'fund_id' => $fund->id,
    ]);

    $this->assertDatabaseHas('item_program', [
        'program_id' => $program->id,
        'item_id' => $item->id,
    ]);
});

test('authenticated users cannot create programs for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    ['fund' => $fund, 'item' => $item] = createDepartmentFundAndItem($departmentA);

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $departmentB->slug]), [
            'name' => 'New Program',
            'descriptions' => 'Description',
            'start_at' => now()->toDateString(),
            'fund_ids' => [$fund->id],
            'item_ids' => [$item->id],
        ])
        ->assertForbidden();

    expect(Program::query()->count())->toBe(0);
});

test('program creation requires valid input', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->post(route('user.programs.store', ['department' => $department->slug]), []);

    $response->assertSessionHasErrors([
        'name',
        'descriptions',
        'start_at',
        'fund_ids',
        'item_ids',
    ]);
    expect(Program::query()->count())->toBe(0);
});

test('users cannot attach funds or items from another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    ['fund' => $fundInB, 'item' => $itemInB] = createDepartmentFundAndItem($departmentB);

    $this->actingAs($user)
        ->post(route('user.programs.store', ['department' => $departmentA->slug]), [
            'name' => 'New Program',
            'descriptions' => 'Description',
            'start_at' => now()->toDateString(),
            'fund_ids' => [$fundInB->id],
            'item_ids' => [$itemInB->id],
        ])
        ->assertSessionHasErrors(['fund_ids.0', 'item_ids.0']);

    expect(Program::query()->count())->toBe(0);
});
