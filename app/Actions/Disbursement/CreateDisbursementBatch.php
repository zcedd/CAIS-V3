<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementApprovalMode;
use App\Enums\DisbursementBatchStatus;
use App\Models\Department;
use App\Models\DisbursementBatch;
use App\Models\Program;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementRoster;
use App\Services\Disbursement\DisbursementTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDisbursementBatch
{
    public function __construct(
        private DisbursementRoster $roster,
        private DisbursementTotals $totals,
        private DisbursementAudit $audit,
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
     *     assistance_ids: list<int>
     * }  $data
     */
    public function __invoke(Department $department, User $actor, array $data): DisbursementBatch
    {
        return DB::transaction(function () use ($department, $actor, $data): DisbursementBatch {
            $program = Program::query()->whereKey($data['program_id'])->lockForUpdate()->firstOrFail();

            if ((int) $program->department_id !== (int) $department->id || ! $program->isEncodable()) {
                throw ValidationException::withMessages([
                    'program_id' => 'Choose an open program in this department.',
                ]);
            }

            $batch = DisbursementBatch::query()->create([
                'batch_code' => $this->nextCode(),
                'program_id' => $program->id,
                'department_id' => $department->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'scheduled_at' => $data['scheduled_at'],
                'venue' => $data['venue'],
                'approval_mode' => DisbursementApprovalMode::from($data['approval_mode']),
                'status' => DisbursementBatchStatus::Draft,
                'revision' => 1,
                'created_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $batch->setRelation('program', $program);
            $this->roster->sync($batch, $data['assistance_ids'], $actor);
            $this->totals->sync($batch);

            $this->audit->record($actor, $batch, 'created', [
                'revision' => $batch->revision,
                'after' => [
                    'batch_code' => $batch->batch_code,
                    'program_id' => $batch->program_id,
                    'approval_mode' => $batch->approval_mode->value,
                ],
            ]);

            return $batch->refresh();
        });
    }

    private function nextCode(): string
    {
        $prefix = 'DSB-'.now()->format('Y').'-';
        $last = DisbursementBatch::withTrashed()
            ->where('batch_code', 'like', $prefix.'%')
            ->orderByDesc('batch_code')
            ->lockForUpdate()
            ->value('batch_code');

        $sequence = is_string($last) ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
