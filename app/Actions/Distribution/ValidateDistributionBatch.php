<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionBatchWorkflow;
use App\Services\Distribution\DistributionRoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValidateDistributionBatch
{
    public function __construct(
        private DistributionRoster $roster,
        private DistributionBatchWorkflow $workflow,
    ) {}

    public function __invoke(DistributionBatch $batch, User $actor): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $inspection = $this->roster->inspect($batch);
            $batch->forceFill([
                'validation_summary' => $inspection,
            ])->save();

            if ($inspection['errors'] !== []) {
                throw ValidationException::withMessages([
                    'roster' => $inspection['errors'],
                ]);
            }

            if ($batch->status === DistributionBatchStatus::ForValidation) {
                return $batch->refresh();
            }

            return $this->workflow->move($batch->refresh(), DistributionBatchStatus::ForValidation, $actor);
        });
    }
}
