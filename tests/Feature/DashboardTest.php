<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users without a department can visit the dashboard empty state', function () {
    $user = User::factory()->create([
        'department_id' => null,
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('noDepartment', true));
});

test('authenticated users with a department are redirected to the department dashboard', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.dashboard.index', ['department' => $department->slug]));
});
