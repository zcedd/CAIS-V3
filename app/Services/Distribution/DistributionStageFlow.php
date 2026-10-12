<?php

namespace App\Services\Distribution;

use App\Enums\DistributionApprovalStrategy;
use App\Enums\DistributionApprovalTaskStatus;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionFinalizationScope;
use App\Enums\RoleName;
use App\Models\DistributionApprovalPolicy;
use App\Models\DistributionApprovalStage;
use App\Models\DistributionApprovalTask;
use App\Models\DistributionBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DistributionStageFlow
{
    public function __construct(
        private DistributionBatchWorkflow $workflow,
        private DistributionNotifier $notifier,
        private DistributionAuthorizer $authorizer,
    ) {}

    public function assignTasks(DistributionBatch $batch, DistributionApprovalPolicy $policy): void
    {
        $batch->tasks()
            ->whereIn('status', [
                DistributionApprovalTaskStatus::Pending->value,
                DistributionApprovalTaskStatus::Waiting->value,
            ])
            ->update(['status' => DistributionApprovalTaskStatus::Cancelled->value]);

        $stages = $policy->stages()->orderBy('sequence')->get();

        if ($stages->isEmpty()) {
            throw ValidationException::withMessages([
                'approval' => 'The approval policy has no stages.',
            ]);
        }

        foreach ($stages as $index => $stage) {
            $status = $policy->strategy === DistributionApprovalStrategy::Parallel || $index === 0
                ? DistributionApprovalTaskStatus::Pending
                : DistributionApprovalTaskStatus::Waiting;

            DistributionApprovalTask::query()->create([
                'distribution_batch_id' => $batch->id,
                'distribution_approval_stage_id' => $stage->id,
                'revision' => $batch->revision,
                'status' => $status,
            ]);

            if ($status === DistributionApprovalTaskStatus::Pending) {
                $this->notifyStage($batch, $stage, 'assigned');
            }
        }
    }

    public function actionableTask(DistributionBatch $batch, User $user): DistributionApprovalTask
    {
        $tasks = $batch->tasks()
            ->with('stage')
            ->where('revision', $batch->revision)
            ->where('status', DistributionApprovalTaskStatus::Pending)
            ->orderBy('id')
            ->get();

        $task = $tasks->first(
            fn (DistributionApprovalTask $candidate): bool => $candidate->stage instanceof DistributionApprovalStage
                && $this->authorizer->isAssigned($user, $candidate->stage),
        );

        if (! $task instanceof DistributionApprovalTask) {
            throw new AuthorizationException('You do not have an actionable approval task for this revision.');
        }

        return $task;
    }

    public function completeTask(DistributionApprovalTask $task, User $actor): void
    {
        $task->forceFill([
            'status' => DistributionApprovalTaskStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ])->save();
    }

    public function advance(DistributionBatch $batch, User $actor): DistributionBatch
    {
        $batch->loadMissing('policy');
        $policy = $batch->policy;

        if (! $policy instanceof DistributionApprovalPolicy) {
            throw ValidationException::withMessages([
                'approval' => 'This distribution batch has no approval policy.',
            ]);
        }

        $tasks = $batch->tasks()->with('stage')->where('revision', $batch->revision)->orderBy('id')->get();

        if ($policy->strategy === DistributionApprovalStrategy::Sequential) {
            $waiting = $tasks->first(
                fn (DistributionApprovalTask $task): bool => $task->status === DistributionApprovalTaskStatus::Waiting,
            );

            $stillPending = $tasks->contains(
                fn (DistributionApprovalTask $task): bool => $task->status === DistributionApprovalTaskStatus::Pending,
            );

            if ($waiting instanceof DistributionApprovalTask && ! $stillPending && $waiting->stage instanceof DistributionApprovalStage) {
                $waiting->forceFill(['status' => DistributionApprovalTaskStatus::Pending])->save();
                $this->notifyStage($batch, $waiting->stage, 'assigned');

                return $batch->refresh();
            }
        }

        $open = $tasks->contains(fn (DistributionApprovalTask $task): bool => in_array($task->status, [
            DistributionApprovalTaskStatus::Pending,
            DistributionApprovalTaskStatus::Waiting,
        ], true));

        if ($open) {
            return $batch->refresh();
        }

        return $this->markApproved($batch, $actor);
    }

    public function markApproved(DistributionBatch $batch, User $actor): DistributionBatch
    {
        if ($batch->status === DistributionBatchStatus::Approved) {
            return $batch;
        }

        $updated = $this->workflow->move($batch, DistributionBatchStatus::Approved, $actor, [
            'approved_at' => now(),
            'finalization_scope' => $batch->finalization_scope ?? DistributionFinalizationScope::Complete,
        ]);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('distribution:%d:revision:%d:approved', $updated->id, $updated->revision),
                'Distribution batch approved',
                sprintf('%s was approved.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function returnBatch(DistributionBatch $batch, User $actor, string $remarks): DistributionBatch
    {
        $this->cancelOpenTasks($batch);

        $updated = $this->workflow->move($batch, DistributionBatchStatus::Returned, $actor, [
            'locked_at' => null,
        ]);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('distribution:%d:revision:%d:returned:%s', $updated->id, $updated->revision, md5($remarks)),
                'Distribution batch returned',
                sprintf('%s was returned for correction.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function rejectBatch(DistributionBatch $batch, User $actor): DistributionBatch
    {
        $this->cancelOpenTasks($batch);

        $updated = $this->workflow->move($batch, DistributionBatchStatus::Rejected, $actor);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('distribution:%d:revision:%d:rejected', $updated->id, $updated->revision),
                'Distribution batch rejected',
                sprintf('%s was rejected.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function cancelOpenTasks(DistributionBatch $batch): void
    {
        $batch->tasks()
            ->whereIn('status', [
                DistributionApprovalTaskStatus::Pending->value,
                DistributionApprovalTaskStatus::Waiting->value,
            ])
            ->update(['status' => DistributionApprovalTaskStatus::Cancelled->value]);
    }

    private function notifyStage(DistributionBatch $batch, DistributionApprovalStage $stage, string $event): void
    {
        $this->notifier->send(
            $this->recipients($stage, $batch),
            $batch,
            sprintf('distribution:%d:revision:%d:stage:%d:%s', $batch->id, $batch->revision, $stage->id, $event),
            'Distribution approval assigned',
            sprintf('%s is waiting for %s.', $batch->batch_code, $stage->name),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(DistributionApprovalStage $stage, DistributionBatch $batch): Collection
    {
        if ($stage->user_id !== null) {
            $user = User::query()->find($stage->user_id);

            return $user instanceof User ? collect([$user]) : collect();
        }

        if ($stage->role_name === null || $stage->role_name === '') {
            return collect();
        }

        $query = User::role($stage->role_name);

        if (! in_array($stage->role_name, [RoleName::Executive->value, RoleName::SuperAdmin->value], true)) {
            $query->where('department_id', $batch->department_id);
        }

        return $query->get();
    }
}
