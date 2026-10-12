<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionItemApprovalStatus;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchRevision;
use App\Models\User;
use App\Services\Distribution\DistributionApprovalPolicyResolver;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionBatchWorkflow;
use App\Services\Distribution\DistributionRoster;
use App\Services\Distribution\DistributionStageFlow;
use App\Services\Distribution\DistributionTotals;
use App\Support\DecimalAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitDistributionBatch
{
    public function __construct(
        private DistributionRoster $roster,
        private DistributionTotals $totals,
        private DistributionBatchWorkflow $workflow,
        private DistributionApprovalPolicyResolver $policies,
        private DistributionStageFlow $stages,
        private DistributionAudit $audit,
    ) {}

    public function __invoke(DistributionBatch $batch, User $actor): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load(['items.lines', 'program']);

            if ($batch->status !== DistributionBatchStatus::ForValidation) {
                throw ValidationException::withMessages([
                    'status' => 'Validate the distribution batch before submitting it.',
                ]);
            }

            $inspection = $this->roster->inspect($batch);

            if ($inspection['errors'] !== []) {
                $batch->forceFill(['validation_summary' => $inspection])->save();

                throw ValidationException::withMessages([
                    'roster' => $inspection['errors'],
                ]);
            }

            if ($batch->submitted_at !== null) {
                $batch->revision = ((int) $batch->revision) + 1;
            }

            foreach ($batch->items as $item) {
                if ($item->approval_status === DistributionItemApprovalStatus::Excluded) {
                    continue;
                }

                $item->forceFill([
                    'approval_status' => DistributionItemApprovalStatus::Pending,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_remarks' => null,
                ])->save();

                foreach ($item->lines as $line) {
                    $line->forceFill([
                        'approved_amount' => null,
                        'approved_quantity' => null,
                    ])->save();
                }
            }

            $amount = $batch->items
                ->flatMap(fn ($item) => $item->lines)
                ->reduce(
                    fn (string $carry, $line): string => DecimalAmount::add($carry, $line->proposed_amount),
                    '0.00',
                );

            $policy = $this->policies->resolve($batch->program, $amount);
            $snapshot = $this->snapshot($batch);

            $batch->forceFill([
                'distribution_approval_policy_id' => $policy->id,
                'submitted_at' => now(),
                'locked_at' => now(),
                'approved_at' => null,
                'finalization_scope' => null,
                'validation_summary' => $inspection,
            ])->save();

            DistributionBatchRevision::query()->create([
                'distribution_batch_id' => $batch->id,
                'revision' => $batch->revision,
                'snapshot' => $snapshot,
                'submitted_by' => $actor->id,
            ]);

            $this->totals->sync($batch);
            $moved = $this->workflow->move($batch->refresh(), DistributionBatchStatus::ForApproval, $actor);
            $moved->setRelation('policy', $policy);
            $this->stages->assignTasks($moved, $policy);

            $this->audit->record($actor, $moved, 'submitted', [
                'revision' => $moved->revision,
                'policy_id' => $policy->id,
            ]);

            return $moved->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(DistributionBatch $batch): array
    {
        $batch->load('items.lines');

        return [
            'title' => $batch->title,
            'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
            'venue' => $batch->venue,
            'approval_mode' => $batch->approval_mode->value,
            'items' => $batch->items->map(static fn ($item): array => [
                'assistance_id' => $item->assistance_id,
                'beneficiary_id' => $item->beneficiary_id,
                'approval_status' => $item->approval_status->value,
                'lines' => $item->lines->map(static fn ($line): array => [
                    'item_id' => $line->item_id,
                    'assistance_item_id' => $line->assistance_item_id,
                    'proposed_quantity' => $line->proposed_quantity,
                    'proposed_amount' => $line->proposed_amount,
                ])->all(),
            ])->all(),
        ];
    }
}
