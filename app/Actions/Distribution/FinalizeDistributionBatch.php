<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionFinalizationScope;
use App\Enums\DistributionItemApprovalStatus;
use App\Models\DistributionApprovalPolicy;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionAuthorizer;
use App\Services\Distribution\DistributionStageFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeDistributionBatch
{
    public function __construct(
        private DistributionAuthorizer $authorizer,
        private DistributionStageFlow $stages,
        private DistributionAudit $audit,
    ) {}

    public function __invoke(DistributionBatch $batch, User $actor): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load(['items', 'policy']);

            if ($batch->status !== DistributionBatchStatus::ForApproval) {
                throw ValidationException::withMessages([
                    'status' => 'This distribution batch is not ready to finalize.',
                ]);
            }

            $policy = $batch->policy;

            if (! $policy instanceof DistributionApprovalPolicy) {
                throw ValidationException::withMessages([
                    'approval' => 'This distribution batch has no approval policy.',
                ]);
            }

            $task = $this->stages->actionableTask($batch, $actor);
            $this->authorizer->assertCanDecide($actor, $batch, $task);

            $unresolved = $batch->items->filter(fn ($item): bool => in_array($item->approval_status, [
                DistributionItemApprovalStatus::Pending,
                DistributionItemApprovalStatus::Returned,
            ], true));

            if ($unresolved->isNotEmpty() && ! $policy->allow_partial_finalization) {
                throw ValidationException::withMessages([
                    'roster' => 'Every beneficiary needs a final decision before this batch can be finalized.',
                ]);
            }

            $approved = $batch->items->filter(
                fn ($item): bool => $item->approval_status === DistributionItemApprovalStatus::Approved,
            );

            if ($approved->isEmpty()) {
                throw ValidationException::withMessages([
                    'roster' => 'Approve at least one beneficiary before finalizing the batch.',
                ]);
            }

            $batch->forceFill([
                'finalization_scope' => $unresolved->isNotEmpty()
                    ? DistributionFinalizationScope::Partial
                    : DistributionFinalizationScope::Complete,
            ])->save();

            $this->stages->completeTask($task, $actor);
            $updated = $this->stages->advance($batch->refresh(), $actor);

            $this->audit->record($actor, $updated, 'finalized', [
                'revision' => $updated->revision,
                'finalization_scope' => $updated->finalization_scope?->value,
            ]);

            return $updated->refresh();
        });
    }
}
