<?php

use App\Models\Assistance;
use App\Models\AssistanceAssignment;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use App\Models\Workflow;
use App\Notifications\AssistanceAssignedNotification;
use App\Notifications\StaleAssistanceReminderNotification;
use App\Services\User\AssistanceService;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use App\Support\RequestStatusCode;
use App\Support\RequestSubStatusCode;
use App\Support\WorkflowTemplate;
use Illuminate\Support\Facades\Notification;

function createQueueAssistanceContext(): array
{
    $department = Department::create(['name' => 'Queue Department']);
    $encoder = User::factory()->create(['department_id' => $department->id]);
    $assignee = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'Aid Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $beneficiary = Beneficiary::create([
        'cais_number' => 'CAIS-Q-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $mode = ModeOfRequest::create(['name' => 'Walk In']);

    return compact('department', 'encoder', 'assignee', 'program', 'beneficiary', 'mode');
}

function assignWorkflowStageOwner(Program $program, RequestStatusCode $stage, User $owner): void
{
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor(
        $program->department ?? Department::query()->findOrFail($program->department_id),
    );

    $workflow->steps()
        ->where('request_status_id', catalogParentId($stage))
        ->update([
            'assigned_to_id' => $owner->id,
            'requires_assignee' => true,
        ]);
}

test('public submit is unassigned on the team queue and claim moves it to my queue', function () {
    ['department' => $department, 'encoder' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => null,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('user.queue.index', [
            'department' => $department->slug,
            'tab' => 'team',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('user/queue/index')
            ->where('tab', 'team')
            ->has('assistances.data', 1)
            ->where('assistances.data.0.id', $assistance->id));

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.claim', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertRedirect();

    expect($assistance->refresh()->assigned_to_id)->toBe($user->id);

    $this->actingAs($user)
        ->get(route('user.queue.index', [
            'department' => $department->slug,
            'tab' => 'mine',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'mine')
            ->has('assistances.data', 1)
            ->where('assistances.data.0.id', $assistance->id));
});

test('assigning an assistance writes history and notifies the new assignee', function () {
    Notification::fake();

    ['department' => $department, 'encoder' => $user, 'assignee' => $assignee, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->patch(route('user.programs.assistances.assign', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'assigned_to_id' => $assignee->id,
            'remark' => 'Please review',
        ])
        ->assertRedirect();

    expect($assistance->refresh()->assigned_to_id)->toBe($assignee->id)
        ->and(AssistanceAssignment::query()->where('assistance_id', $assistance->id)->count())->toBe(1);

    Notification::assertSentTo($assignee, AssistanceAssignedNotification::class);
    Notification::assertNotSentTo($user, AssistanceAssignedNotification::class);
});

test('standard workflow rejects skipping from submitted to delivered', function () {
    ['department' => $department, 'encoder' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program->item()->attach($item->id);
    seedProgramStock($program, $item, 10, $user);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
        'assigned_to_id' => $user->id,
    ]);

    $assistanceItem = AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'requested_quantity' => 1,
        'is_received' => false,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Delivered),
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [
                [
                    'assistance_item_id' => $assistanceItem->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertSessionHasErrors('request_sub_status_id');
});

test('walk-in workflow allows submitted to delivered', function () {
    ['department' => $department, 'encoder' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();

    $walkIn = app(EnsureDepartmentWorkflow::class)->create(
        $department,
        WorkflowTemplate::WalkIn,
        false,
        'Walk-in relief',
    );
    $program->update(['workflow_id' => $walkIn->id]);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program->item()->attach($item->id);
    seedProgramStock($program, $item, 10, $user);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
        'assigned_to_id' => $user->id,
    ]);

    $assistanceItem = AssistanceItem::query()->create([
        'assistance_id' => $assistance->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'requested_quantity' => 1,
        'is_received' => false,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Delivered),
            'recorded_at' => now()->toDateTimeString(),
            'delivered_items' => [
                [
                    'assistance_item_id' => $assistanceItem->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($assistance->refresh()->current_request_sub_status_id)
        ->toBe(catalogReasonId(RequestSubStatusCode::Delivered));
});

test('cannot move to a stage that is not in the program workflow', function () {
    ['department' => $department, 'encoder' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();

    $walkIn = app(EnsureDepartmentWorkflow::class)->create(
        $department,
        WorkflowTemplate::WalkIn,
        false,
        'Walk-in override',
    );
    $program->update(['workflow_id' => $walkIn->id]);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
        'assigned_to_id' => $user->id,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('user.programs.show', [
            'department' => $department->slug,
            'program' => $program->id,
        ]))
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Verified),
            'recorded_at' => now()->toDateTimeString(),
        ])
        ->assertSessionHasErrors('request_sub_status_id');
});

test('retired statuses are hidden from the picker', function () {
    ['department' => $department, 'program' => $program] = createQueueAssistanceContext();

    $options = app(AssistanceService::class)->requestSubStatusesForSelect($program);

    expect(collect($options)->pluck('name')->all())
        ->not->toContain('In Progress')
        ->and(collect($options)->pluck('request_status')->unique()->all())
        ->not->toContain('Verification');
});

test('program override is used instead of the department default', function () {
    ['department' => $department, 'program' => $program] = createQueueAssistanceContext();

    $default = app(EnsureDepartmentWorkflow::class)->defaultFor($department);
    $walkIn = app(EnsureDepartmentWorkflow::class)->create(
        $department,
        WorkflowTemplate::WalkIn,
        false,
        'Walk-in override',
    );

    expect($program->resolvedWorkflow()->id)->toBe($default->id);

    $program->update(['workflow_id' => $walkIn->id]);

    expect($program->fresh()->resolvedWorkflow()->id)->toBe($walkIn->id)
        ->and($program->fresh()->resolvedWorkflow()->template)->toBe(WorkflowTemplate::WalkIn);
});

test('sla pauses on hold and stale reminders go to the assignee', function () {
    ['department' => $department, 'encoder' => $encoder, 'assignee' => $assignee, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $encoder->id,
        'assigned_to_id' => $assignee->id,
        'assigned_at' => now(),
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now(),
    ]);

    $this->actingAs($assignee)
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingInformation),
            'recorded_at' => now()->toDateTimeString(),
        ])
        ->assertSessionHasNoErrors();

    $assistance->refresh();

    expect($assistance->sla_paused_at)->not->toBeNull();

    $stale = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->subDays(8)->toDateString(),
        'user_id' => $encoder->id,
        'assigned_to_id' => $assignee->id,
        'sla_due_at' => now()->subHour(),
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $stale->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now()->subDays(8),
    ]);

    $this->artisan('assistances:notify-stale')
        ->expectsOutputToContain('Dispatched')
        ->assertSuccessful();

    expect($assignee->notifications()->where('type', StaleAssistanceReminderNotification::class)->count())->toBe(1)
        ->and($encoder->notifications()->where('type', StaleAssistanceReminderNotification::class)->count())->toBe(0);
});

test('department workflows page lists the default pipeline', function () {
    ['department' => $department, 'encoder' => $user] = createQueueAssistanceContext();

    $this->actingAs($user)
        ->get(route('user.workflows.index', $department->slug))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('user/workflows/index')
            ->has('workflows', 1)
            ->where('workflows.0.is_default', true)
            ->where('can_create', false));
});

test('admin can create a workflow from a template', function () {
    ['department' => $department, 'encoder' => $user] = createQueueAssistanceContext();
    assignAdminRole($user);

    $this->actingAs($user)
        ->get(route('user.workflows.index', $department->slug))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('can_create', true));

    $this->actingAs($user)
        ->from(route('user.workflows.index', $department->slug))
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

test('staff without the admin role cannot create a workflow', function () {
    ['department' => $department, 'encoder' => $user] = createQueueAssistanceContext();

    $this->actingAs($user)
        ->post(route('user.workflows.store', $department->slug), [
            'name' => 'Walk-in relief',
            'template' => WorkflowTemplate::WalkIn->value,
        ])
        ->assertForbidden();
});

test('staff encode on an owned submitted step assigns the owner instead of the encoder', function () {
    ['department' => $department, 'encoder' => $encoder, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();
    $maria = User::factory()->create(['department_id' => $department->id]);

    assignWorkflowStageOwner($program, RequestStatusCode::Submitted, $maria);

    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program->item()->attach($item->id);

    $this->actingAs($encoder)
        ->post(route('user.programs.assistances.store', [
            'department' => $department->slug,
            'program' => $program->id,
        ]), [
            'beneficiary_id' => $beneficiary->id,
            'mode_of_request_id' => $mode->id,
            'recorded_at' => now()->toDateTimeString(),
            'item_details' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ])
        ->assertRedirect();

    $assistance = Assistance::query()->first();

    expect($assistance)->not->toBeNull()
        ->and($assistance->user_id)->toBe($encoder->id)
        ->and($assistance->assigned_to_id)->toBe($maria->id);
});

test('juan cannot advance maria review request but maria and admin can', function () {
    ['department' => $department, 'encoder' => $juan, 'assignee' => $maria, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();
    $admin = User::factory()->create(['department_id' => $department->id]);
    assignAdminRole($admin);

    assignWorkflowStageOwner($program, RequestStatusCode::Review, $maria);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $juan->id,
        'assigned_to_id' => $maria->id,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::UnderReview),
        'recorded_at' => now(),
    ]);

    $payload = [
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Approved),
        'recorded_at' => now()->toDateTimeString(),
    ];

    $this->actingAs($juan)
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), $payload)
        ->assertForbidden();

    $this->actingAs($maria)
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($assistance->refresh()->current_request_sub_status_id)
        ->toBe(catalogReasonId(RequestSubStatusCode::Approved));

    $adminAssistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $juan->id,
        'assigned_to_id' => $maria->id,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $adminAssistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::UnderReview),
        'recorded_at' => now(),
    ]);

    $this->actingAs($admin)
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $adminAssistance->id,
        ]), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($adminAssistance->refresh()->current_request_sub_status_id)
        ->toBe(catalogReasonId(RequestSubStatusCode::Approved));
});

test('moving to an owned approved step auto-assigns and notifies juan', function () {
    Notification::fake();

    ['department' => $department, 'encoder' => $encoder, 'assignee' => $maria, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();
    $juan = User::factory()->create(['department_id' => $department->id]);

    assignWorkflowStageOwner($program, RequestStatusCode::Review, $maria);
    assignWorkflowStageOwner($program, RequestStatusCode::Approved, $juan);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $encoder->id,
        'assigned_to_id' => $maria->id,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::UnderReview),
        'recorded_at' => now(),
    ]);

    $this->actingAs($maria)
        ->patch(route('user.programs.assistances.status.update', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::Approved),
            'recorded_at' => now()->toDateTimeString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($assistance->refresh()->assigned_to_id)->toBe($juan->id);

    Notification::assertSentTo($juan, AssistanceAssignedNotification::class);
    Notification::assertNotSentTo($maria, AssistanceAssignedNotification::class);
});

test('claim is forbidden on a step-owned request except for the owner or admin', function () {
    ['department' => $department, 'encoder' => $juan, 'assignee' => $maria, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = createQueueAssistanceContext();
    $admin = User::factory()->create(['department_id' => $department->id]);
    assignAdminRole($admin);

    assignWorkflowStageOwner($program, RequestStatusCode::Submitted, $maria);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => null,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now(),
    ]);

    $this->actingAs($juan)
        ->patch(route('user.programs.assistances.claim', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertForbidden();

    $this->actingAs($juan)
        ->patch(route('user.programs.assistances.assign', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'assigned_to_id' => $juan->id,
        ])
        ->assertForbidden();

    $this->actingAs($maria)
        ->patch(route('user.programs.assistances.claim', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertRedirect();

    expect($assistance->refresh()->assigned_to_id)->toBe($maria->id);

    $this->actingAs($admin)
        ->patch(route('user.programs.assistances.assign', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'assigned_to_id' => $admin->id,
            'remark' => 'Admin override',
        ])
        ->assertRedirect();

    expect($assistance->refresh()->assigned_to_id)->toBe($admin->id);
});
