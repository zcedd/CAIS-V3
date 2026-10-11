<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementApprovalMode;
use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementFinalizationScope;
use App\Enums\DisbursementItemApprovalStatus;
use App\Models\DisbursementApprovalPolicy;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementAuthorizer;
use App\Services\Disbursement\DisbursementStageFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeDisbursementBatch
{
    public function __construct(
        private DisbursementAuthorizer $authorizer,
        private DisbursementStageFlow $stages,
        private DisbursementAudit $audit,
    ) {}

    public function __invoke(DisbursementBatch $batch, User $actor): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load(['items', 'policy']);

            if ($batch->approval_mode !== DisbursementApprovalMode::Beneficiary) {
                throw ValidationException::withMessages([
                    'status' => 'Whole-batch approval is finished by approving the batch.',
                ]);
            }

            if ($batch->status !== DisbursementBatchStatus::ForApproval) {
                throw ValidationException::withMessages([
                    'status' => 'This disbursement batch is not ready to finalize.',
                ]);
            }

            $policy = $batch->policy;

            if (! $policy instanceof DisbursementApprovalPolicy) {
                throw ValidationException::withMessages([
                    'approval' => 'This disbursement batch has no approval policy.',
                ]);
            }

            $task = $this->stages->actionableTask($batch, $actor);
            $this->authorizer->assertCanDecide($actor, $batch, $task);

            $unresolved = $batch->items->filter(fn ($item): bool => in_array($item->approval_status, [
                DisbursementItemApprovalStatus::Pending,
                DisbursementItemApprovalStatus::Returned,
            ], true));

            if ($unresolved->isNotEmpty() && ! $policy->allow_partial_finalization) {
                throw ValidationException::withMessages([
                    'roster' => 'Every beneficiary needs a final decision before this batch can be finalized.',
                ]);
            }

            $approved = $batch->items->filter(
                fn ($item): bool => $item->approval_status === DisbursementItemApprovalStatus::Approved,
            );

            if ($approved->isEmpty()) {
                throw ValidationException::withMessages([
                    'roster' => 'Approve at least one beneficiary before finalizing the batch.',
                ]);
            }

            $batch->forceFill([
                'finalization_scope' => $unresolved->isNotEmpty()
                    ? DisbursementFinalizationScope::Partial
                    : DisbursementFinalizationScope::Complete,
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
