<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalBatchStatus;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Services\Dispersal\DispersalBatchWorkflow;
use App\Services\Dispersal\DispersalRoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValidateDispersalBatch
{
    public function __construct(
        private DispersalRoster $roster,
        private DispersalBatchWorkflow $workflow,
    ) {}

    public function __invoke(DispersalBatch $batch, User $actor): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $inspection = $this->roster->inspect($batch);
            $batch->forceFill([
                'validation_summary' => $inspection,
            ])->save();

            if ($inspection['errors'] !== []) {
                throw ValidationException::withMessages([
                    'roster' => $inspection['errors'],
                ]);
            }

            if ($batch->status === DispersalBatchStatus::ForValidation) {
                return $batch->refresh();
            }

            return $this->workflow->move($batch->refresh(), DispersalBatchStatus::ForValidation, $actor);
        });
    }
}
