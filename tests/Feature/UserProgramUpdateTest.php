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
 * @return array{fund: Fund, item: Item, otherFund: Fund, otherItem: Item}
 */
function createDepartmentFundsAndItems(Department $department): array
{
    $fund = Fund::create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);

    $otherFund = Fund::create([
        'name' => 'Reserve Fund',
        'amount' => '5000',
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

    $otherItem = Item::create([
        'name' => 'Noodles',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    return [
        'fund' => $fund,
        'item' => $item,
        'otherFund' => $otherFund,
        'otherItem' => $otherItem,
    ];
}

test('guests cannot update programs', function () {
    $department = Department::create(['name' => 'Department A']);
    ['fund' => $fund, 'item' => $item] = createDepartmentFundsAndItems($department);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $program->fund()->attach($fund->id);
    $program->item()->attach($item->id);

    $response = $this->put(route('user.programs.update', [
        'department' => $department->slug,
        'program' => $program->id,
    ]), [
        'name' => 'Updated Program',
        'descriptions' => 'Updated description',
        'start_at' => '2026-02-01',
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated users can update programs for their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    ['fund' => $fund, 'item' => $item, 'otherFund' => $otherFund, 'otherItem' => $otherItem] = createDepartmentFundsAndItems($department);

    $program = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'Details',
        'start_at' => '2026-01-01',
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $program->fund()->attach($fund->id);
    $program->item()->attach($item->id);

    $response = $this->actingAs($user)->put(route('user.programs.update', [
        'department' => $department->slug,
        'program' => $program->id,
    ]), [
        'name' => 'Updated Program',
        'descriptions' => 'Updated description',
        'start_at' => '2026-02-01',
        'end_at' => '2026-12-31',
        'is_organization' => true,
        'is_closed' => true,
        'fund_ids' => [$otherFund->id],
        'item_ids' => [$otherItem->id],
    ]);

    $response->assertRedirect(route('user.programs.show', [
        'department' => $department->slug,
        'program' => $program->id,
    ]));
    $response->assertSessionHas('success');

    $program->refresh();

    expect($program->name)->toBe('Updated Program');
    expect($program->descriptions)->toBe('Updated description');
    expect($program->is_organization)->toBeTrue();
    expect($program->is_closed)->toBeTrue();

    $this->assertDatabaseHas('fund_program', [
        'program_id' => $program->id,
        'fund_id' => $otherFund->id,
    ]);

    $this->assertDatabaseHas('item_program', [
        'program_id' => $program->id,
        'item_id' => $otherItem->id,
    ]);
});

test('users cannot update programs outside their department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    ['fund' => $fund, 'item' => $item] = createDepartmentFundsAndItems($departmentB);

    $program = Program::create([
        'name' => 'Other Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $program->fund()->attach($fund->id);
    $program->item()->attach($item->id);

    $response = $this->actingAs($user)->put(route('user.programs.update', [
        'department' => $departmentB->slug,
        'program' => $program->id,
    ]), [
        'name' => 'Hijacked Program',
        'descriptions' => 'Updated description',
        'start_at' => '2026-02-01',
        'fund_ids' => [$fund->id],
        'item_ids' => [$item->id],
    ]);

    $response->assertForbidden();
});
