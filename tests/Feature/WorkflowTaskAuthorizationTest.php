<?php

use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\User;
use App\Services\Workflow\WorkflowEngine;

function workflowAuthContext(): array
{
    $department = Department::create(['name' => 'Auth Department']);
    $other = Department::create(['name' => 'Other Department']);
    $assignee = grantResourceRoles(User::factory()->create(['department_id' => $department->id]));
    $outsider = grantResourceRoles(User::factory()->create(['department_id' => $other->id]));
    $unprivileged = withoutResourceRoles(User::factory()->create(['department_id' => $department->id]));
    $program = Program::create([
        'name' => 'Auth Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $assistance = Assistance::query()->create([
        'program_id' => $program->id,
        'beneficiary_id' => Beneficiary::create([
            'cais_number' => 'CAIS-AUTH',
            'name' => 'Ana',
            'beneficiable_type' => 'App\\Models\\Individual',
            'beneficiable_id' => 9,
        ])->id,
        'mode_of_request_id' => ModeOfRequest::create(['name' => 'Walk In'])->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $assignee->id,
    ]);

    AssistanceRequestSubStatus::query()->create([
        'assistance_id' => $assistance->id,
        'request_sub_status_id' => catalogReasonId(RequestSubStatusCode::AwaitingReview),
        'recorded_at' => now(),
    ]);

    $engine = app(WorkflowEngine::class);
    $engine->start($assistance, $assignee, false);
    $task = $engine->currentTask($assistance);

    return compact('department', 'assignee', 'outsider', 'unprivileged', 'program', 'assistance', 'task', 'engine');
}

test('staff without assistance claim cannot claim a workflow task', function () {
    ['department' => $department, 'unprivileged' => $user, 'task' => $task] = workflowAuthContext();

    $this->actingAs($user)
        ->post(route('user.workflow-tasks.claim', [
            'department' => $department->slug,
            'task' => $task->id,
        ]))
        ->assertForbidden();
});

test('a staff member from another department cannot claim a task', function () {
    ['department' => $department, 'outsider' => $user, 'task' => $task] = workflowAuthContext();

    $this->actingAs($user)
        ->post(route('user.workflow-tasks.claim', [
            'department' => $department->slug,
            'task' => $task->id,
        ]))
        ->assertForbidden();
});

test('staff without advance cannot complete a workflow task', function () {
    ['department' => $department, 'unprivileged' => $user, 'task' => $task, 'assistance' => $assistance] = workflowAuthContext();

    $transition = $assistance->currentWorkflowStep()?->transitions->first();

    $this->actingAs($user)
        ->post(route('user.workflow-tasks.complete', [
            'department' => $department->slug,
            'task' => $task->id,
        ]), [
            'transition_id' => $transition->id,
        ])
        ->assertForbidden();
});

test('staff without assign cannot reassign a workflow task', function () {
    ['department' => $department, 'unprivileged' => $user, 'assignee' => $assignee, 'task' => $task] = workflowAuthContext();

    grantResourceRoles($user, RoleName::Beneficiary->value);

    $this->actingAs($user)
        ->post(route('user.workflow-tasks.reassign', [
            'department' => $department->slug,
            'task' => $task->id,
        ]), [
            'assigned_to_id' => $assignee->id,
        ])
        ->assertForbidden();
});

test('authorized staff can claim a pooled task', function () {
    ['department' => $department, 'assignee' => $user, 'task' => $task] = workflowAuthContext();

    $this->actingAs($user)
        ->post(route('user.workflow-tasks.claim', [
            'department' => $department->slug,
            'task' => $task->id,
        ]))
        ->assertRedirect();

    expect($task->refresh()->assigned_to_id)->toBe($user->id);
});
