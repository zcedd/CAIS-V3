<?php

use App\Actions\Admin\CreateWorkflowVersion;
use App\Enums\RoleName;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowTemplate;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use App\Services\Workflow\PublishWorkflowValidator;

test('staff can create a workflow version of an active definition', function () {
    $department = Department::create(['name' => 'Version Department']);
    $user = grantResourceRoles(User::factory()->create(['department_id' => $department->id]));
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    $this->actingAs($user)
        ->post(route('user.workflows.version', [
            'department' => $department->slug,
            'workflow' => $workflow->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $draft = Workflow::query()
        ->where('department_id', $department->id)
        ->where('code', $workflow->code)
        ->where('status', WorkflowStatus::Draft)
        ->first();

    expect($draft)->not->toBeNull()
        ->and($draft->version)->toBe(2)
        ->and($draft->steps)->not->toBeEmpty()
        ->and($workflow->refresh()->status)->toBe(WorkflowStatus::Active);
});

test('active workflows cannot be edited in place', function () {
    $department = Department::create(['name' => 'Immutable Department']);
    $user = grantResourceRoles(User::factory()->create(['department_id' => $department->id]));
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    $this->actingAs($user)
        ->put(route('user.workflows.update', [
            'department' => $department->slug,
            'workflow' => $workflow->id,
        ]), [
            'name' => 'Changed',
            'steps' => [
                [
                    'request_status_id' => $workflow->steps->first()->request_status_id,
                    'sort_order' => 10,
                ],
            ],
        ])
        ->assertSessionHasErrors('status');
});

test('publishing a valid draft marks it published without activating it', function () {
    $department = Department::create(['name' => 'Publish Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);
    $draft = app(CreateWorkflowVersion::class)($workflow);

    expect(app(PublishWorkflowValidator::class)->errors($draft))->toBe([]);

    $this->actingAs($admin)
        ->post(route('admin.workflows.publish', $draft))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($draft->refresh()->status)->toBe(WorkflowStatus::Published)
        ->and($workflow->refresh()->status)->toBe(WorkflowStatus::Active);
});

test('activating a published workflow retires the previous active version', function () {
    $department = Department::create(['name' => 'Activate Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);
    $draft = app(CreateWorkflowVersion::class)($workflow);

    $this->actingAs($admin)
        ->post(route('admin.workflows.publish', $draft))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.workflows.activate', $draft))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($draft->refresh()->status)->toBe(WorkflowStatus::Active)
        ->and($workflow->refresh()->status)->toBe(WorkflowStatus::Inactive);
});

test('a walk-in template can be created from the department workflow page', function () {
    $department = Department::create(['name' => 'Template Department']);
    $user = grantResourceRoles(User::factory()->create(['department_id' => $department->id]));

    $this->actingAs($user)
        ->post(route('user.workflows.store', $department->slug), [
            'name' => 'Walk-in relief',
            'template' => WorkflowTemplate::WalkIn->value,
            'is_default' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Workflow::query()
        ->where('department_id', $department->id)
        ->where('name', 'Walk-in relief')
        ->where('template', WorkflowTemplate::WalkIn)
        ->exists())->toBeTrue();
});

test('admin can deactivate an active workflow', function () {
    $department = Department::create(['name' => 'Deactivate Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    $this->actingAs($admin)
        ->post(route('admin.workflows.deactivate', $workflow))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($workflow->refresh()->status)->toBe(WorkflowStatus::Inactive);
});

test('publishing an empty draft returns configuration errors', function () {
    $department = Department::create(['name' => 'Empty Draft']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = Workflow::factory()->draft()->create([
        'department_id' => $department->id,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.workflows.show', $workflow))
        ->post(route('admin.workflows.publish', $workflow))
        ->assertRedirect()
        ->assertSessionHasErrors('workflow');

    expect($workflow->refresh()->status)->toBe(WorkflowStatus::Draft);
});

test('staff with the workflow role cannot publish a workflow', function () {
    $department = Department::create(['name' => 'Staff Publish']);
    $user = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::Workflow->value,
    );
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);
    $draft = app(CreateWorkflowVersion::class)($workflow);

    $this->actingAs($user)
        ->post(route('user.workflows.publish', [
            'department' => $department->slug,
            'workflow' => $draft->id,
        ]))
        ->assertForbidden();

    expect($draft->refresh()->status)->toBe(WorkflowStatus::Draft);
});

test('deleting a referenced workflow deactivates it instead', function () {
    $department = Department::create(['name' => 'Referenced Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    Program::create([
        'name' => 'Medical Assistance',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
        'workflow_id' => $workflow->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.workflows.destroy', $workflow))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Workflow::query()->find($workflow->id))->not->toBeNull()
        ->and($workflow->refresh()->status)->toBe(WorkflowStatus::Inactive);
});

test('super admin can assign an active workflow to programs', function () {
    $department = Department::create(['name' => 'Assign Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);
    $program = Program::create([
        'name' => 'Food Assistance',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.workflows.programs', $workflow), [
            'program_ids' => [$program->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($program->refresh()->workflow_id)->toBe($workflow->id);
});

test('program staff cannot assign workflows from the admin interface', function () {
    $department = Department::create(['name' => 'Program Staff']);
    $user = grantResourceRoles(
        User::factory()->create(['department_id' => $department->id]),
        RoleName::Program->value,
    );
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    $this->actingAs($user)
        ->put(route('admin.workflows.programs', $workflow), [
            'program_ids' => [],
        ])
        ->assertForbidden();
});
