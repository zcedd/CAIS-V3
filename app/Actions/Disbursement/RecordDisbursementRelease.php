<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementItemApprovalStatus;
use App\Enums\DisbursementReleaseOutcome;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchItem;
use App\Models\DisbursementBatchItemLine;
use App\Models\DisbursementReleaseRecord;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementReconciliation;
use App\Support\DecimalAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDisbursementRelease
{
    public function __construct(
        private ApplyDisbursementReleaseToAssistance $applyRelease,
        private DisbursementAudit $audit,
        private DisbursementReconciliation $reconciliation,
    ) {}

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

            $line = DisbursementBatchItemLine::query()
                ->where('disbursement_batch_item_id', $item->id)
                ->whereKey($data['disbursement_batch_item_line_id'] ?? 0)
                ->lockForUpdate()
                ->first();

            if (! $line instanceof DisbursementBatchItemLine) {
                throw ValidationException::withMessages([
                    'disbursement_batch_item_line_id' => 'Choose the assistance line being released.',
                ]);
            }

            if (! ($data['identity_verified'] ?? false)) {
                throw ValidationException::withMessages([
                    'identity_verified' => 'Confirm the beneficiary identity before release.',
                ]);
            }

            $receipt = trim((string) ($data['receipt_reference'] ?? ''));

            if ($receipt === '') {
                throw ValidationException::withMessages([
                    'receipt_reference' => 'A receipt or acknowledgment reference is required.',
                ]);
            }

            $actualQuantity = $line->approved_quantity !== null
                ? DecimalAmount::of($data['actual_quantity'] ?? null)
                : null;
            $actualAmount = $line->approved_amount !== null
                ? DecimalAmount::of($data['actual_amount'] ?? null)
                : null;

            $releasedQuantity = DecimalAmount::of((string) $line->releases()->sum('actual_quantity'));
            $releasedAmount = DecimalAmount::of((string) $line->releases()->sum('actual_amount'));
            $nextQuantity = $actualQuantity === null ? $releasedQuantity : DecimalAmount::add($releasedQuantity, $actualQuantity);
            $nextAmount = $actualAmount === null ? $releasedAmount : DecimalAmount::add($releasedAmount, $actualAmount);

            if ($line->approved_quantity !== null && DecimalAmount::gt($nextQuantity, $line->approved_quantity)) {
                throw ValidationException::withMessages([
                    'actual_quantity' => 'The released quantity exceeds the approved quantity.',
                ]);
            }

            if ($line->approved_amount !== null && DecimalAmount::gt($nextAmount, $line->approved_amount)) {
                throw ValidationException::withMessages([
                    'actual_amount' => 'The released amount exceeds the approved amount.',
                ]);
            }

            $quantityMet = $line->approved_quantity === null || DecimalAmount::comp($nextQuantity, $line->approved_quantity) >= 0;
            $amountMet = $line->approved_amount === null || DecimalAmount::comp($nextAmount, $line->approved_amount) >= 0;
            $resolvedOutcome = $quantityMet && $amountMet
                ? DisbursementReleaseOutcome::Served
                : DisbursementReleaseOutcome::Partial;

            if ($resolvedOutcome === DisbursementReleaseOutcome::Partial && $remarks === '') {
                throw ValidationException::withMessages([
                    'remarks' => 'A partial release needs a documented reason.',
                ]);
            }

            $value = $actualAmount ?? $actualQuantity ?? '0.00';
            $assistanceItem = $this->applyRelease->apply($line, $value, $actor);

            $record = $this->store(
                $item,
                $line,
                $resolvedOutcome,
                $actualQuantity,
                $actualAmount,
                $actor,
                $data,
                $remarks,
                $assistanceItem->id,
                $receipt,
            );

            $this->audit->record($actor, $batch, 'release_recorded', [
                'revision' => $batch->revision,
                'disbursement_batch_item_id' => $item->id,
                'disbursement_batch_item_line_id' => $line->id,
                'outcome' => $resolvedOutcome->value,
                'assistance_item_id' => $assistanceItem->id,
                'reconciliation' => $this->reconciliation->summarize($batch->refresh()),
            ]);

            return $record;
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
