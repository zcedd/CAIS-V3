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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{unit: ItemUnitMeasurement, item: Item}
 */
function createDepartmentItem(Department $department, string $name = 'Rice'): array
{
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);

    $item = Item::create([
        'name' => $name,
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    return ['unit' => $unit, 'item' => $item];
}

/**
 * @return array{item: Item, beneficiary: Beneficiary, mode: ModeOfRequest}
 */
function createAssistanceItemFixtures(Department $department, Program $program): array
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

test('guests cannot view department items', function () {
    $department = Department::create(['name' => 'Department A']);

    $this->get(route('user.items.index', ['department' => $department->slug]))
        ->assertRedirect(route('login'));
});

test('authenticated users can view items for their department', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['item' => $item] = createDepartmentItem($department);

    $this->actingAs($user)
        ->get(route('user.items.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/items/index')
            ->has('items.data', 1)
            ->where('items.data.0.id', $item->id)
            ->where('items.data.0.name', 'Rice')
            ->where('department.slug', $department->slug));
});

test('authenticated users cannot view items for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create(['department_id' => $departmentA->id]);

    $this->actingAs($user)
        ->get(route('user.items.index', ['department' => $departmentB->slug]))
        ->assertForbidden();
});

test('authenticated users can create items for their department', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $unit = ItemUnitMeasurement::create(['name' => 'pc']);

    $response = $this->actingAs($user)->post(route('user.items.store', ['department' => $department->slug]), [
        'name' => 'Blankets',
        'item_unit_measurement_id' => $unit->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('items', [
        'name' => 'Blankets',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
});

test('authenticated users cannot create items for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);
    $user = User::factory()->create(['department_id' => $departmentA->id]);
    $unit = ItemUnitMeasurement::create(['name' => 'pc']);

    $this->actingAs($user)
        ->post(route('user.items.store', ['department' => $departmentB->slug]), [
            'name' => 'Blankets',
            'item_unit_measurement_id' => $unit->id,
        ])
        ->assertForbidden();

    expect(Item::query()->count())->toBe(0);
});

test('item creation requires valid input', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    $this->actingAs($user)
        ->post(route('user.items.store', ['department' => $department->slug]), [])
        ->assertSessionHasErrors(['name', 'item_unit_measurement_id']);

    expect(Item::query()->count())->toBe(0);
});

test('authenticated users can update items for their department', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['item' => $item] = createDepartmentItem($department);
    $newUnit = ItemUnitMeasurement::create(['name' => 'box']);

    $this->actingAs($user)
        ->put(route('user.items.update', ['department' => $department->slug, 'item' => $item->id]), [
            'name' => 'Updated Rice',
            'item_unit_measurement_id' => $newUnit->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'name' => 'Updated Rice',
        'item_unit_measurement_id' => $newUnit->id,
    ]);
});

test('authenticated users cannot update items for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);
    $user = User::factory()->create(['department_id' => $departmentA->id]);
    ['item' => $item] = createDepartmentItem($departmentB);
    $unit = ItemUnitMeasurement::create(['name' => 'pc']);

    $this->actingAs($user)
        ->put(route('user.items.update', ['department' => $departmentB->slug, 'item' => $item->id]), [
            'name' => 'Updated Rice',
            'item_unit_measurement_id' => $unit->id,
        ])
        ->assertForbidden();
});

test('authenticated users can delete unused items for their department', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['item' => $item] = createDepartmentItem($department);

    $this->actingAs($user)
        ->delete(route('user.items.destroy', ['department' => $department->slug, 'item' => $item->id]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('items', ['id' => $item->id]);
});

test('users cannot delete items linked to a program', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    ['item' => $item] = createDepartmentItem($department);

    $program = Program::create([
        'name' => 'Relief Program',
        'descriptions' => 'Description',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $program->item()->attach($item->id);

    $this->actingAs($user)
        ->from(route('user.items.index', ['department' => $department->slug]))
        ->delete(route('user.items.destroy', ['department' => $department->slug, 'item' => $item->id]))
        ->assertRedirect()
        ->assertSessionHasErrors(['item']);

    expect(Item::query()->whereKey($item->id)->exists())->toBeTrue();
});

test('users cannot delete items linked to an assistance', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    $program = Program::create([
        'name' => 'Relief Program',
        'descriptions' => 'Description',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    ['item' => $item, 'beneficiary' => $beneficiary, 'mode' => $mode] = createAssistanceItemFixtures(
        $department,
        $program,
    );

    $assistance = Assistance::create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    AssistanceItem::create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'is_received' => false,
    ]);

    $this->actingAs($user)
        ->from(route('user.items.index', ['department' => $department->slug]))
        ->delete(route('user.items.destroy', ['department' => $department->slug, 'item' => $item->id]))
        ->assertRedirect()
        ->assertSessionHasErrors(['item']);

    expect(Item::query()->whereKey($item->id)->exists())->toBeTrue();
});

test('authenticated users cannot delete items for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);
    $user = User::factory()->create(['department_id' => $departmentA->id]);
    ['item' => $item] = createDepartmentItem($departmentB);

    $this->actingAs($user)
        ->delete(route('user.items.destroy', ['department' => $departmentB->slug, 'item' => $item->id]))
        ->assertForbidden();

    expect(Item::query()->whereKey($item->id)->exists())->toBeTrue();
});
