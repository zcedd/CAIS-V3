<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use App\Services\Disbursement\DisbursementRoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValidateDisbursementBatch
{
    public function __construct(
        private DisbursementRoster $roster,
        private DisbursementBatchWorkflow $workflow,
    ) {}

    public function __invoke(DisbursementBatch $batch, User $actor): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $inspection = $this->roster->inspect($batch);
            $batch->forceFill([
                'validation_summary' => $inspection,
            ])->save();

            if ($inspection['errors'] !== []) {
                throw ValidationException::withMessages([
                    'roster' => $inspection['errors'],
                ]);
            }

            if ($batch->status === DisbursementBatchStatus::ForValidation) {
                return $batch->refresh();
            }

            return $this->workflow->move($batch->refresh(), DisbursementBatchStatus::ForValidation, $actor);
        });
    }
}
