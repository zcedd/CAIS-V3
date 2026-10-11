<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use App\Services\Disbursement\DisbursementReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteDisbursementBatch
{
    public function __construct(
        private DisbursementReconciliation $reconciliation,
        private DisbursementBatchWorkflow $workflow,
        private DisbursementAudit $audit,
    ) {}

    public function __invoke(DisbursementBatch $batch, User $actor): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($batch->status !== DisbursementBatchStatus::InProgress) {
                throw ValidationException::withMessages([
                    'status' => 'Only a disbursement that is in progress can be completed.',
                ]);
            }

            $summary = $this->reconciliation->summarize($batch);

            if (! $summary['ready_to_complete']) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Record an outcome for every approved beneficiary before completing the disbursement.',
                ]);
            }

            $updated = $this->workflow->move($batch, DisbursementBatchStatus::Completed, $actor, [
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
