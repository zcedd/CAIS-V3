<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\User;
use App\Support\StockMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('authenticated users can receive and allocate item stock', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program = Program::create([
        'name' => 'Relief',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $program->item()->attach($item->id);

    $this->actingAs($user)
        ->post(route('user.items.stock.receipts.store', [
            'department' => $department->slug,
            'item' => $item->id,
        ]), [
            'quantity' => 20,
            'type' => StockMovementType::OpeningBalance,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('user.items.stock.allocations.store', [
            'department' => $department->slug,
            'item' => $item->id,
        ]), [
            'program_id' => $program->id,
            'type' => StockMovementType::Allocate,
            'quantity' => 8,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $balance = ItemStockBalance::query()->where('item_id', $item->id)->first();

    expect($balance->on_hand)->toBe(20)
        ->and($balance->allocated)->toBe(8)
        ->and($balance->available)->toBe(12);

    $this->actingAs($user)
        ->get(route('user.items.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/items/index')
            ->where('items.data.0.on_hand', 20)
            ->where('items.data.0.available', 12));
});

test('guests cannot receive stock', function () {
    $department = Department::create(['name' => 'Department A']);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);

    $this->post(route('user.items.stock.receipts.store', [
        'department' => $department->slug,
        'item' => $item->id,
    ]), [
        'quantity' => 5,
        'type' => StockMovementType::Receipt,
    ])->assertRedirect(route('login'));
});
