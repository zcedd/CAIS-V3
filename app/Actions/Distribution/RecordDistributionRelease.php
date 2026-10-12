<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionItemApprovalStatus;
use App\Enums\DistributionReleaseOutcome;
use App\Models\Assistance;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\DistributionReleaseRecord;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDistributionRelease
{
    public function __construct(
        private DistributionAudit $audit,
        private RecordDistributionAssistanceStatus $assistanceStatus,
    ) {}

    /**
     * @param  array{
     *     distribution_batch_item_id: int,
     *     distribution_batch_item_line_id?: int|null,
     *     outcome: string,
     *     actual_quantity?: string|null,
     *     actual_amount?: string|null,
     *     receipt_reference?: string|null,
     *     identity_verified?: bool,
     *     remarks?: string|null,
     *     released_at?: string|null
     * }  $data
     */
    public function __invoke(DistributionBatch $batch, User $actor, array $data): DistributionReleaseRecord
    {
        return DB::transaction(function () use ($batch, $actor, $data): DistributionReleaseRecord {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if (! in_array($batch->status, [DistributionBatchStatus::Scheduled, DistributionBatchStatus::InProgress], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Releases can be recorded only after the batch is scheduled and the distribution has started.',
                ]);
            }

            if ($batch->status === DistributionBatchStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'status' => 'Start the distribution before recording releases.',
                ]);
            }

            $item = DistributionBatchItem::query()
                ->where('distribution_batch_id', $batch->id)
                ->whereKey($data['distribution_batch_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->approval_status !== DistributionItemApprovalStatus::Approved) {
                throw ValidationException::withMessages([
                    'distribution_batch_item_id' => 'Only approved beneficiaries can receive assistance.',
                ]);
            }

            if ($item->releases()->where('outcome', DistributionReleaseOutcome::Absent)->exists()) {
                throw ValidationException::withMessages([
                    'outcome' => 'This beneficiary is already marked absent.',
                ]);
            }

            $outcome = DistributionReleaseOutcome::from($data['outcome']);
            $remarks = trim((string) ($data['remarks'] ?? ''));

            if ($outcome !== DistributionReleaseOutcome::Absent) {
                throw ValidationException::withMessages([
                    'outcome' => 'Record a served or partial release from the venue desk so the photo, acknowledgment, and receipt are stored with it.',
                ]);
            }

            if ($outcome === DistributionReleaseOutcome::Absent) {
                if ($item->releases()->exists()) {
                    throw ValidationException::withMessages([
                        'outcome' => 'A beneficiary with a recorded release cannot be marked absent.',
                    ]);
                }

                if ($remarks === '') {
                    throw ValidationException::withMessages([
                        'remarks' => 'Record why the beneficiary was absent.',
                    ]);
                }

                $record = $this->store($item, null, $outcome, null, null, $actor, $data, $remarks);
                $item->loadMissing('assistance');

                if ($item->assistance instanceof Assistance) {
                    $this->assistanceStatus->recordAbsent($item->assistance, $actor, $remarks);
                }

                $this->audit->record($actor, $batch, 'release_recorded', [
                    'revision' => $batch->revision,
                    'distribution_batch_item_id' => $item->id,
                    'outcome' => $outcome->value,
                ]);

                return $record;
            }
        });
    }

    private function store(
        DistributionBatchItem $item,
        ?DistributionBatchItemLine $line,
        DistributionReleaseOutcome $outcome,
        ?string $actualQuantity,
        ?string $actualAmount,
        User $actor,
        array $data,
        string $remarks,
        ?int $assistanceItemId = null,
        ?string $receipt = null,
    ): DistributionReleaseRecord {
        return DistributionReleaseRecord::query()->create([
            'distribution_batch_item_id' => $item->id,
            'distribution_batch_item_line_id' => $line?->id,
            'assistance_item_id' => $assistanceItemId,
            'outcome' => $outcome,
            'actual_quantity' => $actualQuantity,
            'actual_amount' => $actualAmount,
            'released_at' => $data['released_at'] ?? now(),
            'released_by' => $actor->id,
            'receipt_reference' => $receipt,
            'identity_verified' => (bool) ($data['identity_verified'] ?? false),
            'remarks' => $remarks !== '' ? $remarks : null,
        ]);
    }
}
