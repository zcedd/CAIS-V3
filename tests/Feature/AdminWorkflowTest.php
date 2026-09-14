<?php

use App\Enums\RoleName;
use App\Enums\WorkflowTemplate;
use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;
use Inertia\Testing\AssertableInertia as Assert;

test('staff cannot view the admin workflows page', function () {
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create([
        'department_id' => $department->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.workflows.index'))
        ->assertForbidden();
});

test('super admin can open the admin workflows page without a department', function () {
    $admin = assignSuperAdminRole(User::factory()->create());
    Department::create(['name' => 'Social Welfare']);

    $this->actingAs($admin)
        ->get(route('admin.workflows.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/workflows/index')
            ->has('departments', 1)
            ->where('department', null)
            ->where('workflows', [])
            ->where('can_create', false));
});

test('super admin can view workflows for a selected department', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $admin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->get(route('admin.workflows.index', [
            'department' => $department->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/workflows/index')
            ->where('department.slug', $department->slug)
            ->has('workflows', 1)
            ->where('workflows.0.is_default', true)
            ->where('can_create', true));
});

test('super admin can create a workflow for a selected department from the admin page', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $admin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->from(route('admin.workflows.index', [
            'department' => $department->slug,
        ]))
        ->post(route('user.workflows.store', $department->slug), [
            'name' => 'Walk-in relief',
            'template' => WorkflowTemplate::WalkIn->value,
            'is_default' => false,
        ])
        ->assertRedirect(route('admin.workflows.index', [
            'department' => $department->slug,
        ]))
        ->assertSessionHas('success');

    expect(Workflow::query()
        ->where('department_id', $department->id)
        ->where('name', 'Walk-in relief')
        ->where('template', WorkflowTemplate::WalkIn)
        ->exists())->toBeTrue();
});

test('staff cannot create a workflow through the department route from admin', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::Assistance->value,
    );

    $this->actingAs($user)
        ->post(route('user.workflows.store', $department->slug), [
            'name' => 'Walk-in relief',
            'template' => WorkflowTemplate::WalkIn->value,
        ])
        ->assertForbidden();
});
