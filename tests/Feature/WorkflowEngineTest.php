<?php

use App\Enums\RequestStatusCode;
use App\Enums\RequestSubStatusCode;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowTaskStatus;
use App\Enums\WorkflowTemplate;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\AssistanceRequestSubStatus;
use App\Models\AssistanceWorkflow;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Models\WorkflowTask;
use App\Services\User\AssistanceService;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use App\Services\Workflow\PublishWorkflowValidator;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Validation\ValidationException;

function workflowEngineContext(): array
{
    $department = Department::create(['name' => 'Engine Department']);
    $user = grantResourceRoles(User::factory()->create(['department_id' => $department->id]));
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
        'cais_number' => 'CAIS-WE-001',
        'name' => 'Juan Dela Cruz',
        'beneficiable_type' => 'App\\Models\\Individual',
        'beneficiable_id' => 1,
    ]);
    $mode = ModeOfRequest::create(['name' => 'Walk In']);
    $unit = ItemUnitMeasurement::create(['name' => 'kg']);
    $item = Item::create([
        'name' => 'Rice',
        'department_id' => $department->id,
        'item_unit_measurement_id' => $unit->id,
    ]);
    $program->item()->attach($item->id);

    return compact('department', 'user', 'program', 'beneficiary', 'mode', 'item');
}

test('creating an assistance starts a workflow instance and the first task', function () {
    ['user' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode, 'item' => $item] = workflowEngineContext();

    $assistance = app(AssistanceService::class)->create($program, $user, [
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'recorded_at' => now()->toDateTimeString(),
        'item_details' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ],
    ]);

    $instance = AssistanceWorkflow::query()->where('assistance_id', $assistance->id)->first();

    expect($instance)->not->toBeNull()
        ->and($instance->status)->toBe(WorkflowInstanceStatus::Active)
        ->and($assistance->refresh()->workflow_id)->not->toBeNull()
        ->and(WorkflowTask::query()->where('assistance_workflow_id', $instance->id)->count())->toBe(1)
        ->and($assistance->currentRequestSubStatus?->request_status_id)->toBe(catalogParentId(RequestStatusCode::Submitted));
});

test('the engine rejects a skip from submitted to delivered on the standard workflow', function () {
    ['user' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = workflowEngineContext();

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

    $engine = app(WorkflowEngine::class);
    $engine->start($assistance, $user, false);

    expect(fn () => $engine->executeByTargetSubStatus(
        $assistance->refresh(),
        RequestSubStatus::query()->findOrFail(catalogReasonId(RequestSubStatusCode::Delivered)),
        $user,
    ))->toThrow(ValidationException::class);
});

test('a valid review transition creates the next task and records history', function () {
    ['user' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = workflowEngineContext();

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

    $engine = app(WorkflowEngine::class);
    $engine->start($assistance, $user, false);
    $firstTask = $engine->currentTask($assistance);

    $engine->executeByTargetSubStatus(
        $assistance->refresh(),
        RequestSubStatus::query()->findOrFail(catalogReasonId(RequestSubStatusCode::UnderReview)),
        $user,
    );

    $assistance->refresh();
    $nextTask = $engine->currentTask($assistance);

    expect($assistance->currentRequestSubStatus?->request_status_id)->toBe(catalogParentId(RequestStatusCode::Review))
        ->and($firstTask?->fresh()->status)->toBe(WorkflowTaskStatus::Completed)
        ->and($nextTask)->not->toBeNull()
        ->and($nextTask->id)->not->toBe($firstTask?->id)
        ->and($nextTask->histories()->count())->toBeGreaterThan(0);
});

test('completing an already completed task fails', function () {
    ['user' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = workflowEngineContext();

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

    $engine = app(WorkflowEngine::class);
    $engine->start($assistance, $user, false);
    $task = $engine->currentTask($assistance);
    $transition = $assistance->currentWorkflowStep()?->transitions->first();

    $engine->executeTransition($assistance, $user, $transition, [], $task);

    expect(fn () => $engine->executeTransition($assistance->refresh(), $user, $transition, [], $task->fresh()))
        ->toThrow(ValidationException::class);
});

test('walk-in workflow allows submitted to delivered', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode, 'item' => $item] = workflowEngineContext();

    $walkIn = app(EnsureDepartmentWorkflow::class)->create(
        $department,
        WorkflowTemplate::WalkIn,
        false,
        'Walk-in relief',
    );
    $program->update(['workflow_id' => $walkIn->id]);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
        'assigned_to_id' => $user->id,
        'workflow_id' => $walkIn->id,
    ]);

    AssistanceItem::query()->create([
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

    $engine = app(WorkflowEngine::class);
    $engine->start($assistance, $user, false);

    $engine->executeByTargetSubStatus(
        $assistance->refresh(),
        RequestSubStatus::query()->findOrFail(catalogReasonId(RequestSubStatusCode::Delivered)),
        $user,
    );

    expect($assistance->refresh()->currentRequestSubStatus?->request_status_id)
        ->toBe(catalogParentId(RequestStatusCode::Delivered));
});

test('start refuses a deactivated workflow', function () {
    ['department' => $department, 'user' => $user, 'program' => $program, 'beneficiary' => $beneficiary, 'mode' => $mode] = workflowEngineContext();

    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);
    $workflow->forceFill(['status' => WorkflowStatus::Inactive])->save();
    $program->update(['workflow_id' => $workflow->id]);

    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => $beneficiary->id,
        'mode_of_request_id' => $mode->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
        'workflow_id' => $workflow->id,
    ]);

    expect(fn () => app(WorkflowEngine::class)->start($assistance, $user))
        ->toThrow(ValidationException::class);
});

test('the standard template publishes with one start and a reachable end', function () {
    seedRolesAndPermissions();
    $department = Department::create(['name' => 'Publish Department']);
    $workflow = app(EnsureDepartmentWorkflow::class)->defaultFor($department);

    expect(app(PublishWorkflowValidator::class)->errors($workflow))->toBe([]);
});
