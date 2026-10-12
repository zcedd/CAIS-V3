<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionApprovalMode;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionDecisionAction;
use App\Enums\DistributionItemApprovalStatus;
use App\Models\DistributionApprovalDecision;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionAuthorizer;
use App\Services\Distribution\DistributionBatchWorkflow;
use App\Services\Distribution\DistributionStageFlow;
use App\Services\Distribution\DistributionTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideDistributionBatch
{
    public function __construct(
        private DistributionBatchWorkflow $workflow,
        private DistributionAuthorizer $authorizer,
        private DistributionStageFlow $stages,
        private DistributionTotals $totals,
        private DistributionAudit $audit,
    ) {}

    /**
     * @param  array{decision: string, remarks?: string|null, lock_version?: int|null}  $data
     */
    public function __invoke(DistributionBatch $batch, User $actor, array $data): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            if ($batch->status !== DistributionBatchStatus::ForApproval) {
                throw ValidationException::withMessages([
                    'status' => 'This distribution batch is not waiting for approval.',
                ]);
            }

            $decision = DistributionDecisionAction::from($data['decision']);
            $remarks = trim((string) ($data['remarks'] ?? ''));
            $task = $this->stages->actionableTask($batch, $actor);
            $this->authorizer->assertCanDecide($actor, $batch, $task);

            if ($decision === DistributionDecisionAction::Approved && $batch->approval_mode !== DistributionApprovalMode::Batch) {
                throw ValidationException::withMessages([
                    'decision' => 'This batch uses individual beneficiary approval. Finalize it after each beneficiary is reviewed.',
                ]);
            }

            if (in_array($decision, [DistributionDecisionAction::Returned, DistributionDecisionAction::Rejected], true) && $remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'A reason is required.',
                ]);
            }

            DistributionApprovalDecision::query()->create([
                'distribution_batch_id' => $batch->id,
                'distribution_approval_task_id' => $task->id,
                'distribution_approval_stage_id' => $task->distribution_approval_stage_id,
                'revision' => $batch->revision,
                'actor_id' => $actor->id,
                'previous_decision' => null,
                'decision' => $decision,
                'remarks' => $remarks !== '' ? $remarks : null,
            ]);

            $this->stages->completeTask($task, $actor);

            if ($decision === DistributionDecisionAction::Approved) {
                $batch->load('items.lines');

                foreach ($batch->items as $item) {
                    if ($item->approval_status === DistributionItemApprovalStatus::Excluded) {
                        continue;
                    }

                    foreach ($item->lines as $line) {
                        $line->forceFill([
                            'approved_amount' => $line->proposed_amount,
                            'approved_quantity' => $line->proposed_quantity,
                        ])->save();
                    }

                    $item->forceFill([
                        'approval_status' => DistributionItemApprovalStatus::Approved,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                        'review_remarks' => $remarks !== '' ? $remarks : null,
                    ])->save();
                }

                $this->totals->sync($batch);
                $updated = $this->stages->advance($batch->refresh(), $actor);
            } elseif ($decision === DistributionDecisionAction::Returned) {
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
