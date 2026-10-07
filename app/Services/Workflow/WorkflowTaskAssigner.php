<?php

namespace App\Services\Workflow;

use App\Actions\User\RecalculateAssistanceSla;
use App\Actions\User\RecordAssistanceAssignment;
use App\Enums\WorkflowAssignmentType;
use App\Enums\WorkflowTaskHistoryAction;
use App\Enums\WorkflowTaskStatus;
use App\Models\Assistance;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTask;
use App\Models\WorkflowTaskHistory;
use App\Notifications\WorkflowTaskAssignedNotification;
use App\Notifications\WorkflowTaskClaimedNotification;
use App\Notifications\WorkflowTaskReassignedNotification;

class WorkflowTaskAssigner
{
    public function __construct(
        private RecordAssistanceAssignment $recordAssistanceAssignment,
        private RecalculateAssistanceSla $recalculateAssistanceSla,
    ) {}

    public function applyStepRule(
        WorkflowTask $task,
        WorkflowStep $step,
        Assistance $assistance,
        ?User $actor,
        bool $notify = true,
    ): WorkflowTask {
        $assignee = $this->resolve($step, $assistance);

        if ($assignee instanceof User) {
            return $this->assign($task, $assistance, $actor, $assignee, 'Workflow auto-assign', $notify);
        }

        if ($task->assigned_to_id !== null) {
            return $this->assign($task, $assistance, $actor, null, 'Released to claim pool', $notify);
        }

        return $task->refresh();
    }

    public function assign(
        WorkflowTask $task,
        Assistance $assistance,
        ?User $actor,
        ?User $assignee,
        ?string $remarks = null,
        bool $notify = true,
    ): WorkflowTask {
        $fromStatus = $task->status instanceof WorkflowTaskStatus
            ? $task->status
            : WorkflowTaskStatus::tryFrom((string) $task->status) ?? WorkflowTaskStatus::Pending;
        $previousId = $task->assigned_to_id;
        $nextId = $assignee?->id;
        $action = $previousId !== null && $nextId !== null && $previousId !== $nextId
            ? WorkflowTaskHistoryAction::Reassigned
            : WorkflowTaskHistoryAction::Assigned;
        $toStatus = $assignee instanceof User
            ? ($fromStatus === WorkflowTaskStatus::Pending ? WorkflowTaskStatus::InProgress : $fromStatus)
            : WorkflowTaskStatus::Pending;

        $task->forceFill([
            'assigned_to_id' => $nextId,
            'assigned_by_id' => $actor?->id,
            'assigned_at' => $assignee instanceof User ? now() : null,
            'started_at' => $assignee instanceof User ? ($task->started_at ?? now()) : null,
            'status' => $toStatus,
            'remarks' => $remarks ?? $task->remarks,
        ])->save();

        if ($actor instanceof User) {
            ($this->recordAssistanceAssignment)($assistance, $actor, $assignee, $remarks, $notify);
        } else {
            $assistance->forceFill([
                'assigned_to_id' => $nextId,
                'assigned_at' => $assignee instanceof User ? now() : null,
            ])->save();
        }

        $this->recordHistory(
            $task,
            $action,
            $fromStatus,
            $toStatus,
            $actor,
            $remarks,
            [
                'from_user_id' => $previousId,
                'to_user_id' => $nextId,
            ],
        );

        if ($notify && $actor instanceof User) {
            if ($action === WorkflowTaskHistoryAction::Reassigned && $previousId !== null && $previousId !== $actor->id) {
                $previous = User::query()->find($previousId);
                if ($previous instanceof User) {
                    $previous->notify((new WorkflowTaskReassignedNotification($task->refresh(), $actor, $assignee))->afterCommit());
                }
            }

            if ($assignee instanceof User && $assignee->id !== $actor->id) {
                $assignee->notify((new WorkflowTaskAssignedNotification($task->refresh(), $actor))->afterCommit());
            }
        }

        ($this->recalculateAssistanceSla)($assistance->refresh(), $task->step);

        return $task->refresh();
    }

    public function claim(WorkflowTask $task, Assistance $assistance, User $user): WorkflowTask
    {
        $fromStatus = $task->status instanceof WorkflowTaskStatus
            ? $task->status
            : WorkflowTaskStatus::Pending;

        $task->forceFill([
            'assigned_to_id' => $user->id,
            'assigned_by_id' => $user->id,
            'assigned_at' => now(),
            'started_at' => $task->started_at ?? now(),
            'status' => WorkflowTaskStatus::Claimed,
        ])->save();

        ($this->recordAssistanceAssignment)($assistance, $user, $user, 'Claimed from queue');

        $this->recordHistory(
            $task,
            WorkflowTaskHistoryAction::Claimed,
            $fromStatus,
            WorkflowTaskStatus::Claimed,
            $user,
            'Claimed from queue',
        );

        $task = $task->refresh();
        $user->notify((new WorkflowTaskClaimedNotification($task, $user))->afterCommit());

        ($this->recalculateAssistanceSla)($assistance->refresh(), $task->step);

        return $task->refresh();
    }

    public function resolve(WorkflowStep $step, Assistance $assistance): ?User
    {
        $type = $step->assignment_type ?? WorkflowAssignmentType::None;

        if ($step->assigned_to_id !== null) {
            $user = $step->assignedTo ?? User::query()->find($step->assigned_to_id);

            return $user instanceof User ? $user : null;
        }

        if ($type === WorkflowAssignmentType::None) {
            return null;
        }

        if (! $step->automatic_assignment) {
            return null;
        }

        $departmentId = $step->assigned_department_id
            ?? $assistance->program?->department_id;

        $query = User::query()->orderBy('id');

        if ($type === WorkflowAssignmentType::DepartmentRole && $departmentId !== null) {
            $query->where('department_id', $departmentId);
        } elseif ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        $role = trim((string) $step->assigned_role);

        if ($role !== '' && ($type === WorkflowAssignmentType::Role || $type === WorkflowAssignmentType::DepartmentRole)) {
            $query->role($role);
        }

        $user = $query->first();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordHistory(
        WorkflowTask $task,
        WorkflowTaskHistoryAction $action,
        ?WorkflowTaskStatus $from,
        ?WorkflowTaskStatus $to,
        ?User $actor,
        ?string $remarks = null,
        ?array $metadata = null,
    ): void {
        WorkflowTaskHistory::query()->create([
            'workflow_task_id' => $task->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'performed_by' => $actor?->id,
            'remarks' => $remarks,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
