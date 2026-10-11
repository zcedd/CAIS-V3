<?php

namespace App\Services\Dispersal;

use App\Enums\DispersalBatchStatus;
use App\Models\DispersalBatch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DispersalBatchWorkflow
{
    public function __construct(private DispersalAudit $audit) {}

    public function assertLock(DispersalBatch $batch, ?int $expected): void
    {
        if ($expected === null) {
            return;
        }

        if ((int) $batch->lock_version !== $expected) {
            throw ValidationException::withMessages([
                'lock_version' => 'This dispersal batch was updated by someone else. Refresh and try again.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function move(DispersalBatch $batch, DispersalBatchStatus $to, User $actor, array $attributes = []): DispersalBatch
    {
        $from = $batch->status;

        if (! $from instanceof DispersalBatchStatus || ! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'A %s dispersal batch cannot move to %s.',
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

        return $batch;
    }
}
