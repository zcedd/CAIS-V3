<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalBatchStatus;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalBatchWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenRejectedDispersalBatch
{
    public function __construct(
        private DispersalBatchWorkflow $workflow,
        private DispersalAudit $audit,
    ) {}

    /**
     * @param  array{reason: string}  $data
     */
    public function __invoke(DispersalBatch $batch, User $actor, array $data): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load('policy');

            if ($batch->status !== DispersalBatchStatus::Rejected) {
                throw ValidationException::withMessages([
                    'status' => 'Only a rejected dispersal batch can be reopened.',
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

            $updated = $this->workflow->move($batch, DispersalBatchStatus::Draft, $actor, [
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
