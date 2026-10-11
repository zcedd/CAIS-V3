<?php

namespace App\Actions\Disbursement;

use App\Models\DisbursementBatch;
use App\Models\Program;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use App\Services\Disbursement\DisbursementRoster;
use App\Services\Disbursement\DisbursementTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDisbursementBatch
{
    public function __construct(
        private DisbursementRoster $roster,
        private DisbursementTotals $totals,
        private DisbursementAudit $audit,
        private DisbursementBatchWorkflow $workflow,
    ) {}

    /**
     * @param  array{
     *     program_id: int,
     *     title: string,
     *     description?: string|null,
     *     scheduled_at: string,
     *     venue: string,
     *     approval_mode: string,
     *     notes?: string|null,
     *     assistance_ids: list<int>,
     *     lock_version?: int|null
     * }  $data
     */
    public function __invoke(DisbursementBatch $batch, User $actor, array $data): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            if (! $batch->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => 'This disbursement batch cannot be edited.',
                ]);
            }

            $program = Program::query()->findOrFail($data['program_id']);

            if ((int) $program->department_id !== (int) $batch->department_id || ! $program->isEncodable()) {
                throw ValidationException::withMessages([
                    'program_id' => 'Choose an open program in this department.',
                ]);
            }

            if ((int) $program->id !== (int) $batch->program_id && $batch->items()->exists()) {
                $batch->items()->delete();
            }

            $before = [
                'title' => $batch->title,
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'venue' => $batch->venue,
                'program_id' => $batch->program_id,
            ];

            $batch->fill([
                'program_id' => $program->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'scheduled_at' => $data['scheduled_at'],
                'venue' => $data['venue'],
                'approval_mode' => $data['approval_mode'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $batch->setRelation('program', $program);
            $this->roster->sync($batch, $data['assistance_ids'], $actor);
            $batch->lock_version = ((int) $batch->lock_version) + 1;
            $batch->save();
            $this->totals->sync($batch);

            $this->audit->record($actor, $batch, 'updated', [
                'revision' => $batch->revision,
                'before' => $before,
                'after' => [
                    'title' => $batch->title,
                    'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                    'venue' => $batch->venue,
                    'program_id' => $batch->program_id,
                ],
            ]);

            return $batch->refresh();
        });
    }
}
