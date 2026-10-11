<?php

namespace App\Services\Dispersal;

use App\Enums\DispersalApprovalStrategy;
use App\Enums\DispersalApprovalTaskStatus;
use App\Enums\DispersalBatchStatus;
use App\Enums\DispersalFinalizationScope;
use App\Enums\RoleName;
use App\Models\DispersalApprovalPolicy;
use App\Models\DispersalApprovalStage;
use App\Models\DispersalApprovalTask;
use App\Models\DispersalBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DispersalStageFlow
{
    public function __construct(
        private DispersalBatchWorkflow $workflow,
        private DispersalNotifier $notifier,
        private DispersalAuthorizer $authorizer,
    ) {}

    public function assignTasks(DispersalBatch $batch, DispersalApprovalPolicy $policy): void
    {
        $batch->tasks()
            ->whereIn('status', [
                DispersalApprovalTaskStatus::Pending->value,
                DispersalApprovalTaskStatus::Waiting->value,
            ])
            ->update(['status' => DispersalApprovalTaskStatus::Cancelled->value]);

        $stages = $policy->stages()->orderBy('sequence')->get();

        if ($stages->isEmpty()) {
            throw ValidationException::withMessages([
                'approval' => 'The approval policy has no stages.',
            ]);
        }

        foreach ($stages as $index => $stage) {
            $status = $policy->strategy === DispersalApprovalStrategy::Parallel || $index === 0
                ? DispersalApprovalTaskStatus::Pending
                : DispersalApprovalTaskStatus::Waiting;

            DispersalApprovalTask::query()->create([
                'dispersal_batch_id' => $batch->id,
                'dispersal_approval_stage_id' => $stage->id,
                'revision' => $batch->revision,
                'status' => $status,
            ]);

            if ($status === DispersalApprovalTaskStatus::Pending) {
                $this->notifyStage($batch, $stage, 'assigned');
            }
        }
    }

    public function actionableTask(DispersalBatch $batch, User $user): DispersalApprovalTask
    {
        $tasks = $batch->tasks()
            ->with('stage')
            ->where('revision', $batch->revision)
            ->where('status', DispersalApprovalTaskStatus::Pending)
            ->orderBy('id')
            ->get();

        $task = $tasks->first(
            fn (DispersalApprovalTask $candidate): bool => $candidate->stage instanceof DispersalApprovalStage
                && $this->authorizer->isAssigned($user, $candidate->stage),
        );

        if (! $task instanceof DispersalApprovalTask) {
            throw new AuthorizationException('You do not have an actionable approval task for this revision.');
        }

        return $task;
    }

    public function completeTask(DispersalApprovalTask $task, User $actor): void
    {
        $task->forceFill([
            'status' => DispersalApprovalTaskStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ])->save();
    }

    public function advance(DispersalBatch $batch, User $actor): DispersalBatch
    {
        $batch->loadMissing('policy');
        $policy = $batch->policy;

        if (! $policy instanceof DispersalApprovalPolicy) {
            throw ValidationException::withMessages([
                'approval' => 'This dispersal batch has no approval policy.',
            ]);
        }

        $tasks = $batch->tasks()->with('stage')->where('revision', $batch->revision)->orderBy('id')->get();

        if ($policy->strategy === DispersalApprovalStrategy::Sequential) {
            $waiting = $tasks->first(
                fn (DispersalApprovalTask $task): bool => $task->status === DispersalApprovalTaskStatus::Waiting,
            );

            $stillPending = $tasks->contains(
                fn (DispersalApprovalTask $task): bool => $task->status === DispersalApprovalTaskStatus::Pending,
            );

            if ($waiting instanceof DispersalApprovalTask && ! $stillPending && $waiting->stage instanceof DispersalApprovalStage) {
                $waiting->forceFill(['status' => DispersalApprovalTaskStatus::Pending])->save();
                $this->notifyStage($batch, $waiting->stage, 'assigned');

                return $batch->refresh();
            }
        }

        $open = $tasks->contains(fn (DispersalApprovalTask $task): bool => in_array($task->status, [
            DispersalApprovalTaskStatus::Pending,
            DispersalApprovalTaskStatus::Waiting,
        ], true));

        if ($open) {
            return $batch->refresh();
        }

        return $this->markApproved($batch, $actor);
    }

    public function markApproved(DispersalBatch $batch, User $actor): DispersalBatch
    {
        if ($batch->status === DispersalBatchStatus::Approved) {
            return $batch;
        }

        $updated = $this->workflow->move($batch, DispersalBatchStatus::Approved, $actor, [
            'approved_at' => now(),
            'finalization_scope' => $batch->finalization_scope ?? DispersalFinalizationScope::Complete,
        ]);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('dispersal:%d:revision:%d:approved', $updated->id, $updated->revision),
                'Dispersal batch approved',
                sprintf('%s was approved.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function returnBatch(DispersalBatch $batch, User $actor, string $remarks): DispersalBatch
    {
        $this->cancelOpenTasks($batch);

        $updated = $this->workflow->move($batch, DispersalBatchStatus::Returned, $actor, [
            'locked_at' => null,
        ]);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('dispersal:%d:revision:%d:returned:%s', $updated->id, $updated->revision, md5($remarks)),
                'Dispersal batch returned',
                sprintf('%s was returned for correction.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function rejectBatch(DispersalBatch $batch, User $actor): DispersalBatch
    {
        $this->cancelOpenTasks($batch);

        $updated = $this->workflow->move($batch, DispersalBatchStatus::Rejected, $actor);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('dispersal:%d:revision:%d:rejected', $updated->id, $updated->revision),
                'Dispersal batch rejected',
                sprintf('%s was rejected.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function cancelOpenTasks(DispersalBatch $batch): void
    {
        $batch->tasks()
            ->whereIn('status', [
                DispersalApprovalTaskStatus::Pending->value,
                DispersalApprovalTaskStatus::Waiting->value,
            ])
            ->update(['status' => DispersalApprovalTaskStatus::Cancelled->value]);
    }

    private function notifyStage(DispersalBatch $batch, DispersalApprovalStage $stage, string $event): void
    {
        $this->notifier->send(
            $this->recipients($stage, $batch),
            $batch,
            sprintf('dispersal:%d:revision:%d:stage:%d:%s', $batch->id, $batch->revision, $stage->id, $event),
            'Dispersal approval assigned',
            sprintf('%s is waiting for %s.', $batch->batch_code, $stage->name),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(DispersalApprovalStage $stage, DispersalBatch $batch): Collection
    {
        if ($stage->user_id !== null) {
            $user = User::query()->find($stage->user_id);

            return $user instanceof User ? collect([$user]) : collect();
        }

        if ($stage->role_name === null || $stage->role_name === '') {
            return collect();
        }

        $query = User::role($stage->role_name);

        if (! in_array($stage->role_name, [RoleName::Governor->value, RoleName::SuperAdmin->value], true)) {
            $query->where('department_id', $batch->department_id);
        }

        return $query->get();
    }
}
