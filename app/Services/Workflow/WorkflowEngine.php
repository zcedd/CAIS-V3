<?php

namespace App\Services\Workflow;

use App\Actions\User\RecalculateAssistanceSla;
use App\Enums\PermissionName;
use App\Enums\RequestStatusCode;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowTaskHistoryAction;
use App\Enums\WorkflowTaskPriority;
use App\Enums\WorkflowTaskStatus;
use App\Enums\WorkflowTransitionAction;
use App\Models\Assistance;
use App\Models\AssistanceRequestSubStatus;
use App\Models\AssistanceWorkflow;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use App\Models\WorkflowTask;
use App\Notifications\WorkflowStepChangedNotification;
use App\Notifications\WorkflowTaskReturnedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowEngine
{
    public function __construct(
        private WorkflowConditionEvaluator $conditions,
        private WorkflowTaskAssigner $assigner,
        private RecalculateAssistanceSla $recalculateAssistanceSla,
    ) {}

    public function start(Assistance $assistance, ?User $actor = null, bool $requireActive = true): AssistanceWorkflow
    {
        return DB::transaction(function () use ($assistance, $actor, $requireActive): AssistanceWorkflow {
            $assistance = Assistance::query()->whereKey($assistance->id)->lockForUpdate()->firstOrFail();
            $existing = AssistanceWorkflow::query()->where('assistance_id', $assistance->id)->first();

            if ($existing instanceof AssistanceWorkflow) {
                return $existing;
            }

            $workflow = $assistance->resolvedWorkflow();

            if ($requireActive && $workflow->status !== WorkflowStatus::Active) {
                throw ValidationException::withMessages([
                    'workflow' => 'This program workflow is not active.',
                ]);
            }

            $assistance->forceFill(['workflow_id' => $workflow->id])->save();

            $entryStatusId = $assistance->currentRequestSubStatus?->request_status_id
                ?? $workflow->staff_entry_request_status_id;
            $currentStep = $workflow->stepForStatus((int) $entryStatusId)
                ?? $workflow->startStep();

            $instance = AssistanceWorkflow::query()->create([
                'assistance_id' => $assistance->id,
                'workflow_id' => $workflow->id,
                'workflow_version' => (int) $workflow->version,
                'current_step_id' => $currentStep?->id,
                'status' => WorkflowInstanceStatus::Active,
                'started_at' => now(),
            ]);

            if ($currentStep instanceof WorkflowStep && $currentStep->isTaskStep()) {
                $this->createTask($assistance->refresh(), $instance, $currentStep, $actor);
            }

            return $instance->refresh();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function availableTransitions(Assistance $assistance, User $user): array
    {
        $assistance->loadMissing([
            'workflowInstance.currentStep.transitions.toStep.requestStatus',
            'workflowInstance.currentStep.transitions.toRequestStatus',
            'currentRequestSubStatus.requestStatus',
        ]);

        $instance = $this->instance($assistance);
        $step = $instance?->currentStep ?? $assistance->currentWorkflowStep();

        if (! $step instanceof WorkflowStep) {
            return [];
        }

        $step->loadMissing(['transitions.toStep.requestStatus', 'transitions.toRequestStatus']);

        $task = $this->currentTask($assistance);

        return $step->transitions
            ->filter(fn (WorkflowStepTransition $transition): bool => $this->transitionIsAllowed(
                $assistance,
                $user,
                $transition,
                $task,
            ))
            ->map(fn (WorkflowStepTransition $transition): array => $this->serializeTransition($transition))
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     request_sub_status_id?: int,
     *     recorded_at?: string,
     *     remark?: string|null,
     *     assigned_to_id?: int|null
     * }  $payload
     */
    public function executeByTargetSubStatus(
        Assistance $assistance,
        RequestSubStatus $target,
        User $user,
        array $payload = [],
    ): Assistance {
        $target->loadMissing('requestStatus');
        $assistance->loadMissing('currentRequestSubStatus.requestStatus');
        $hasCurrentStatus = $assistance->currentRequestSubStatus !== null;

        $this->ensureStarted($assistance, $user);

        $assistance->refresh()->loadMissing([
            'workflowInstance.currentStep.transitions.toStep',
            'currentRequestSubStatus.requestStatus',
        ]);

        $currentStatusId = (int) ($assistance->currentRequestSubStatus?->request_status_id ?? 0);
        $targetStatusId = (int) $target->request_status_id;

        if (! $hasCurrentStatus) {
            return $this->landOnFirstStatus($assistance, $target, $user, $payload);
        }

        if ($currentStatusId === $targetStatusId) {
            $sameStatusMove = $this->transitionForSameStatusStep($assistance, $user, $targetStatusId);

            if ($sameStatusMove instanceof WorkflowStepTransition) {
                return $this->executeTransition(
                    $assistance,
                    $user,
                    $sameStatusMove,
                    $payload + ['request_sub_status_id' => $target->id],
                    $this->currentTask($assistance),
                );
            }

            $this->recordStatus($assistance, $target, $user, $payload);

            return $assistance->refresh();
        }

        $transition = $this->transitionForTarget($assistance, $user, $targetStatusId);

        return $this->executeTransition(
            $assistance,
            $user,
            $transition,
            $payload + ['request_sub_status_id' => $target->id],
            $this->currentTask($assistance),
        );
    }

    /**
     * Record Ready for Release on a verified request after executive approval.
     * Uses the same status history row as a workflow transition, and moves the
     * workflow when that request already has one.
     */
    public function recordGovernorRelease(Assistance $assistance, RequestSubStatus $target, User $actor): Assistance
    {
        $payload = [
            'remark' => 'Executive approved. Ready for release.',
            'recorded_at' => now()->toDateTimeString(),
        ];

        $assistance->loadMissing('workflowInstance');

        if ($assistance->workflowInstance === null) {
            $this->recordStatus($assistance, $target, $actor, $payload);

            return $assistance->refresh();
        }

        try {
            return $this->executeByTargetSubStatus($assistance, $target, $actor, $payload);
        } catch (ValidationException) {
            $this->recordStatus($assistance, $target, $actor, $payload);

            return $assistance->refresh();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function executeTransition(
        Assistance $assistance,
        User $user,
        WorkflowStepTransition $transition,
        array $payload = [],
        ?WorkflowTask $task = null,
    ): Assistance {
        return DB::transaction(function () use ($assistance, $user, $transition, $payload, $task): Assistance {
            $assistance = Assistance::query()->whereKey($assistance->id)->lockForUpdate()->firstOrFail();
            $this->ensureStarted($assistance, $user);
            $assistance->refresh()->loadMissing([
                'workflowInstance.currentStep.transitions.toStep.requestStatus',
                'currentRequestSubStatus.requestStatus',
                'program',
            ]);

            $transition->loadMissing(['toStep.defaultSubStatus.requestStatus', 'fromStep', 'step']);
            $task ??= $this->currentTask($assistance);

            if ($task instanceof WorkflowTask) {
                $task = WorkflowTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            }

            if (! $this->transitionIsAllowed($assistance, $user, $transition, $task)) {
                throw ValidationException::withMessages([
                    'request_sub_status_id' => 'That status is not a valid next step for this request.',
                ]);
            }

            $this->assertComment($transition, $payload['remark'] ?? $payload['remarks'] ?? null);

            $destination = $this->destinationStep($assistance, $transition);
            $targetSubStatus = $this->resolveTargetSubStatus($destination, $payload);

            if ($task instanceof WorkflowTask) {
                $this->completeTask($task, $user, $transition, $payload['remark'] ?? $payload['remarks'] ?? null);
            }

            $this->recordStatus($assistance, $targetSubStatus, $user, $payload);
            $this->moveInstance($assistance, $destination, $user, $transition);
            $this->openNextTask($assistance->refresh(), $destination, $user);

            ($this->recalculateAssistanceSla)($assistance->refresh(), $destination);

            $assignee = $assistance->refresh()->assignedTo;
            if ($assignee instanceof User && $assignee->id !== $user->id) {
                $assignee->notify(
                    (new WorkflowStepChangedNotification($assistance, $destination, $user))->afterCommit(),
                );
            }

            return $assistance->refresh();
        });
    }

    public function claimTask(WorkflowTask $task, User $user): WorkflowTask
    {
        return DB::transaction(function () use ($task, $user): WorkflowTask {
            $task = WorkflowTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->assertTaskOpen($task);
            $assistance = $this->assistanceFor($task);

            if ($task->assigned_to_id !== null && (int) $task->assigned_to_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'task' => 'This task is already assigned.',
                ]);
            }

            return $this->assigner->claim($task, $assistance, $user);
        });
    }

    public function assignTask(
        WorkflowTask $task,
        User $actor,
        ?User $assignee,
        ?string $remarks = null,
    ): WorkflowTask {
        return DB::transaction(function () use ($task, $actor, $assignee, $remarks): WorkflowTask {
            $task = WorkflowTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->assertTaskOpen($task);

            return $this->assigner->assign($task, $this->assistanceFor($task), $actor, $assignee, $remarks);
        });
    }

    public function reassignTask(
        WorkflowTask $task,
        User $actor,
        ?User $assignee,
        ?string $remarks = null,
    ): WorkflowTask {
        return $this->assignTask($task, $actor, $assignee, $remarks ?? 'Task reassigned');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function returnTask(
        WorkflowTask $task,
        User $user,
        WorkflowStepTransition $transition,
        array $payload = [],
    ): Assistance {
        $transition->loadMissing('toStep');

        if ($transition->action !== WorkflowTransitionAction::Return) {
            throw ValidationException::withMessages([
                'action' => 'That action does not return the task.',
            ]);
        }

        return $this->executeTransition($this->assistanceFor($task), $user, $transition, $payload, $task);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function rejectTask(
        WorkflowTask $task,
        User $user,
        WorkflowStepTransition $transition,
        array $payload = [],
    ): Assistance {
        $transition->loadMissing('toStep');

        if ($transition->action !== WorkflowTransitionAction::Reject) {
            throw ValidationException::withMessages([
                'action' => 'That action does not reject the request.',
            ]);
        }

        return $this->executeTransition($this->assistanceFor($task), $user, $transition, $payload, $task);
    }

    public function currentTask(Assistance $assistance): ?WorkflowTask
    {
        $assistance->loadMissing('workflowInstance.tasks.step');

        $task = $assistance->workflowInstance?->tasks
            ?->first(static fn (WorkflowTask $candidate): bool => $candidate->isOpen());

        return $task instanceof WorkflowTask ? $task : null;
    }

    public function serializeTransition(WorkflowStepTransition $transition): array
    {
        $transition->loadMissing(['toStep.requestStatus', 'toStep.defaultSubStatus']);

        return [
            'id' => $transition->id,
            'action' => $transition->action instanceof WorkflowTransitionAction
                ? $transition->action->value
                : (string) $transition->action,
            'label' => $transition->displayLabel(),
            'requires_comment' => (bool) $transition->requires_comment,
            'to_step_id' => $transition->to_step_id,
            'to_step_name' => $transition->toStep?->displayName(),
            'to_request_status_id' => $transition->toStep?->request_status_id
                ?? $transition->to_request_status_id,
            'to_request_sub_status_id' => $transition->toStep?->default_request_sub_status_id,
        ];
    }

    public function serializeTask(?WorkflowTask $task): ?array
    {
        if (! $task instanceof WorkflowTask) {
            return null;
        }

        $task->loadMissing(['assignedUser', 'step.requestStatus']);
        $assignee = $task->assignedUser;

        return [
            'id' => $task->id,
            'name' => $task->step?->displayName(),
            'step_code' => $task->step?->code,
            'status' => $task->status instanceof WorkflowTaskStatus
                ? $task->status->value
                : (string) $task->status,
            'status_label' => $task->status instanceof WorkflowTaskStatus
                ? $task->status->label()
                : (string) $task->status,
            'priority' => $task->priority instanceof WorkflowTaskPriority
                ? $task->priority->value
                : (string) $task->priority,
            'assigned_to_id' => $task->assigned_to_id,
            'assigned_to_name' => $assignee instanceof User
                ? trim($assignee->firstName.' '.$assignee->lastName)
                : null,
            'assigned_at' => $task->assigned_at?->toIso8601String(),
            'due_at' => $task->due_at?->toIso8601String(),
        ];
    }

    private function ensureStarted(Assistance $assistance, ?User $actor): AssistanceWorkflow
    {
        $instance = $this->instance($assistance);

        if ($instance instanceof AssistanceWorkflow) {
            return $instance;
        }

        return $this->start($assistance, $actor, false);
    }

    private function instance(Assistance $assistance): ?AssistanceWorkflow
    {
        $assistance->loadMissing('workflowInstance.currentStep');

        return $assistance->workflowInstance;
    }

    private function transitionForTarget(Assistance $assistance, User $user, int $targetStatusId): WorkflowStepTransition
    {
        $step = $this->instance($assistance)?->currentStep ?? $assistance->currentWorkflowStep();

        if (! $step instanceof WorkflowStep) {
            throw ValidationException::withMessages([
                'request_sub_status_id' => 'This program does not have a workflow.',
            ]);
        }

        $step->loadMissing(['transitions.toStep.requestStatus', 'transitions.toRequestStatus']);
        $task = $this->currentTask($assistance);

        $matches = $step->transitions->filter(function (WorkflowStepTransition $transition) use ($targetStatusId): bool {
            $destinationStatusId = (int) ($transition->toStep?->request_status_id ?? $transition->to_request_status_id);

            return $destinationStatusId === $targetStatusId;
        });

        $allowed = $matches->first(
            fn (WorkflowStepTransition $transition): bool => $this->transitionIsAllowed($assistance, $user, $transition, $task),
        );

        if ($allowed instanceof WorkflowStepTransition) {
            return $allowed;
        }

        $skip = $step->transitions->first(function (WorkflowStepTransition $transition) use ($step): bool {
            $destination = $transition->toStep;
            $status = $destination?->requestStatus;

            return ($step->allows_skip_to_deliver || $destination?->allows_skip_to_deliver)
                && RequestStatusCode::Delivered->matches($status ?? $transition->toRequestStatus);
        });

        if (
            $skip instanceof WorkflowStepTransition
            && (int) ($skip->toStep?->request_status_id ?? $skip->to_request_status_id) === $targetStatusId
            && $this->transitionIsAllowed($assistance, $user, $skip, $task)
        ) {
            return $skip;
        }

        throw ValidationException::withMessages([
            'request_sub_status_id' => 'That status is not a valid next step for this request.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function landOnFirstStatus(
        Assistance $assistance,
        RequestSubStatus $target,
        User $user,
        array $payload,
    ): Assistance {
        return DB::transaction(function () use ($assistance, $target, $user, $payload): Assistance {
            $assistance = Assistance::query()->whereKey($assistance->id)->lockForUpdate()->firstOrFail();
            $this->ensureStarted($assistance, $user);
            $workflow = $assistance->resolvedWorkflow()->loadMissing('steps.requestStatus');
            $destination = $workflow->stepForStatus((int) $target->request_status_id);

            if (! $destination instanceof WorkflowStep) {
                throw ValidationException::withMessages([
                    'request_sub_status_id' => 'That status is not part of this program workflow.',
                ]);
            }

            $task = $this->currentTask($assistance);

            if ($task instanceof WorkflowTask) {
                $task = WorkflowTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
                $from = $task->status instanceof WorkflowTaskStatus
                    ? $task->status
                    : WorkflowTaskStatus::Pending;
                $task->forceFill([
                    'status' => WorkflowTaskStatus::Completed,
                    'completed_at' => now(),
                ])->save();
                $this->assigner->recordHistory(
                    $task,
                    WorkflowTaskHistoryAction::Completed,
                    $from,
                    WorkflowTaskStatus::Completed,
                    $user,
                    $payload['remark'] ?? $payload['remarks'] ?? null,
                );
            }

            $this->recordStatus($assistance, $target, $user, $payload);
            $this->moveInstance($assistance, $destination, $user);
            $this->openNextTask($assistance->refresh(), $destination, $user);
            ($this->recalculateAssistanceSla)($assistance->refresh(), $destination);

            return $assistance->refresh();
        });
    }

    private function transitionForSameStatusStep(
        Assistance $assistance,
        User $user,
        int $targetStatusId,
    ): ?WorkflowStepTransition {
        $current = $this->instance($assistance)?->currentStep ?? $assistance->currentWorkflowStep();

        if (! $current instanceof WorkflowStep) {
            return null;
        }

        $current->loadMissing(['transitions.toStep']);
        $task = $this->currentTask($assistance);

        return $current->transitions->first(function (WorkflowStepTransition $transition) use (
            $assistance,
            $user,
            $task,
            $current,
            $targetStatusId,
        ): bool {
            $destination = $this->destinationStep($assistance, $transition);

            if (! $destination instanceof WorkflowStep || (int) $destination->id === (int) $current->id) {
                return false;
            }

            if ((int) $destination->request_status_id !== $targetStatusId) {
                return false;
            }

            return $this->transitionIsAllowed($assistance, $user, $transition, $task);
        });
    }

    private function transitionIsAllowed(
        Assistance $assistance,
        User $user,
        WorkflowStepTransition $transition,
        ?WorkflowTask $task,
    ): bool {
        $transition->loadMissing(['toStep.requestStatus', 'step']);
        $destination = $this->destinationStep($assistance, $transition);

        if (! $destination instanceof WorkflowStep) {
            return false;
        }

        if ($destination->permission !== null && ! $user->can($destination->permission)) {
            return false;
        }

        if (! $this->userMayAct($assistance, $user, $task, $transition->step ?? $assistance->currentWorkflowStep())) {
            return false;
        }

        try {
            return $this->conditions->matches($assistance, $transition->conditions);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function userMayAct(
        Assistance $assistance,
        User $user,
        ?WorkflowTask $task,
        ?WorkflowStep $step,
    ): bool {
        if ($user->isSuperAdmin() || $user->can(PermissionName::DepartmentSupervise->value)) {
            return true;
        }

        if ($user->can(PermissionName::ProgramApprove->value)) {
            return true;
        }

        if ($task instanceof WorkflowTask && $task->assigned_to_id !== null) {
            return (int) $task->assigned_to_id === (int) $user->id;
        }

        if ($step?->assigned_to_id !== null) {
            return (int) $assistance->assigned_to_id === (int) $user->id
                || (int) $step->assigned_to_id === (int) $user->id;
        }

        return true;
    }

    private function destinationStep(Assistance $assistance, WorkflowStepTransition $transition): ?WorkflowStep
    {
        if ($transition->toStep instanceof WorkflowStep) {
            return $transition->toStep;
        }

        if ($transition->to_step_id !== null) {
            return WorkflowStep::query()->find($transition->to_step_id);
        }

        $workflow = $assistance->resolvedWorkflow();

        return $workflow->stepForStatus((int) $transition->to_request_status_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTargetSubStatus(WorkflowStep $destination, array $payload): RequestSubStatus
    {
        if (isset($payload['request_sub_status_id'])) {
            $subStatus = RequestSubStatus::query()
                ->with('requestStatus')
                ->find((int) $payload['request_sub_status_id']);

            if ($subStatus instanceof RequestSubStatus
                && (int) $subStatus->request_status_id === (int) $destination->request_status_id) {
                return $subStatus;
            }
        }

        $destination->loadMissing('defaultSubStatus.requestStatus');

        if ($destination->defaultSubStatus instanceof RequestSubStatus) {
            return $destination->defaultSubStatus;
        }

        throw ValidationException::withMessages([
            'request_sub_status_id' => 'The destination workflow step has no status mapping.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordStatus(
        Assistance $assistance,
        RequestSubStatus $target,
        User $user,
        array $payload,
    ): void {
        AssistanceRequestSubStatus::query()->create([
            'assistance_id' => $assistance->id,
            'request_sub_status_id' => $target->id,
            'remark' => $payload['remark'] ?? $payload['remarks'] ?? null,
            'recorded_at' => isset($payload['recorded_at'])
                ? Carbon::parse((string) $payload['recorded_at'])
                : now(),
            'recorded_by' => $user->id,
        ]);
    }

    private function completeTask(
        WorkflowTask $task,
        User $user,
        WorkflowStepTransition $transition,
        ?string $remarks,
    ): void {
        $this->assertTaskOpen($task);

        $from = $task->status instanceof WorkflowTaskStatus
            ? $task->status
            : WorkflowTaskStatus::Pending;
        $action = $transition->action instanceof WorkflowTransitionAction
            ? $transition->action
            : WorkflowTransitionAction::Advance;
        $to = $action->resultingTaskStatus();
        $historyAction = match ($to) {
            WorkflowTaskStatus::Returned => WorkflowTaskHistoryAction::Returned,
            WorkflowTaskStatus::Rejected => WorkflowTaskHistoryAction::Rejected,
            default => WorkflowTaskHistoryAction::Completed,
        };

        $task->forceFill([
            'status' => $to,
            'completed_at' => now(),
            'remarks' => $remarks ?? $task->remarks,
        ])->save();

        $this->assigner->recordHistory($task, $historyAction, $from, $to, $user, $remarks, [
            'transition_id' => $transition->id,
            'action' => $action->value,
        ]);

        if ($to === WorkflowTaskStatus::Returned && $task->assigned_to_id !== null && (int) $task->assigned_to_id !== (int) $user->id) {
            $previous = $task->assignedUser ?? User::query()->find($task->assigned_to_id);
            if ($previous instanceof User) {
                $previous->notify((new WorkflowTaskReturnedNotification($task->refresh(), $user))->afterCommit());
            }
        }
    }

    private function moveInstance(
        Assistance $assistance,
        WorkflowStep $destination,
        User $user,
        ?WorkflowStepTransition $transition = null,
    ): void {
        $instance = $this->ensureStarted($assistance, $user);
        $isEnd = $destination->is_end
            || RequestStatusCode::matchesAny($destination->requestStatus, [
                RequestStatusCode::Closed,
            ]);
        $status = $isEnd
            ? WorkflowInstanceStatus::Completed
            : WorkflowInstanceStatus::Active;

        $instance->forceFill([
            'current_step_id' => $destination->id,
            'status' => $status,
            'completed_at' => $status === WorkflowInstanceStatus::Active ? null : now(),
        ])->save();

        if ($status !== WorkflowInstanceStatus::Active) {
            WorkflowTask::query()
                ->where('assistance_workflow_id', $instance->id)
                ->open()
                ->get()
                ->each(function (WorkflowTask $openTask) use ($user): void {
                    $from = $openTask->status instanceof WorkflowTaskStatus
                        ? $openTask->status
                        : WorkflowTaskStatus::Pending;
                    $openTask->forceFill([
                        'status' => WorkflowTaskStatus::Cancelled,
                        'completed_at' => now(),
                    ])->save();
                    $this->assigner->recordHistory(
                        $openTask,
                        WorkflowTaskHistoryAction::Cancelled,
                        $from,
                        WorkflowTaskStatus::Cancelled,
                        $user,
                        'Workflow ended',
                    );
                });
        }
    }

    private function openNextTask(Assistance $assistance, WorkflowStep $destination, User $actor): void
    {
        $instance = $this->instance($assistance);

        if (! $instance instanceof AssistanceWorkflow || ! $instance->isOpen()) {
            $assistance->forceFill([
                'assigned_to_id' => null,
                'assigned_at' => null,
            ])->save();

            return;
        }

        if (! $destination->isTaskStep()) {
            $assistance->forceFill([
                'assigned_to_id' => null,
                'assigned_at' => null,
            ])->save();

            return;
        }

        $this->createTask($assistance, $instance, $destination, $actor);
    }

    private function createTask(
        Assistance $assistance,
        AssistanceWorkflow $instance,
        WorkflowStep $step,
        ?User $actor,
    ): WorkflowTask {
        $dueAt = $step->sla_hours !== null && $step->sla_hours > 0
            ? now()->addHours((int) $step->sla_hours)
            : null;

        $task = WorkflowTask::query()->create([
            'assistance_workflow_id' => $instance->id,
            'workflow_step_id' => $step->id,
            'status' => WorkflowTaskStatus::Pending,
            'priority' => WorkflowTaskPriority::Normal,
            'due_at' => $dueAt,
        ]);

        $this->assigner->recordHistory(
            $task,
            WorkflowTaskHistoryAction::Created,
            null,
            WorkflowTaskStatus::Pending,
            $actor,
        );

        return $this->assigner->applyStepRule($task->refresh(), $step, $assistance, $actor);
    }

    private function assertTaskOpen(WorkflowTask $task): void
    {
        if ($task->isOpen()) {
            return;
        }

        throw ValidationException::withMessages([
            'task' => 'This task has already been completed.',
        ]);
    }

    private function assertComment(WorkflowStepTransition $transition, mixed $remark): void
    {
        if (! $transition->requires_comment) {
            return;
        }

        if (is_string($remark) && trim($remark) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'remark' => 'A comment is required for this action.',
        ]);
    }

    private function assistanceFor(WorkflowTask $task): Assistance
    {
        $task->loadMissing('workflowInstance.assistance');

        $assistance = $task->workflowInstance?->assistance;

        if (! $assistance instanceof Assistance) {
            throw ValidationException::withMessages([
                'task' => 'This task is not linked to an assistance request.',
            ]);
        }

        return $assistance;
    }
}
