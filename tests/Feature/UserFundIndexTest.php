<?php

use App\Models\Department;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view the user funds page', function () {
    $response = $this->get(route('user.funds.index', ['department' => 'any-department']));

    $response->assertRedirect(route('login'));
});

test('authenticated users only see funds for the requested department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $fundInA = Fund::factory()->create([
        'name' => 'General Fund',
        'department_id' => $departmentA->id,
    ]);

    Fund::factory()->create([
        'name' => 'Other Fund',
        'department_id' => $departmentB->id,
    ]);

    $response = $this->actingAs($user)->get(route('user.funds.index', ['department' => $departmentA->slug]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('user/funds/index')
        ->has('funds.data', 1)
        ->where('funds.data.0.id', $fundInA->id)
        ->where('funds.data.0.name', 'General Fund')
        ->where('department.id', $departmentA->id)
        ->where('department.slug', $departmentA->slug)
        ->where('status', []));
});

test('users can filter funds by status and search', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $activeFund = Fund::factory()->create([
        'name' => 'Active General Fund',
        'department_id' => $department->id,
        'is_active' => true,
    ]);

    Fund::factory()->inactive()->create([
        'name' => 'Inactive Reserve Fund',
        'department_id' => $department->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.funds.index', [
            'department' => $department->slug,
            'status' => ['active'],
            'search' => 'General',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/funds/index')
            ->has('funds.data', 1)
            ->where('funds.data.0.id', $activeFund->id)
            ->where('search', 'General')
            ->where('status', ['active']));
});

test('users can load additional funds via pagination', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    foreach (range(1, 13) as $index) {
        Fund::factory()->create([
            'name' => sprintf('Fund %02d', $index),
            'department_id' => $department->id,
        ]);
    }

    $this->actingAs($user)
        ->get(route('user.funds.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('funds.data', 12)
            ->where('funds.current_page', 1));

    $this->actingAs($user)
        ->get(route('user.funds.index', [
            'department' => $department->slug,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('funds.data', 1)
            ->where('funds.current_page', 2)
            ->where('funds.data.0.name', 'Fund 13'));
});

test('authenticated users cannot view another departments fund list', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.funds.index', ['department' => $departmentB->slug]))
        ->assertForbidden();
});
