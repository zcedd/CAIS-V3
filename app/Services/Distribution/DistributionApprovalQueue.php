<?php

namespace App\Services\Distribution;

use App\Enums\DistributionApprovalTaskStatus;
use App\Models\DistributionApprovalTask;
use App\Models\User;

class DistributionApprovalQueue
{
    public function __construct(
        private DistributionAuthorizer $authorizer,
        private DistributionReconciliation $reconciliation,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingFor(User $user, string $showRoute): array
    {
        return DistributionApprovalTask::query()
            ->with([
                'stage',
                'batch.program:id,name',
                'batch.department:id,name,slug',
                'batch.items.lines.item.unitMeasurement',
            ])
            ->where('status', DistributionApprovalTaskStatus::Pending)
            ->whereHas('batch', fn ($query) => $query->whereColumn('distribution_batches.revision', 'distribution_approval_tasks.revision'))
            ->latest('id')
            ->get()
            ->filter(function (DistributionApprovalTask $task) use ($user): bool {
                return $task->stage !== null && $this->authorizer->isAssigned($user, $task->stage);
            })
            ->map(function (DistributionApprovalTask $task) use ($showRoute): array {
                $batchId = $task->batch?->id;

                return [
                    'task_id' => $task->id,
                    'stage' => $task->stage?->name,
                    'batch_id' => $batchId,
                    'show_url' => $batchId === null ? null : route($showRoute, $batchId),
                    'batch_code' => $task->batch?->batch_code,
                    'title' => $task->batch?->title,
                    'program' => $task->batch?->program?->name,
                    'department' => $task->batch?->department?->name,
                    'department_slug' => $task->batch?->department?->slug,
                    'scheduled_at' => $task->batch?->scheduled_at?->toIso8601String(),
                    'beneficiary_count' => $task->batch?->beneficiary_count,
                    'proposed_cash_total' => $task->batch?->proposed_cash_total,
                    'offer' => $task->batch === null ? null : $this->reconciliation->offer($task->batch),
                ];
            })
            ->values()
            ->all();
    }
}
