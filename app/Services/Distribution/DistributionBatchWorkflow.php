<?php

namespace App\Services\Distribution;

use App\Actions\Distribution\RecordDistributionAssistanceStatus;
use App\Enums\DistributionBatchStatus;
use App\Models\DistributionBatch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DistributionBatchWorkflow
{
    public function __construct(
        private DistributionAudit $audit,
        private RecordDistributionAssistanceStatus $assistanceStatus,
    ) {}

    public function assertLock(DistributionBatch $batch, ?int $expected): void
    {
        if ($expected === null) {
            return;
        }

        if ((int) $batch->lock_version !== $expected) {
            throw ValidationException::withMessages([
                'lock_version' => 'This distribution batch was updated by someone else. Refresh and try again.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function move(DistributionBatch $batch, DistributionBatchStatus $to, User $actor, array $attributes = []): DistributionBatch
    {
        $from = $batch->status;

        if (! $from instanceof DistributionBatchStatus || ! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'A %s distribution batch cannot move to %s.',
                    $from?->value ?? 'unknown',
                    $to->value,
                ),
            ]);
        }

        $batch->fill($attributes);
        $batch->status = $to;
        $batch->lock_version = ((int) $batch->lock_version) + 1;
        $batch->save();

        $this->audit->record($actor, $batch, 'status_changed', [
            'revision' => $batch->revision,
            'before' => ['status' => $from->value],
            'after' => ['status' => $to->value],
        ]);

        $this->assistanceStatus->reflect($batch, $actor);

        return $batch;
    }
}
