<?php

use App\Models\Department;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot create funds', function () {
    $department = Department::create(['name' => 'Department A']);

    $response = $this->post(route('user.funds.store', ['department' => $department->slug]), [
        'name' => 'New Fund',
        'amount' => '50000',
        'year' => '2026',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('login'));
});

test('authenticated users can create funds for their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->post(route('user.funds.store', ['department' => $department->slug]), [
        'name' => 'New Fund',
        'amount' => '50000',
        'year' => '2026',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('funds', [
        'name' => 'New Fund',
        'amount' => '50000',
        'year' => '2026',
        'is_active' => true,
        'department_id' => $department->id,
    ]);
});

test('authenticated users cannot create funds for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $this->actingAs($user)
        ->post(route('user.funds.store', ['department' => $departmentB->slug]), [
            'name' => 'New Fund',
            'amount' => '50000',
            'year' => '2026',
        ])
        ->assertForbidden();

    expect(Fund::query()->count())->toBe(0);
});

test('fund creation requires valid input', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->post(route('user.funds.store', ['department' => $department->slug]), []);

    $response->assertSessionHasErrors(['name']);
    expect(Fund::query()->count())->toBe(0);
});
