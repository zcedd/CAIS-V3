<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionBatchWorkflow;
use App\Services\Distribution\DistributionReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteDistributionBatch
{
    public function __construct(
        private DistributionReconciliation $reconciliation,
        private DistributionBatchWorkflow $workflow,
        private DistributionAudit $audit,
    ) {}

    public function __invoke(DistributionBatch $batch, User $actor): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($batch->status !== DistributionBatchStatus::InProgress) {
                throw ValidationException::withMessages([
                    'status' => 'Only a distribution that is in progress can be completed.',
                ]);
            }

            $summary = $this->reconciliation->summarize($batch);

            if (! $summary['ready_to_complete']) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Record an outcome for every approved beneficiary before completing the distribution.',
                ]);
            }

            $updated = $this->workflow->move($batch, DistributionBatchStatus::Completed, $actor, [
                'completed_at' => now(),
                'closed_at' => now(),
            ]);

            $this->audit->record($actor, $updated, 'completed', [
                'revision' => $updated->revision,
                'reconciliation' => $summary,
            ]);

            return $updated;
        });
    }
}
