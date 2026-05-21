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
        ->has('programs.data', 1)
        ->where('programs.data.0.id', $programInA->id)
        ->where('programs.data.0.name', 'Alpha Program')
        ->where('department.id', $departmentA->id)
        ->where('department.name', 'Department A')
        ->where('department.slug', $departmentA->slug)
        ->where('type', [])
        ->where('status', []));
});

test('users can filter programs by type and status', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $individualOpen = Program::create([
        'name' => 'Individual Open',
        'descriptions' => 'Individual assistance',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    Program::create([
        'name' => 'Organization Closed',
        'descriptions' => 'Organization assistance',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => true,
        'is_organization' => true,
    ]);

    $this->actingAs($user)
        ->get(route('user.programs.index', [
            'department' => $department->slug,
            'type' => ['individual'],
            'status' => ['open'],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/programs/index')
            ->has('programs.data', 1)
            ->where('programs.data.0.id', $individualOpen->id)
            ->where('type', ['individual'])
            ->where('status', ['open']));
});

test('users can load additional programs via pagination', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    foreach (range(1, 13) as $index) {
        Program::create([
            'name' => "Program {$index}",
            'descriptions' => "Description {$index}",
            'start_at' => now()->toDateString(),
            'end_at' => null,
            'department_id' => $department->id,
            'is_closed' => false,
            'is_organization' => false,
        ]);
    }

    $this->actingAs($user)
        ->get(route('user.programs.index', ['department' => $department->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs.data', 12)
            ->where('programs.current_page', 1));

    $this->actingAs($user)
        ->get(route('user.programs.index', [
            'department' => $department->slug,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs.data', 1)
            ->where('programs.current_page', 2)
            ->where('programs.data.0.name', 'Program 13'));
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
