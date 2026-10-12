<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionBatchWorkflow;
use Illuminate\Support\Facades\DB;

class StartDistributionBatch
{
    public function __construct(
        private DistributionBatchWorkflow $workflow,
        private DistributionAudit $audit,
    ) {}

    public function __invoke(DistributionBatch $batch, User $actor): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $updated = $this->workflow->move($batch, DistributionBatchStatus::InProgress, $actor, [
                'opened_at' => $batch->opened_at ?? now(),
            ]);
            $this->audit->record($actor, $updated, 'started', [
                'revision' => $updated->revision,
            ]);

            return $updated;
        });
    }
}
