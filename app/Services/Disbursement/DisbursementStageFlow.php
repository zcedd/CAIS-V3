<?php

namespace App\Services\Disbursement;

use App\Enums\DisbursementApprovalStrategy;
use App\Enums\DisbursementApprovalTaskStatus;
use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementFinalizationScope;
use App\Enums\RoleName;
use App\Models\DisbursementApprovalPolicy;
use App\Models\DisbursementApprovalStage;
use App\Models\DisbursementApprovalTask;
use App\Models\DisbursementBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DisbursementStageFlow
{
    public function __construct(
        private DisbursementBatchWorkflow $workflow,
        private DisbursementNotifier $notifier,
        private DisbursementAuthorizer $authorizer,
    ) {}

    public function assignTasks(DisbursementBatch $batch, DisbursementApprovalPolicy $policy): void
    {
        $batch->tasks()
            ->whereIn('status', [
                DisbursementApprovalTaskStatus::Pending->value,
                DisbursementApprovalTaskStatus::Waiting->value,
            ])
            ->update(['status' => DisbursementApprovalTaskStatus::Cancelled->value]);

        $stages = $policy->stages()->orderBy('sequence')->get();

        if ($stages->isEmpty()) {
            throw ValidationException::withMessages([
                'approval' => 'The approval policy has no stages.',
            ]);
        }

        foreach ($stages as $index => $stage) {
            $status = $policy->strategy === DisbursementApprovalStrategy::Parallel || $index === 0
                ? DisbursementApprovalTaskStatus::Pending
                : DisbursementApprovalTaskStatus::Waiting;

            DisbursementApprovalTask::query()->create([
                'disbursement_batch_id' => $batch->id,
                'disbursement_approval_stage_id' => $stage->id,
                'revision' => $batch->revision,
                'status' => $status,
            ]);

            if ($status === DisbursementApprovalTaskStatus::Pending) {
                $this->notifyStage($batch, $stage, 'assigned');
            }
        }
    }

    public function actionableTask(DisbursementBatch $batch, User $user): DisbursementApprovalTask
    {
        $tasks = $batch->tasks()
            ->with('stage')
            ->where('revision', $batch->revision)
            ->where('status', DisbursementApprovalTaskStatus::Pending)
            ->orderBy('id')
            ->get();

        $task = $tasks->first(
            fn (DisbursementApprovalTask $candidate): bool => $candidate->stage instanceof DisbursementApprovalStage
                && $this->authorizer->isAssigned($user, $candidate->stage),
        );

        if (! $task instanceof DisbursementApprovalTask) {
            throw new AuthorizationException('You do not have an actionable approval task for this revision.');
        }

        return $task;
    }

    public function completeTask(DisbursementApprovalTask $task, User $actor): void
    {
        $task->forceFill([
            'status' => DisbursementApprovalTaskStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ])->save();
    }

    public function advance(DisbursementBatch $batch, User $actor): DisbursementBatch
    {
        $batch->loadMissing('policy');
        $policy = $batch->policy;

        if (! $policy instanceof DisbursementApprovalPolicy) {
            throw ValidationException::withMessages([
                'approval' => 'This disbursement batch has no approval policy.',
            ]);
        }

        $tasks = $batch->tasks()->with('stage')->where('revision', $batch->revision)->orderBy('id')->get();

        if ($policy->strategy === DisbursementApprovalStrategy::Sequential) {
            $waiting = $tasks->first(
                fn (DisbursementApprovalTask $task): bool => $task->status === DisbursementApprovalTaskStatus::Waiting,
            );

            $stillPending = $tasks->contains(
                fn (DisbursementApprovalTask $task): bool => $task->status === DisbursementApprovalTaskStatus::Pending,
            );

            if ($waiting instanceof DisbursementApprovalTask && ! $stillPending && $waiting->stage instanceof DisbursementApprovalStage) {
                $waiting->forceFill(['status' => DisbursementApprovalTaskStatus::Pending])->save();
                $this->notifyStage($batch, $waiting->stage, 'assigned');

                return $batch->refresh();
            }
        }

        $open = $tasks->contains(fn (DisbursementApprovalTask $task): bool => in_array($task->status, [
            DisbursementApprovalTaskStatus::Pending,
            DisbursementApprovalTaskStatus::Waiting,
        ], true));

        if ($open) {
            return $batch->refresh();
        }

        return $this->markApproved($batch, $actor);
    }

    public function markApproved(DisbursementBatch $batch, User $actor): DisbursementBatch
    {
        if ($batch->status === DisbursementBatchStatus::Approved) {
            return $batch;
        }

        $updated = $this->workflow->move($batch, DisbursementBatchStatus::Approved, $actor, [
            'approved_at' => now(),
            'finalization_scope' => $batch->finalization_scope ?? DisbursementFinalizationScope::Complete,
        ]);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('disbursement:%d:revision:%d:approved', $updated->id, $updated->revision),
                'Disbursement batch approved',
                sprintf('%s was approved.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function returnBatch(DisbursementBatch $batch, User $actor, string $remarks): DisbursementBatch
    {
        $this->cancelOpenTasks($batch);

        $updated = $this->workflow->move($batch, DisbursementBatchStatus::Returned, $actor, [
            'locked_at' => null,
        ]);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('disbursement:%d:revision:%d:returned:%s', $updated->id, $updated->revision, md5($remarks)),
                'Disbursement batch returned',
                sprintf('%s was returned for correction.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function rejectBatch(DisbursementBatch $batch, User $actor): DisbursementBatch
    {
        $this->cancelOpenTasks($batch);

        $updated = $this->workflow->move($batch, DisbursementBatchStatus::Rejected, $actor);

        $creator = $updated->creator;

        if ($creator instanceof User) {
            $this->notifier->send(
                collect([$creator]),
                $updated,
                sprintf('disbursement:%d:revision:%d:rejected', $updated->id, $updated->revision),
                'Disbursement batch rejected',
                sprintf('%s was rejected.', $updated->batch_code),
            );
        }

        return $updated;
    }

    public function cancelOpenTasks(DisbursementBatch $batch): void
    {
        $batch->tasks()
            ->whereIn('status', [
                DisbursementApprovalTaskStatus::Pending->value,
                DisbursementApprovalTaskStatus::Waiting->value,
            ])
            ->update(['status' => DisbursementApprovalTaskStatus::Cancelled->value]);
    }

    private function notifyStage(DisbursementBatch $batch, DisbursementApprovalStage $stage, string $event): void
    {
        $this->notifier->send(
            $this->recipients($stage, $batch),
            $batch,
            sprintf('disbursement:%d:revision:%d:stage:%d:%s', $batch->id, $batch->revision, $stage->id, $event),
            'Disbursement approval assigned',
            sprintf('%s is waiting for %s.', $batch->batch_code, $stage->name),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(DisbursementApprovalStage $stage, DisbursementBatch $batch): Collection
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
