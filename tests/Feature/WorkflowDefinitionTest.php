<?php

use App\Actions\Admin\CreateWorkflowVersion;
use App\Enums\RequestStatusCode;
use App\Enums\RoleName;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowTransitionAction;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use App\Services\Workflow\PublishWorkflowValidator;
use Illuminate\Database\Eloquent\Model;

test('a super admin can create a workflow version of an active definition', function () {
    $department = Department::create(['name' => 'Version Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    $this->actingAs($admin)
        ->post(route('admin.workflows.version', $workflow))
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

test('a super admin can save a draft workflow with stage transitions', function () {
    Model::preventLazyLoading(true);

    try {
        $department = Department::create(['name' => 'Draft Edit Department']);
        $admin = assignSuperAdminRole(User::factory()->create());
        $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);
        $draft = app(CreateWorkflowVersion::class)($workflow);
        $draft->load('steps.requestStatus');

        $denied = $draft->steps->first(
            static fn (WorkflowStep $step): bool => RequestStatusCode::Denied->matches($step->requestStatus),
        );
        $start = $draft->steps->first(static fn (WorkflowStep $step): bool => $step->is_start);

        expect($denied)->not->toBeNull()
            ->and($start)->not->toBeNull();

        $steps = $draft->steps->map(static function (WorkflowStep $step) use ($denied, $start): array {
            return [
                'id' => $step->id,
                'code' => $step->code,
                'name' => $step->name,
                'request_status_id' => $step->request_status_id,
                'sort_order' => $step->sort_order,
                'is_start' => $step->is_start,
                'is_end' => $step->is_end,
                'transition_status_ids' => $step->is($start) ? [$denied->request_status_id] : [],
            ];
        })->all();

        $this->actingAs($admin)
            ->put(route('admin.workflows.update', $draft), [
                'name' => 'Updated draft',
                'code' => $draft->code,
                'description' => $draft->description,
                'steps' => $steps,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $transition = WorkflowStepTransition::query()
            ->where('workflow_step_id', $start->id)
            ->where('to_request_status_id', $denied->request_status_id)
            ->first();

        expect($draft->refresh()->name)->toBe('Updated draft')
            ->and($transition)->not->toBeNull()
            ->and($transition?->action)->toBe(WorkflowTransitionAction::Reject);
    } finally {
        Model::preventLazyLoading(false);
    }
});

test('active workflows cannot be edited in place', function () {
    $department = Department::create(['name' => 'Immutable Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    $this->actingAs($admin)
        ->put(route('admin.workflows.update', $workflow), [
            'name' => 'Changed',
            'code' => $workflow->code,
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

test('department staff cannot open workflow management', function () {
    $department = Department::create(['name' => 'Template Department']);
    $user = grantResourceRoles(User::factory()->create(['department_id' => $department->id]));

    $this->actingAs($user)
        ->get("/{$department->slug}/workflows")
        ->assertNotFound();
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
        ->post(route('admin.workflows.publish', $draft))
        ->assertForbidden();

    expect($draft->refresh()->status)->toBe(WorkflowStatus::Draft);
});

test('deleting an unused workflow from the editor returns to the workflow list', function () {
    $department = Department::create(['name' => 'Delete Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = Workflow::factory()->draft()->create([
        'department_id' => $department->id,
        'name' => 'Unused draft',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.workflows.show', $workflow))
        ->delete(route('admin.workflows.destroy', $workflow))
        ->assertRedirect(route('admin.workflows.index'))
        ->assertSessionHas('success', 'Workflow deleted.');

    expect(Workflow::query()->find($workflow->id))->toBeNull();
});

test('deleting an unused workflow from the list stays on the list', function () {
    $department = Department::create(['name' => 'List Delete Department']);
    $admin = assignSuperAdminRole(User::factory()->create());
    $workflow = Workflow::factory()->draft()->create([
        'department_id' => $department->id,
        'name' => 'List draft',
    ]);
    $listUrl = route('admin.workflows.index', ['search' => 'List draft']);

    $this->actingAs($admin)
        ->from($listUrl)
        ->delete(route('admin.workflows.destroy', $workflow))
        ->assertRedirect($listUrl)
        ->assertSessionHas('success', 'Workflow deleted.');

    expect(Workflow::query()->find($workflow->id))->toBeNull();
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
        ->from(route('admin.workflows.show', $workflow))
        ->delete(route('admin.workflows.destroy', $workflow))
        ->assertRedirect(route('admin.workflows.show', $workflow))
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
