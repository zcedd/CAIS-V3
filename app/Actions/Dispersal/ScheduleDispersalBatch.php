<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalBatchStatus;
use App\Models\DispersalApprovalPolicy;
use App\Models\DispersalBatch;
use App\Models\DispersalBatchRevision;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalBatchWorkflow;
use App\Services\Dispersal\DispersalNotifier;
use App\Services\Dispersal\DispersalStageFlow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleDispersalBatch
{
    public function __construct(
        private DispersalBatchWorkflow $workflow,
        private DispersalStageFlow $stages,
        private DispersalAudit $audit,
        private DispersalNotifier $notifier,
    ) {}

    /**
     * @param  array{scheduled_at: string, venue?: string|null, reason?: string|null, lock_version?: int|null}  $data
     */
    public function __invoke(DispersalBatch $batch, User $actor, array $data): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load('policy');
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            $before = [
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'venue' => $batch->venue,
            ];
            $venue = $data['venue'] ?? $batch->venue;
            $changingExistingSchedule = $batch->status === DispersalBatchStatus::Scheduled
                && (
                    $batch->scheduled_at?->toIso8601String() !== Carbon::parse($data['scheduled_at'])->toIso8601String()
                    || $batch->venue !== $venue
                );

            if ($changingExistingSchedule && $batch->policy?->reschedule_requires_reapproval) {
                $reason = trim((string) ($data['reason'] ?? ''));

                if ($reason === '') {
                    throw ValidationException::withMessages([
                        'reason' => 'Explain why the dispersal is being rescheduled.',
                    ]);
                }

                $policy = $batch->policy;

                if (! $policy instanceof DispersalApprovalPolicy) {
                    throw ValidationException::withMessages([
                        'approval' => 'This dispersal batch has no approval policy.',
                    ]);
                }

                $batch->revision = ((int) $batch->revision) + 1;
                $batch->scheduled_at = $data['scheduled_at'];
                $batch->venue = $venue;
                $batch->approved_at = null;
                $batch->finalization_scope = null;
                $batch->save();

                DispersalBatchRevision::query()->create([
                    'dispersal_batch_id' => $batch->id,
                    'revision' => $batch->revision,
                    'snapshot' => [
                        'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                        'venue' => $batch->venue,
                        'reason' => $reason,
                    ],
                    'submitted_by' => $actor->id,
                ]);

                $updated = $this->workflow->move($batch->refresh(), DispersalBatchStatus::ForApproval, $actor);
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

            if ($batch->status === DispersalBatchStatus::Scheduled) {
                $batch->fill($attributes);
                $batch->lock_version = ((int) $batch->lock_version) + 1;
                $batch->save();
                $updated = $batch;
            } else {
                $updated = $this->workflow->move($batch, DispersalBatchStatus::Scheduled, $actor, $attributes);
            }

            $creator = $updated->creator;

            if ($creator instanceof User) {
                $this->notifier->send(
                    collect([$creator]),
                    $updated,
                    sprintf('dispersal:%d:scheduled:%s', $updated->id, $updated->scheduled_at?->toIso8601String()),
                    'Dispersal scheduled',
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
}
