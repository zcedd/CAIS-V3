<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementItemApprovalStatus;
use App\Enums\DisbursementReleaseOutcome;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchItem;
use App\Models\DisbursementReleaseRecord;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDisbursementRelease
{
    public function __construct(private DisbursementAudit $audit) {}

    /**
     * @param  array{
     *     disbursement_batch_item_id: int,
     *     disbursement_batch_item_line_id?: int|null,
     *     outcome: string,
     *     actual_quantity?: string|null,
     *     actual_amount?: string|null,
     *     receipt_reference?: string|null,
     *     identity_verified?: bool,
     *     remarks?: string|null,
     *     released_at?: string|null
     * }  $data
     */
    public function __invoke(DisbursementBatch $batch, User $actor, array $data): DisbursementReleaseRecord
    {
        return DB::transaction(function () use ($batch, $actor, $data): DisbursementReleaseRecord {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if (! in_array($batch->status, [DisbursementBatchStatus::Scheduled, DisbursementBatchStatus::InProgress], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Releases can be recorded only after the batch is scheduled and the disbursement has started.',
                ]);
            }

            if ($batch->status === DisbursementBatchStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'status' => 'Start the disbursement before recording releases.',
                ]);
            }

            $item = DisbursementBatchItem::query()
                ->where('disbursement_batch_id', $batch->id)
                ->whereKey($data['disbursement_batch_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->approval_status !== DisbursementItemApprovalStatus::Approved) {
                throw ValidationException::withMessages([
                    'disbursement_batch_item_id' => 'Only approved beneficiaries can receive assistance.',
                ]);
            }

            if ($item->releases()->where('outcome', DisbursementReleaseOutcome::Absent)->exists()) {
                throw ValidationException::withMessages([
                    'outcome' => 'This beneficiary is already marked absent.',
                ]);
            }

            $outcome = DisbursementReleaseOutcome::from($data['outcome']);
            $remarks = trim((string) ($data['remarks'] ?? ''));

            if ($outcome !== DisbursementReleaseOutcome::Absent) {
                throw ValidationException::withMessages([
                    'outcome' => 'Record a served or partial release from the venue desk so the photo, acknowledgment, and receipt are stored with it.',
                ]);
            }

            if ($outcome === DisbursementReleaseOutcome::Absent) {
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
                $this->audit->record($actor, $batch, 'release_recorded', [
                    'revision' => $batch->revision,
                    'disbursement_batch_item_id' => $item->id,
                    'outcome' => $outcome->value,
                ]);

                return $record;
            }
        });
    }

    private function store(
        DisbursementBatchItem $item,
        ?DisbursementBatchItemLine $line,
        DisbursementReleaseOutcome $outcome,
        ?string $actualQuantity,
        ?string $actualAmount,
        User $actor,
        array $data,
        string $remarks,
        ?int $assistanceItemId = null,
        ?string $receipt = null,
    ): DisbursementReleaseRecord {
        return DisbursementReleaseRecord::query()->create([
            'disbursement_batch_item_id' => $item->id,
            'disbursement_batch_item_line_id' => $line?->id,
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
