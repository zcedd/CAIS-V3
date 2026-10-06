<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

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
            ->where('department_id', null)
            ->where('search', '')
            ->where('status', [])
            ->where('workflows.data', [])
            ->where('can.create', true)
            ->where('can.delete', true));
});

test('super admin can create a draft workflow from the admin page', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $admin = assignSuperAdminRole(User::factory()->create());

    $this->actingAs($admin)
        ->from(route('admin.workflows.create'))
        ->post(route('admin.workflows.store'), [
            'department_id' => $department->id,
            'name' => 'Medical Assistance Workflow',
            'code' => 'MEDICAL_ASSISTANCE',
            'description' => 'Workflow for processing medical assistance requests.',
            'version' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $workflow = Workflow::query()
        ->where('department_id', $department->id)
        ->where('code', 'MEDICAL_ASSISTANCE')
        ->first();

    expect($workflow)->not->toBeNull()
        ->and($workflow->name)->toBe('Medical Assistance Workflow')
        ->and($workflow->status)->toBe(WorkflowStatus::Draft)
        ->and($workflow->version)->toBe(1)
        ->and($workflow->description)->toBe('Workflow for processing medical assistance requests.');
});

test('super admin can filter workflows by name, department, and status', function () {
    $welfare = Department::create(['name' => 'Social Welfare']);
    $health = Department::create(['name' => 'Health']);
    $admin = assignSuperAdminRole(User::factory()->create());

    Workflow::factory()->draft()->create([
        'department_id' => $welfare->id,
        'name' => 'Medical Assistance',
        'code' => 'MEDICAL',
    ]);
    Workflow::factory()->create([
        'department_id' => $health->id,
        'name' => 'Food Relief',
        'code' => 'FOOD',
        'status' => WorkflowStatus::Active,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.workflows.index', [
            'search' => 'Medical',
            'department_id' => $welfare->id,
            'status' => [WorkflowStatus::Draft->value],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('workflows.data', 1)
            ->where('workflows.data.0.name', 'Medical Assistance')
            ->where('search', 'Medical')
            ->where('department_id', $welfare->id)
            ->where('status', [WorkflowStatus::Draft->value]));
});

test('staff cannot create a workflow through the admin store route', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $user = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::Workflow->value,
    );

    $this->actingAs($user)
        ->post(route('admin.workflows.store'), [
            'department_id' => $department->id,
            'name' => 'Walk-in relief',
            'code' => 'WALK_IN',
        ])
        ->assertForbidden();
});

test('super admin can view an existing workflow on the admin show page', function () {
    $department = Department::create(['name' => 'Social Welfare']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    $this->actingAs($admin)
        ->get(route('admin.workflows.show', $workflow))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/workflows/show')
            ->where('workflow.id', $workflow->id)
            ->where('can.publish', true)
            ->where('can.assign', true));
});

test('the super admin role is seeded with workflow management permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    $role = Role::query()
        ->where('name', RoleName::SuperAdmin->value)
        ->where('guard_name', 'web')
        ->first();

    expect($role)->not->toBeNull()
        ->and($role->permissions->pluck('name')->all())
        ->toContain(PermissionName::WorkflowPublish->value)
        ->toContain(PermissionName::WorkflowAssign->value)
        ->toContain(PermissionName::WorkflowTaskOverride->value);
});
