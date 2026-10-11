<?php

namespace App\Actions\Disbursement;

use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchAssignment;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignDisbursementPersonnel
{
    public function __construct(private DisbursementAudit $audit) {}

    /**
     * @param  list<int>  $userIds
     */
    public function __invoke(DisbursementBatch $batch, User $actor, array $userIds): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor, $userIds): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $ids = collect($userIds)->map(fn (mixed $id): int => (int) $id)->unique()->values();
            $officers = User::query()
                ->where('department_id', $batch->department_id)
                ->whereIn('id', $ids)
                ->pluck('id');

            if ($officers->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Assign only personnel from this department.',
                ]);
            }

            $batch->assignments()->delete();

            foreach ($officers as $userId) {
                DisbursementBatchAssignment::query()->create([
                    'disbursement_batch_id' => $batch->id,
                    'user_id' => $userId,
                    'assignment_role' => 'releasing_officer',
                    'assigned_by' => $actor->id,
                ]);
            }

            $this->audit->record($actor, $batch, 'personnel_assigned', [
                'user_ids' => $officers->all(),
            ]);

            return $batch->refresh()->load('assignments.user');
        });
    }
}
