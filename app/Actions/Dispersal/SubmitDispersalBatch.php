<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalBatchStatus;
use App\Enums\DispersalItemApprovalStatus;
use App\Models\DispersalBatch;
use App\Models\DispersalBatchRevision;
use App\Models\User;
use App\Services\Dispersal\DispersalApprovalPolicyResolver;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalBatchWorkflow;
use App\Services\Dispersal\DispersalRoster;
use App\Services\Dispersal\DispersalStageFlow;
use App\Services\Dispersal\DispersalTotals;
use App\Support\DecimalAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitDispersalBatch
{
    public function __construct(
        private DispersalRoster $roster,
        private DispersalTotals $totals,
        private DispersalBatchWorkflow $workflow,
        private DispersalApprovalPolicyResolver $policies,
        private DispersalStageFlow $stages,
        private DispersalAudit $audit,
    ) {}

    public function __invoke(DispersalBatch $batch, User $actor): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load(['items.lines', 'program']);

            if ($batch->status !== DispersalBatchStatus::ForValidation) {
                throw ValidationException::withMessages([
                    'status' => 'Validate the dispersal batch before submitting it.',
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
                if ($item->approval_status === DispersalItemApprovalStatus::Excluded) {
                    continue;
                }

                $item->forceFill([
                    'approval_status' => DispersalItemApprovalStatus::Pending,
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

            $policy = $this->policies->resolve($batch->program, $batch->approval_mode, $amount);
            $snapshot = $this->snapshot($batch);

            $batch->forceFill([
                'dispersal_approval_policy_id' => $policy->id,
                'submitted_at' => now(),
                'locked_at' => now(),
                'approved_at' => null,
                'finalization_scope' => null,
                'validation_summary' => $inspection,
            ])->save();

            DispersalBatchRevision::query()->create([
                'dispersal_batch_id' => $batch->id,
                'revision' => $batch->revision,
                'snapshot' => $snapshot,
                'submitted_by' => $actor->id,
            ]);

            $this->totals->sync($batch);
            $moved = $this->workflow->move($batch->refresh(), DispersalBatchStatus::ForApproval, $actor);
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
    private function snapshot(DispersalBatch $batch): array
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
