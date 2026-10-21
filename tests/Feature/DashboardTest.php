<?php

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
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

test('an executive without a department is redirected to the executive dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'department_id' => null,
    ]);
    $user->syncRoles([RoleName::Executive->value]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('executive.dashboard'));
});

test('an administrator without a department is redirected to the administrator dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'department_id' => null,
    ]);
    $user->syncRoles([RoleName::Administrator->value]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('administrator.dashboard'));
});

test('an executive with a department is redirected to the department dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);
    $user->syncRoles([RoleName::Executive->value]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.dashboard.index', ['department' => $department->slug]));
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
