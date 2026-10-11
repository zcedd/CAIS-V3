<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Models\DisbursementApprovalPolicy;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchRevision;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use App\Services\Disbursement\DisbursementNotifier;
use App\Services\Disbursement\DisbursementStageFlow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleDisbursementBatch
{
    public function __construct(
        private DisbursementBatchWorkflow $workflow,
        private DisbursementStageFlow $stages,
        private DisbursementAudit $audit,
        private DisbursementNotifier $notifier,
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
    public function __invoke(DisbursementBatch $batch, User $actor, array $data): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load('policy');
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            $before = [
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'venue' => $batch->venue,
            ];
            $venue = $data['venue'] ?? $batch->venue;
            $changingExistingSchedule = $batch->status === DisbursementBatchStatus::Scheduled
                && (
                    $batch->scheduled_at?->toIso8601String() !== Carbon::parse($data['scheduled_at'])->toIso8601String()
                    || $batch->venue !== $venue
                );

            if ($changingExistingSchedule && $batch->policy?->reschedule_requires_reapproval) {
                $reason = trim((string) ($data['reason'] ?? ''));

                if ($reason === '') {
                    throw ValidationException::withMessages([
                        'reason' => 'Explain why the disbursement is being rescheduled.',
                    ]);
                }

                $policy = $batch->policy;

                if (! $policy instanceof DisbursementApprovalPolicy) {
                    throw ValidationException::withMessages([
                        'approval' => 'This disbursement batch has no approval policy.',
                    ]);
                }

                $batch->revision = ((int) $batch->revision) + 1;
                $batch->scheduled_at = $data['scheduled_at'];
                $batch->venue = $venue;
                $this->fillVenue($batch, $data);
                $batch->approved_at = null;
                $batch->finalization_scope = null;
                $batch->save();

                DisbursementBatchRevision::query()->create([
                    'disbursement_batch_id' => $batch->id,
                    'revision' => $batch->revision,
                    'snapshot' => [
                        'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                        'venue' => $batch->venue,
                        'reason' => $reason,
                    ],
                    'submitted_by' => $actor->id,
                ]);

                $updated = $this->workflow->move($batch->refresh(), DisbursementBatchStatus::ForApproval, $actor);
                $this->stages->assignTasks($updated, $policy);

                $this->audit->record($actor, $updated, 'rescheduled', [
                    'revision' => $updated->revision,
                    'before' => $before,
                    'after' => [
                        'scheduled_at' => $updated->scheduled_at?->toIso8601String(),
                        'venue' => $updated->venue,
                    ],
                    'reason' => $reason,
                ]);

                return $updated->refresh();
            }

            $attributes = [
                'scheduled_at' => $data['scheduled_at'],
                'venue' => $venue,
            ];
            $this->fillVenueAttributes($attributes, $data);

            if ($batch->status === DisbursementBatchStatus::Scheduled) {
                $batch->fill($attributes);
                $batch->lock_version = ((int) $batch->lock_version) + 1;
                $batch->save();
                $updated = $batch;
            } else {
                $updated = $this->workflow->move($batch, DisbursementBatchStatus::Scheduled, $actor, $attributes);
            }

            $creator = $updated->creator;

            if ($creator instanceof User) {
                $this->notifier->send(
                    collect([$creator]),
                    $updated,
                    sprintf('disbursement:%d:scheduled:%s', $updated->id, $updated->scheduled_at?->toIso8601String()),
                    'Disbursement scheduled',
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
     */
    private function fillVenue(DisbursementBatch $batch, array $data): void
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
