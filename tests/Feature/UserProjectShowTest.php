<?php

use App\Models\Assistance;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view a user project show page', function () {
    $department = Department::create(['name' => 'Department A']);
    $project = Project::create([
        'name' => 'Alpha Project',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $response = $this->get(route('user.projects.show', [
        'department' => $department->slug,
        'project' => $project->id,
    ]));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view a project in their department', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $project = Project::create([
        'name' => 'Alpha Project',
        'descriptions' => 'Full description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $response = $this->actingAs($user)->get(route('user.projects.show', [
        'department' => $department->slug,
        'project' => $project->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('user/projects/show')
        ->where('project.id', $project->id)
        ->where('project.name', 'Alpha Project')
        ->where('project.descriptions', 'Full description')
        ->where('department.id', $department->id)
        ->where('department.slug', $department->slug)
        ->has('assistances', 0));
});

test('project show page includes assistances for the project', function () {
    $department = Department::create(['name' => 'Department A']);

    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $project = Project::create([
        'name' => 'Alpha Project',
        'descriptions' => 'Full description',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    Assistance::create([
        'project_id' => $project->id,
        'beneficiary_id' => null,
        'organization_id' => null,
        'mode_of_request_id' => null,
        'date_requested' => now()->toDateString(),
        'date_verified' => null,
        'date_denied' => null,
        'date_delivered' => null,
        'user_id' => $user->id,
        'remark' => 'Follow up next week',
    ]);

    $this->actingAs($user)
        ->get(route('user.projects.show', [
            'department' => $department->slug,
            'project' => $project->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assistances', 1)
            ->where('assistances.0.party', '—')
            ->where('assistances.0.status', 'Pending')
            ->where('assistances.0.remark', 'Follow up next week'));
});

test('authenticated users cannot view a project show page for another department', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $project = Project::create([
        'name' => 'Beta Project',
        'descriptions' => 'For B',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $this->actingAs($user)
        ->get(route('user.projects.show', [
            'department' => $departmentB->slug,
            'project' => $project->id,
        ]))
        ->assertForbidden();
});

test('authenticated users cannot view a project from another department using their own department slug', function () {
    $departmentA = Department::create(['name' => 'Department A']);
    $departmentB = Department::create(['name' => 'Department B']);

    $user = User::factory()->create([
        'department_id' => $departmentA->id,
    ]);

    $projectInB = Project::create([
        'name' => 'Other dept project',
        'descriptions' => '',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $departmentB->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $this->actingAs($user)
        ->get(route('user.projects.show', [
            'department' => $departmentA->slug,
            'project' => $projectInB->id,
        ]))
        ->assertNotFound();
});
