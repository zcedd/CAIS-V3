<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalApprovalMode;
use App\Enums\DispersalBatchStatus;
use App\Enums\DispersalFinalizationScope;
use App\Enums\DispersalItemApprovalStatus;
use App\Models\DispersalApprovalPolicy;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalAuthorizer;
use App\Services\Dispersal\DispersalStageFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeDispersalBatch
{
    public function __construct(
        private DispersalAuthorizer $authorizer,
        private DispersalStageFlow $stages,
        private DispersalAudit $audit,
    ) {}

    public function __invoke(DispersalBatch $batch, User $actor): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load(['items', 'policy']);

            if ($batch->approval_mode !== DispersalApprovalMode::Beneficiary) {
                throw ValidationException::withMessages([
                    'status' => 'Whole-batch approval is finished by approving the batch.',
                ]);
            }

            if ($batch->status !== DispersalBatchStatus::ForApproval) {
                throw ValidationException::withMessages([
                    'status' => 'This dispersal batch is not ready to finalize.',
                ]);
            }

            $policy = $batch->policy;

            if (! $policy instanceof DispersalApprovalPolicy) {
                throw ValidationException::withMessages([
                    'approval' => 'This dispersal batch has no approval policy.',
                ]);
            }

            $task = $this->stages->actionableTask($batch, $actor);
            $this->authorizer->assertCanDecide($actor, $batch, $task);

            $unresolved = $batch->items->filter(fn ($item): bool => in_array($item->approval_status, [
                DispersalItemApprovalStatus::Pending,
                DispersalItemApprovalStatus::Returned,
            ], true));

            if ($unresolved->isNotEmpty() && ! $policy->allow_partial_finalization) {
                throw ValidationException::withMessages([
                    'roster' => 'Every beneficiary needs a final decision before this batch can be finalized.',
                ]);
            }

            $approved = $batch->items->filter(
                fn ($item): bool => $item->approval_status === DispersalItemApprovalStatus::Approved,
            );

            if ($approved->isEmpty()) {
                throw ValidationException::withMessages([
                    'roster' => 'Approve at least one beneficiary before finalizing the batch.',
                ]);
            }

            $batch->forceFill([
                'finalization_scope' => $unresolved->isNotEmpty()
                    ? DispersalFinalizationScope::Partial
                    : DispersalFinalizationScope::Complete,
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
