<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use Illuminate\Support\Facades\DB;

class StartDisbursementBatch
{
    public function __construct(
        private DisbursementBatchWorkflow $workflow,
        private DisbursementAudit $audit,
    ) {}

    public function __invoke(DisbursementBatch $batch, User $actor): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $updated = $this->workflow->move($batch, DisbursementBatchStatus::InProgress, $actor, [
                'opened_at' => $batch->opened_at ?? now(),
            ]);
            $this->audit->record($actor, $updated, 'started', [
                'revision' => $updated->revision,
            ]);

            return $updated;
        });
    }
}
