<?php

use App\Models\Department;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can update funds in their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $fund = Fund::factory()->create([
        'name' => 'General Fund',
        'amount' => '10000',
        'year' => '2025',
        'is_active' => true,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->put(route('user.funds.update', [
        'department' => $department->slug,
        'fund' => $fund->id,
    ]), [
        'name' => 'Updated Fund',
        'amount' => '25000',
        'year' => '2026',
        'is_active' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('funds', [
        'id' => $fund->id,
        'name' => 'Updated Fund',
        'amount' => '25000',
        'year' => '2026',
        'is_active' => false,
        'department_id' => $department->id,
    ]);
});

test('authenticated users cannot update funds from another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $fundInB = Fund::factory()->create([
        'name' => 'Department B Fund',
        'department_id' => $departmentB->id,
    ]);

    $this->actingAs($user)
        ->put(route('user.funds.update', [
            'department' => $departmentB->slug,
            'fund' => $fundInB->id,
        ]), [
            'name' => 'Attempted Update',
            'amount' => '99999',
            'year' => '2026',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('funds', [
        'id' => $fundInB->id,
        'name' => 'Department B Fund',
    ]);
});
