<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalBatchStatus;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalBatchWorkflow;
use Illuminate\Support\Facades\DB;

class StartDispersalBatch
{
    public function __construct(
        private DispersalBatchWorkflow $workflow,
        private DispersalAudit $audit,
    ) {}

    public function __invoke(DispersalBatch $batch, User $actor): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $updated = $this->workflow->move($batch, DispersalBatchStatus::InProgress, $actor);
            $this->audit->record($actor, $updated, 'started', [
                'revision' => $updated->revision,
            ]);

            return $updated;
        });
    }
}
