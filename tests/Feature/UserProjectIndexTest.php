<?php

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view the user projects page', function () {
    $response = $this->get(route('user.projects.index', ['department' => 'any-department']));

    $response->assertRedirect(route('login'));
});

test('authenticated users only see projects for their department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $projectInA = Project::create([
        'name' => 'Alpha Project',
        'descriptions' => 'For department A',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentA->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    Project::create([
        'name' => 'Beta Project',
        'descriptions' => 'For department B',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $response = $this->actingAs($user)->get(route('user.projects.index', ['department' => $departmentA->slug]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('user/projects/index')
        ->has('projects', 1)
        ->where('projects.0.id', $projectInA->id)
        ->where('projects.0.name', 'Alpha Project')
        ->where('department.id', $departmentA->id)
        ->where('department.name', 'Department A')
        ->where('department.slug', $departmentA->slug));
});

test('authenticated users cannot view another departments project list', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.projects.index', ['department' => $departmentB->slug]))
        ->assertForbidden();
});
