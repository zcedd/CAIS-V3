<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Models\DistributionApprovalPolicy;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchRevision;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionBatchWorkflow;
use App\Services\Distribution\DistributionNotifier;
use App\Services\Distribution\DistributionStageFlow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleDistributionBatch
{
    public function __construct(
        private DistributionBatchWorkflow $workflow,
        private DistributionStageFlow $stages,
        private DistributionAudit $audit,
        private DistributionNotifier $notifier,
    ) {}

    /**
     * @param  array{
     *     scheduled_at: string,
     *     venue?: string|null,
     *     venue_details?: string|null,
     *     venue_latitude?: string|float|null,
     *     venue_longitude?: string|float|null,
     *     reason?: string|null,
     *     lock_version?: int|null
     * }  $data
     */
    public function __invoke(DistributionBatch $batch, User $actor, array $data): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load('policy');
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            $before = [
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'venue' => $batch->venue,
                'venue_details' => $batch->venue_details,
            ];
            $venue = $data['venue'] ?? $batch->venue;
            $awaitingRelease = in_array($batch->status, [
                DistributionBatchStatus::Approved,
                DistributionBatchStatus::Scheduled,
            ], true);

            if ($awaitingRelease && $this->scheduleChanged($batch, $data)) {
                return $this->submitForReapproval($batch, $actor, $data, $venue, $before);
            }

            $attributes = [
                'scheduled_at' => $data['scheduled_at'],
                'venue' => $venue,
            ];
            $this->fillVenueAttributes($attributes, $data);

            if ($batch->status === DistributionBatchStatus::Scheduled) {
                $batch->fill($attributes);
                $batch->lock_version = ((int) $batch->lock_version) + 1;
                $batch->save();
                $updated = $batch;
            } else {
                $updated = $this->workflow->move($batch, DistributionBatchStatus::Scheduled, $actor, $attributes);
            }

            $creator = $updated->creator;

            if ($creator instanceof User) {
                $this->notifier->send(
                    collect([$creator]),
                    $updated,
                    sprintf('distribution:%d:scheduled:%s', $updated->id, $updated->scheduled_at?->toIso8601String()),
                    'Distribution scheduled',
                    sprintf('%s is scheduled for %s.', $updated->batch_code, $updated->scheduled_at?->toDateTimeString()),
                );
            }

            $this->audit->record($actor, $updated, 'scheduled', [
                'revision' => $updated->revision,
                'before' => $before,
                'after' => [
                    'scheduled_at' => $updated->scheduled_at?->toIso8601String(),
                    'venue' => $updated->venue,
                ],
            ]);

            return $updated->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{scheduled_at: string|null, venue: string|null, venue_details: string|null}  $before
     */
    private function submitForReapproval(
        DistributionBatch $batch,
        User $actor,
        array $data,
        mixed $venue,
        array $before,
    ): DistributionBatch {
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Explain why the schedule or venue is changing.',
            ]);
        }

        $policy = $batch->policy;

        if (! $policy instanceof DistributionApprovalPolicy) {
            throw ValidationException::withMessages([
                'approval' => 'This distribution batch has no approval policy.',
            ]);
        }

        $batch->revision = ((int) $batch->revision) + 1;
        $batch->scheduled_at = $data['scheduled_at'];
        $batch->venue = is_string($venue) ? $venue : null;
        $this->fillVenue($batch, $data);
        $batch->approved_at = null;
        $batch->save();

        DistributionBatchRevision::query()->create([
            'distribution_batch_id' => $batch->id,
            'revision' => $batch->revision,
            'snapshot' => [
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'venue' => $batch->venue,
                'venue_details' => $batch->venue_details,
                'reason' => $reason,
            ],
            'submitted_by' => $actor->id,
        ]);

        $updated = $this->workflow->move($batch->refresh(), DistributionBatchStatus::ForApproval, $actor);
        $this->stages->assignTasks($updated, $policy);

        $this->audit->record($actor, $updated, 'rescheduled', [
            'revision' => $updated->revision,
            'before' => $before,
            'after' => [
                'scheduled_at' => $updated->scheduled_at?->toIso8601String(),
                'venue' => $updated->venue,
                'venue_details' => $updated->venue_details,
            ],
            'reason' => $reason,
        ]);

        return $updated->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function scheduleChanged(DistributionBatch $batch, array $data): bool
    {
        if ($batch->scheduled_at === null) {
            return true;
        }

        $incoming = Carbon::parse($data['scheduled_at'])->startOfMinute();
        $current = $batch->scheduled_at->copy()->startOfMinute();

        if (! $incoming->equalTo($current)) {
            return true;
        }

        $venue = array_key_exists('venue', $data) ? $data['venue'] : $batch->venue;

        if ($this->plain($venue) !== $this->plain($batch->venue)) {
            return true;
        }

        foreach (['venue_details', 'venue_latitude', 'venue_longitude'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($this->plain($data[$field]) !== $this->plain($batch->{$field})) {
                return true;
            }
        }

        return false;
    }

    private function plain(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim(number_format((float) $value, 7, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillVenue(DistributionBatch $batch, array $data): void
    {
        if (array_key_exists('venue_details', $data)) {
            $batch->venue_details = $data['venue_details'];
        }

        if (array_key_exists('venue_latitude', $data)) {
            $batch->venue_latitude = $data['venue_latitude'];
        }

        if (array_key_exists('venue_longitude', $data)) {
            $batch->venue_longitude = $data['venue_longitude'];
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $data
     */
    private function fillVenueAttributes(array &$attributes, array $data): void
    {
        foreach (['venue_details', 'venue_latitude', 'venue_longitude'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }
    }
}
