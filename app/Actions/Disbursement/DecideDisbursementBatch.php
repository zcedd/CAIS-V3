<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementApprovalMode;
use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementDecisionAction;
use App\Enums\DisbursementItemApprovalStatus;
use App\Models\DisbursementApprovalDecision;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementAuthorizer;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use App\Services\Disbursement\DisbursementStageFlow;
use App\Services\Disbursement\DisbursementTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideDisbursementBatch
{
    public function __construct(
        private DisbursementBatchWorkflow $workflow,
        private DisbursementAuthorizer $authorizer,
        private DisbursementStageFlow $stages,
        private DisbursementTotals $totals,
        private DisbursementAudit $audit,
    ) {}

    /**
     * @param  array{decision: string, remarks?: string|null, lock_version?: int|null}  $data
     */
    public function __invoke(DisbursementBatch $batch, User $actor, array $data): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            if ($batch->status !== DisbursementBatchStatus::ForApproval) {
                throw ValidationException::withMessages([
                    'status' => 'This disbursement batch is not waiting for approval.',
                ]);
            }

            $decision = DisbursementDecisionAction::from($data['decision']);
            $remarks = trim((string) ($data['remarks'] ?? ''));
            $task = $this->stages->actionableTask($batch, $actor);
            $this->authorizer->assertCanDecide($actor, $batch, $task);

            if ($decision === DisbursementDecisionAction::Approved && $batch->approval_mode !== DisbursementApprovalMode::Batch) {
                throw ValidationException::withMessages([
                    'decision' => 'This batch uses individual beneficiary approval. Finalize it after each beneficiary is reviewed.',
                ]);
            }

            if (in_array($decision, [DisbursementDecisionAction::Returned, DisbursementDecisionAction::Rejected], true) && $remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'A reason is required.',
                ]);
            }

            DisbursementApprovalDecision::query()->create([
                'disbursement_batch_id' => $batch->id,
                'disbursement_approval_task_id' => $task->id,
                'disbursement_approval_stage_id' => $task->disbursement_approval_stage_id,
                'revision' => $batch->revision,
                'actor_id' => $actor->id,
                'previous_decision' => null,
                'decision' => $decision,
                'remarks' => $remarks !== '' ? $remarks : null,
            ]);

            $this->stages->completeTask($task, $actor);

            if ($decision === DisbursementDecisionAction::Approved) {
                $batch->load('items.lines');

                foreach ($batch->items as $item) {
                    if ($item->approval_status === DisbursementItemApprovalStatus::Excluded) {
                        continue;
                    }

                    foreach ($item->lines as $line) {
                        $line->forceFill([
                            'approved_amount' => $line->proposed_amount,
                            'approved_quantity' => $line->proposed_quantity,
                        ])->save();
                    }

                    $item->forceFill([
                        'approval_status' => DisbursementItemApprovalStatus::Approved,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                        'review_remarks' => $remarks !== '' ? $remarks : null,
                    ])->save();
                }

                $this->totals->sync($batch);
                $updated = $this->stages->advance($batch->refresh(), $actor);
            } elseif ($decision === DisbursementDecisionAction::Returned) {
                $updated = $this->stages->returnBatch($batch, $actor, $remarks);
            } else {
                $updated = $this->stages->rejectBatch($batch, $actor);
            }

            $this->audit->record($actor, $updated, 'batch_decided', [
                'revision' => $updated->revision,
                'decision' => $decision->value,
            ]);

            return $updated->refresh();
        });
    }
}
