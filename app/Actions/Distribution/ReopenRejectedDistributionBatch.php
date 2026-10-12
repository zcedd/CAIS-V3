<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionBatchWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenRejectedDistributionBatch
{
    public function __construct(
        private DistributionBatchWorkflow $workflow,
        private DistributionAudit $audit,
    ) {}

    /**
     * @param  array{reason: string}  $data
     */
    public function __invoke(DistributionBatch $batch, User $actor, array $data): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load('policy');

            if ($batch->status !== DistributionBatchStatus::Rejected) {
                throw ValidationException::withMessages([
                    'status' => 'Only a rejected distribution batch can be reopened.',
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

            $updated = $this->workflow->move($batch, DistributionBatchStatus::Draft, $actor, [
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
