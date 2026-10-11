<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalBatchStatus;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalBatchWorkflow;
use App\Services\Dispersal\DispersalReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteDispersalBatch
{
    public function __construct(
        private DispersalReconciliation $reconciliation,
        private DispersalBatchWorkflow $workflow,
        private DispersalAudit $audit,
    ) {}

    public function __invoke(DispersalBatch $batch, User $actor): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($batch->status !== DispersalBatchStatus::InProgress) {
                throw ValidationException::withMessages([
                    'status' => 'Only a dispersal that is in progress can be completed.',
                ]);
            }

            $summary = $this->reconciliation->summarize($batch);

            if (! $summary['ready_to_complete']) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Record an outcome for every approved beneficiary before completing the dispersal.',
                ]);
            }

            $updated = $this->workflow->move($batch, DispersalBatchStatus::Completed, $actor, [
                'completed_at' => now(),
            ]);

            $this->audit->record($actor, $updated, 'completed', [
                'revision' => $updated->revision,
                'reconciliation' => $summary,
            ]);

            return $updated;
        });
    }
}
