<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionApprovalMode;
use App\Enums\DistributionBatchStatus;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\Program;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionRoster;
use App\Services\Distribution\DistributionTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDistributionBatch
{
    public function __construct(
        private DistributionRoster $roster,
        private DistributionTotals $totals,
        private DistributionAudit $audit,
    ) {}

    /**
     * @param  array{
     *     program_id: int,
     *     title: string,
     *     description?: string|null,
     *     scheduled_at: string,
     *     venue: string,
     *     notes?: string|null,
     *     assistance_ids: list<int>
     * }  $data
     */
    public function __invoke(Department $department, User $actor, array $data): DistributionBatch
    {
        return DB::transaction(function () use ($department, $actor, $data): DistributionBatch {
            $program = Program::query()->whereKey($data['program_id'])->lockForUpdate()->firstOrFail();

            if ((int) $program->department_id !== (int) $department->id || ! $program->isEncodable()) {
                throw ValidationException::withMessages([
                    'program_id' => 'Choose an open program in this department.',
                ]);
            }

            $batch = DistributionBatch::query()->create([
                'batch_code' => $this->nextCode(),
                'program_id' => $program->id,
                'department_id' => $department->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'scheduled_at' => $data['scheduled_at'],
                'venue' => $data['venue'],
                'approval_mode' => DistributionApprovalMode::Batch,
                'status' => DistributionBatchStatus::Draft,
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
        $last = DistributionBatch::withTrashed()
            ->where('batch_code', 'like', $prefix.'%')
            ->orderByDesc('batch_code')
            ->lockForUpdate()
            ->value('batch_code');

        $sequence = is_string($last) ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
