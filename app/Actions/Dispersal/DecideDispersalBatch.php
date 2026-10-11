<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalApprovalMode;
use App\Enums\DispersalBatchStatus;
use App\Enums\DispersalDecisionAction;
use App\Enums\DispersalItemApprovalStatus;
use App\Models\DispersalApprovalDecision;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalAuthorizer;
use App\Services\Dispersal\DispersalBatchWorkflow;
use App\Services\Dispersal\DispersalStageFlow;
use App\Services\Dispersal\DispersalTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideDispersalBatch
{
    public function __construct(
        private DispersalBatchWorkflow $workflow,
        private DispersalAuthorizer $authorizer,
        private DispersalStageFlow $stages,
        private DispersalTotals $totals,
        private DispersalAudit $audit,
    ) {}

    /**
     * @param  array{decision: string, remarks?: string|null, lock_version?: int|null}  $data
     */
    public function __invoke(DispersalBatch $batch, User $actor, array $data): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            if ($batch->status !== DispersalBatchStatus::ForApproval) {
                throw ValidationException::withMessages([
                    'status' => 'This dispersal batch is not waiting for approval.',
                ]);
            }

            $decision = DispersalDecisionAction::from($data['decision']);
            $remarks = trim((string) ($data['remarks'] ?? ''));
            $task = $this->stages->actionableTask($batch, $actor);
            $this->authorizer->assertCanDecide($actor, $batch, $task);

            if ($decision === DispersalDecisionAction::Approved && $batch->approval_mode !== DispersalApprovalMode::Batch) {
                throw ValidationException::withMessages([
                    'decision' => 'This batch uses individual beneficiary approval. Finalize it after each beneficiary is reviewed.',
                ]);
            }

            if (in_array($decision, [DispersalDecisionAction::Returned, DispersalDecisionAction::Rejected], true) && $remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'A reason is required.',
                ]);
            }

            DispersalApprovalDecision::query()->create([
                'dispersal_batch_id' => $batch->id,
                'dispersal_approval_task_id' => $task->id,
                'dispersal_approval_stage_id' => $task->dispersal_approval_stage_id,
                'revision' => $batch->revision,
                'actor_id' => $actor->id,
                'previous_decision' => null,
                'decision' => $decision,
                'remarks' => $remarks !== '' ? $remarks : null,
            ]);

            $this->stages->completeTask($task, $actor);

            if ($decision === DispersalDecisionAction::Approved) {
                $batch->load('items.lines');

                foreach ($batch->items as $item) {
                    if ($item->approval_status === DispersalItemApprovalStatus::Excluded) {
                        continue;
                    }

                    foreach ($item->lines as $line) {
                        $line->forceFill([
                            'approved_amount' => $line->proposed_amount,
                            'approved_quantity' => $line->proposed_quantity,
                        ])->save();
                    }

                    $item->forceFill([
                        'approval_status' => DispersalItemApprovalStatus::Approved,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                        'review_remarks' => $remarks !== '' ? $remarks : null,
                    ])->save();
                }

                $this->totals->sync($batch);
                $updated = $this->stages->advance($batch->refresh(), $actor);
            } elseif ($decision === DispersalDecisionAction::Returned) {
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
