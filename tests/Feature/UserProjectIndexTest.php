<?php

use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view the user programs page', function () {
    $response = $this->get(route('user.programs.index', ['department' => 'any-department']));

    $response->assertRedirect(route('login'));
});

test('authenticated users only see programs for their department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $programInA = Program::create([
        'name' => 'Alpha Program',
        'descriptions' => 'For department A',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentA->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    Program::create([
        'name' => 'Beta Program',
        'descriptions' => 'For department B',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $response = $this->actingAs($user)->get(route('user.programs.index', ['department' => $departmentA->slug]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('user/programs/index')
        ->has('programs', 1)
        ->where('programs.0.id', $programInA->id)
        ->where('programs.0.name', 'Alpha Program')
        ->where('department.id', $departmentA->id)
        ->where('department.name', 'Department A')
        ->where('department.slug', $departmentA->slug));
});

test('authenticated users cannot view another departments program list', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.index', ['department' => $departmentB->slug]))
        ->assertForbidden();
});
