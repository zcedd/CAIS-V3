<?php

use App\Enums\ItemKind;
use App\Enums\UnspscCodeLevel;
use App\Models\Department;
use App\Models\ItemUnitMeasurement;
use App\Models\UnspscCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unspsc search returns curated commodities by default', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    $this->actingAs($user)
        ->getJson(route('user.unspsc-codes.search', [
            'department' => $department->slug,
            'q' => 'Rice',
        ]))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Rice', 'is_curated' => true]);
});

test('unspsc search can include the full code set', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);

    UnspscCode::factory()->create([
        'code' => '88888888',
        'title' => 'Full set only',
        'level' => UnspscCodeLevel::Commodity->value,
        'is_curated' => false,
    ]);

    $this->actingAs($user)
        ->getJson(route('user.unspsc-codes.search', [
            'department' => $department->slug,
            'q' => 'Full set',
            'curated' => 0,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('items can be created with an optional unspsc classification', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $code = UnspscCode::query()->where('code', '50221101')->firstOrFail();

    $this->actingAs($user)
        ->post(route('user.items.store', ['department' => $department->slug]), [
            'name' => 'Rice 25kg',
            'kind' => ItemKind::Goods->value,
            'item_unit_measurement_id' => $unit->id,
            'unspsc_code_id' => $code->id,
            'is_perishable' => 1,
            'low_stock_threshold' => 10,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('items', [
        'name' => 'Rice 25kg',
        'unspsc_code_id' => $code->id,
        'is_perishable' => 1,
        'low_stock_threshold' => 10,
    ]);
});
