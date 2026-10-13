<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionApprovalMode;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionFinalizationScope;
use App\Enums\DistributionItemApprovalStatus;
use App\Enums\DistributionType;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenIndividualDistribution
{
    public function __construct(
        private CreateDistributionBatch $create,
        private DistributionTotals $totals,
        private RecordDistributionAssistanceStatus $assistanceStatus,
        private DistributionAudit $audit,
    ) {}

    /**
     * @param  array{
     *     program_id: int,
     *     title: string,
     *     description?: string|null,
     *     scheduled_at: string,
     *     venue?: string|null,
     *     notes?: string|null,
     *     approval_mode?: string|null,
     *     assistance_ids: list<int>
     * }  $data
     */
    public function __invoke(Department $department, User $actor, array $data): DistributionBatch
    {
        return DB::transaction(function () use ($department, $actor, $data): DistributionBatch {
            $this->assertIndividual($data);
            $data['distribution_type'] = DistributionType::Individual->value;

            $batch = ($this->create)($department, $actor, $data);
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $batch->load('items.lines');

            if ($batch->items->count() !== 1) {
                throw ValidationException::withMessages([
                    'assistance_ids' => 'Individual distribution covers one assistance request.',
                ]);
            }

            $item = $batch->items->first();

            foreach ($item->lines as $line) {
                $line->forceFill([
                    'approved_amount' => $line->proposed_amount,
                    'approved_quantity' => $line->proposed_quantity,
                ])->save();
            }

            $item->forceFill([
                'approval_status' => DistributionItemApprovalStatus::Approved,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $this->totals->sync($batch);

            $batch->forceFill([
                'status' => DistributionBatchStatus::InProgress,
                'approved_at' => now(),
                'opened_at' => now(),
                'finalization_scope' => DistributionFinalizationScope::Complete,
                'lock_version' => ((int) $batch->lock_version) + 1,
                'updated_by' => $actor->id,
            ])->save();

            $opened = $batch->refresh();
            $this->assistanceStatus->reflect($opened, $actor);
            $this->audit->record($actor, $opened, 'individual_opened', [
                'revision' => $opened->revision,
                'after' => [
                    'status' => DistributionBatchStatus::InProgress->value,
                    'assistance_id' => $item->assistance_id,
                ],
            ]);

            return $opened->refresh();
        });
    }

    /**
     * @param  array{approval_mode?: string|null, assistance_ids: list<int>}  $data
     */
    private function assertIndividual(array $data): void
    {
        if (DistributionApprovalMode::tryFrom((string) ($data['approval_mode'] ?? '')) !== DistributionApprovalMode::Beneficiary) {
            throw ValidationException::withMessages([
                'approval_mode' => 'Individual distribution does not use batch approval.',
            ]);
        }

        if (count($data['assistance_ids']) !== 1) {
            throw ValidationException::withMessages([
                'assistance_ids' => 'Individual distribution covers one assistance request.',
            ]);
        }
    }
}
