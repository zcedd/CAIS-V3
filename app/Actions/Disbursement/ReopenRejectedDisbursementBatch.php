<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenRejectedDisbursementBatch
{
    public function __construct(
        private DisbursementBatchWorkflow $workflow,
        private DisbursementAudit $audit,
    ) {}

    /**
     * @param  array{reason: string}  $data
     */
    public function __invoke(DisbursementBatch $batch, User $actor, array $data): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load('policy');

            if ($batch->status !== DisbursementBatchStatus::Rejected) {
                throw ValidationException::withMessages([
                    'status' => 'Only a rejected disbursement batch can be reopened.',
                ]);
            }

            if (! $batch->policy?->allow_resubmit_after_reject) {
                throw ValidationException::withMessages([
                    'status' => 'The approval policy does not allow a rejected batch to be resubmitted.',
                ]);
            }

            $reason = trim($data['reason']);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Explain why the rejected batch is being reopened.',
                ]);
            }

            $updated = $this->workflow->move($batch, DisbursementBatchStatus::Draft, $actor, [
                'locked_at' => null,
            ]);

            $this->audit->record($actor, $updated, 'reopened', [
                'revision' => $updated->revision,
                'reason' => $reason,
            ]);

            return $updated;
        });
    }
}
