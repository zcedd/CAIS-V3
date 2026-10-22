<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionAcknowledgmentMode;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionEvidenceType;
use App\Enums\DistributionItemApprovalStatus;
use App\Enums\DistributionRecipientType;
use App\Enums\DistributionReleaseOutcome;
use App\Enums\DistributionTransactionStatus;
use App\Enums\DistributionVerificationMethod;
use App\Enums\PermissionName;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\DistributionBatchItemLine;
use App\Models\DistributionReleaseRecord;
use App\Models\DistributionTransaction;
use App\Models\Individual;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionReceiptService;
use App\Services\Distribution\DistributionReferenceNumbers;
use App\Support\DecimalAmount;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeDistributionTransaction
{
    public function __construct(
        private ApplyDistributionReleaseToAssistance $applyRelease,
        private DistributionReceiptService $receipts,
        private DistributionReferenceNumbers $numbers,
        private DistributionAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(DistributionBatch $batch, DistributionTransaction $transaction, User $actor, array $data): DistributionTransaction
    {
        return DB::transaction(function () use ($batch, $transaction, $actor, $data): DistributionTransaction {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $transaction = DistributionTransaction::query()
                ->where('distribution_batch_id', $batch->id)
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status === DistributionTransactionStatus::Finalized) {
                return $transaction->load('currentReceipt');
            }

            if ($transaction->status !== DistributionTransactionStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'This release can no longer be finalized.',
                ]);
            }

            if ($batch->status !== DistributionBatchStatus::InProgress) {
                throw ValidationException::withMessages([
                    'status' => 'Assistance can be released only while the distribution is in progress.',
                ]);
            }

            $item = DistributionBatchItem::query()
                ->where('distribution_batch_id', $batch->id)
                ->whereKey($transaction->distribution_batch_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->approval_status !== DistributionItemApprovalStatus::Approved) {
                throw ValidationException::withMessages([
                    'beneficiary' => 'This beneficiary is not approved for release.',
                ]);
            }

            $this->assertAcknowledgment($transaction, $actor, $data);

            $witness = $this->witness($batch, $data['witnessed_by'] ?? null);
            $receiptNumber = $this->numbers->receiptNumber();
            $capturedAt = $this->capturedAt($data);
            $releasedLines = [];

            foreach ($data['lines'] as $row) {
                $releasedLines[] = $this->releaseLine(
                    $batch,
                    $item,
                    $transaction,
                    $actor,
                    $row,
                    $receiptNumber,
                    (string) ($data['notes'] ?? ''),
                    $capturedAt,
                );
            }

            if ($releasedLines === []) {
                throw ValidationException::withMessages([
                    'lines' => 'Record at least one assistance line.',
                ]);
            }

            $recipientType = DistributionRecipientType::from($data['received_by_type']);
            $mode = DistributionAcknowledgmentMode::from($data['acknowledgment_mode']);
            $transaction->fill([
                'received_by_type' => $recipientType,
                'received_by_name' => $data['received_by_name'],
                'representative_relationship' => $data['representative_relationship'] ?? null,
                'representative_verification' => $data['representative_verification'] ?? null,
                'verification_method' => DistributionVerificationMethod::from($data['verification_method']),
                'verification_status' => 'verified',
                'acknowledgment_mode' => $mode,
                'alternative_reason' => $mode === DistributionAcknowledgmentMode::Alternative ? $data['alternative_reason'] : null,
                'privacy_acknowledged_at' => now(),
                'camera_waiver_reason' => $data['camera_waiver_reason'] ?? null,
                'camera_waived_by' => filled($data['camera_waiver_reason'] ?? null) ? $actor->id : null,
                'notes' => $data['notes'] ?? null,
                'released_by' => $actor->id,
                'witnessed_by' => $witness?->id,
                'captured_at' => $capturedAt,
                'finalized_at' => now(),
                'status' => DistributionTransactionStatus::Finalized,
                'updated_by' => $actor->id,
            ]);
            $transaction->save();

            $snapshot = $this->snapshot($batch, $item, $transaction, $actor, $witness, $receiptNumber, $releasedLines);
            $this->receipts->issue($transaction, $actor, $snapshot);

            $this->audit->record($actor, $batch, 'transaction_finalized', [
                'distribution_transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'receipt_number' => $receiptNumber,
                'distribution_batch_item_id' => $item->id,
            ]);

            return $transaction->refresh()->load('currentReceipt');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAcknowledgment(DistributionTransaction $transaction, User $actor, array $data): void
    {
        if (! ($data['privacy_acknowledged'] ?? false)) {
            throw ValidationException::withMessages([
                'privacy_acknowledged' => 'Acknowledge the privacy notice before releasing assistance.',
            ]);
        }

        if (! ($data['identity_verified'] ?? false)) {
            throw ValidationException::withMessages([
                'identity_verified' => 'Confirm the recipient before releasing assistance.',
            ]);
        }

        $recipientType = DistributionRecipientType::from($data['received_by_type']);

        if ($recipientType === DistributionRecipientType::Representative) {
            if (trim((string) ($data['representative_relationship'] ?? '')) === '' || trim((string) ($data['representative_verification'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'representative_relationship' => 'Record the representative relationship and how the authorization was checked.',
                ]);
            }
        }

        $mode = DistributionAcknowledgmentMode::from($data['acknowledgment_mode']);
        $hasSignature = $transaction->evidence()->where('evidence_type', DistributionEvidenceType::Signature)->exists();

        if ($mode === DistributionAcknowledgmentMode::Signature && ! $hasSignature) {
            throw ValidationException::withMessages([
                'acknowledgment_mode' => 'Capture a signature, or record an approved alternative acknowledgment.',
            ]);
        }

        if ($mode === DistributionAcknowledgmentMode::Alternative && trim((string) ($data['alternative_reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'alternative_reason' => 'Record why the recipient cannot sign and which alternative was used.',
            ]);
        }

        $hasPhoto = $transaction->evidence()->where('evidence_type', DistributionEvidenceType::LivePhoto)->exists();
        $waiver = trim((string) ($data['camera_waiver_reason'] ?? ''));

        if (! $hasPhoto && $waiver === '') {
            throw ValidationException::withMessages([
                'live_photo' => 'Capture a live photo from the camera before finalizing. If the camera is unavailable, an authorized officer must record the waiver.',
            ]);
        }

        if (! $hasPhoto && ! $actor->can(PermissionName::DistributionWaiveLivePhoto->value)) {
            throw ValidationException::withMessages([
                'camera_waiver_reason' => 'You are not authorized to waive live photo evidence.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{description: string, quantity: string|null, unit: string|null, amount: string|null, outcome: string}
     */
    private function releaseLine(
        DistributionBatch $batch,
        DistributionBatchItem $item,
        DistributionTransaction $transaction,
        User $actor,
        array $row,
        string $receiptNumber,
        string $notes,
        DateTimeInterface $capturedAt,
    ): array {
        $line = DistributionBatchItemLine::query()
            ->where('distribution_batch_item_id', $item->id)
            ->whereKey($row['id'] ?? 0)
            ->lockForUpdate()
            ->first();

        if (! $line instanceof DistributionBatchItemLine) {
            throw ValidationException::withMessages([
                'lines' => 'One of the assistance lines is not part of this beneficiary allocation.',
            ]);
        }

        $line->loadMissing('item.unitMeasurement');
        $actualQuantity = $line->approved_quantity !== null ? DecimalAmount::of($row['actual_quantity'] ?? null) : null;
        $actualAmount = $line->approved_amount !== null ? DecimalAmount::of($row['actual_amount'] ?? null) : null;

        if ($line->approved_quantity !== null && ! DecimalAmount::isPositive($actualQuantity)) {
            throw ValidationException::withMessages([
                'lines' => 'Enter the quantity being released.',
            ]);
        }

        if ($line->approved_amount !== null && ! DecimalAmount::isPositive($actualAmount)) {
            throw ValidationException::withMessages([
                'lines' => 'Enter the amount being released.',
            ]);
        }

        $releasedQuantity = DecimalAmount::of((string) $line->releases()->sum('actual_quantity'));
        $releasedAmount = DecimalAmount::of((string) $line->releases()->sum('actual_amount'));
        $nextQuantity = $actualQuantity === null ? $releasedQuantity : DecimalAmount::add($releasedQuantity, $actualQuantity);
        $nextAmount = $actualAmount === null ? $releasedAmount : DecimalAmount::add($releasedAmount, $actualAmount);

        if ($line->approved_quantity !== null && DecimalAmount::gt($nextQuantity, $line->approved_quantity)) {
            throw ValidationException::withMessages([
                'lines' => 'The released quantity exceeds the approved quantity.',
            ]);
        }

        if ($line->approved_amount !== null && DecimalAmount::gt($nextAmount, $line->approved_amount)) {
            throw ValidationException::withMessages([
                'lines' => 'The released amount exceeds the approved amount.',
            ]);
        }

        $quantityMet = $line->approved_quantity === null || DecimalAmount::comp($nextQuantity, $line->approved_quantity) >= 0;
        $amountMet = $line->approved_amount === null || DecimalAmount::comp($nextAmount, $line->approved_amount) >= 0;
        $outcome = $quantityMet && $amountMet
            ? DistributionReleaseOutcome::Served
            : DistributionReleaseOutcome::Partial;

        if ($outcome === DistributionReleaseOutcome::Partial && trim($notes) === '') {
            throw ValidationException::withMessages([
                'notes' => 'A partial release needs a documented reason.',
            ]);
        }

        $value = $actualAmount ?? $actualQuantity ?? '0.00';
        $assistanceItem = $this->applyRelease->apply($line, $value, $actor);

        DistributionReleaseRecord::query()->create([
            'distribution_batch_item_id' => $item->id,
            'distribution_batch_item_line_id' => $line->id,
            'distribution_transaction_id' => $transaction->id,
            'assistance_item_id' => $assistanceItem->id,
            'outcome' => $outcome,
            'actual_quantity' => $actualQuantity,
            'actual_amount' => $actualAmount,
            'released_at' => $capturedAt,
            'released_by' => $actor->id,
            'receipt_reference' => $receiptNumber,
            'identity_verified' => true,
            'remarks' => trim($notes) !== '' ? trim($notes) : null,
        ]);

        return [
            'description' => $line->item?->name ?? 'Assistance',
            'quantity' => $actualQuantity,
            'unit' => $line->item?->unitMeasurement?->name,
            'amount' => $actualAmount,
            'outcome' => $outcome->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function capturedAt(array $data): DateTimeInterface
    {
        $value = $data['captured_at'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return now();
        }

        return Carbon::parse($value);
    }

    private function witness(DistributionBatch $batch, mixed $userId): ?User
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $witness = User::query()->whereKey($userId)->first();

        if (! $witness instanceof User || (int) $witness->department_id !== (int) $batch->department_id) {
            throw ValidationException::withMessages([
                'witnessed_by' => 'Choose a witness from this department.',
            ]);
        }

        return $witness;
    }

    /**
     * @param  list<array{description: string, quantity: string|null, unit: string|null, amount: string|null, outcome: string}>  $lines
     * @return array<string, mixed>
     */
    private function snapshot(
        DistributionBatch $batch,
        DistributionBatchItem $item,
        DistributionTransaction $transaction,
        User $actor,
        ?User $witness,
        string $receiptNumber,
        array $lines,
    ): array {
        $item->loadMissing([
            'beneficiary.beneficiable' => function ($morphTo): void {
                $morphTo->morphWith([
                    Individual::class => ['address.city', 'beneficiaryIdentification'],
                ]);
            },
            'assistance',
        ]);
        $batch->loadMissing('program', 'department');
        $individual = $item->beneficiary?->beneficiable instanceof Individual
            ? $item->beneficiary->beneficiable
            : null;
        $address = $individual?->address;
        $total = '0.00';

        foreach ($lines as $line) {
            $total = DecimalAmount::add($total, $line['amount']);
        }

        $mode = $transaction->acknowledgment_mode;

        return [
            'agency' => (string) config('app.name'),
            'receipt_number' => $receiptNumber,
            'transaction_number' => $transaction->transaction_number,
            'batch_code' => $batch->batch_code,
            'program' => $batch->program?->name,
            'department' => $batch->department?->name,
            'recipient_name' => $transaction->received_by_name,
            'recipient_type' => $transaction->received_by_type?->label(),
            'representative_relationship' => $transaction->representative_relationship,
            'beneficiary_reference' => $item->beneficiary?->cais_number,
            'beneficiary_name' => $item->beneficiary?->name,
            'beneficiary_type' => $item->beneficiary ? class_basename((string) $item->beneficiary->beneficiable_type) : null,
            'beneficiary_address' => $address === null
                ? null
                : collect([$address->name, $address->city?->name])->filter()->implode(', '),
            'beneficiary_id_number' => $individual?->beneficiaryIdentification->first()?->number,
            'assistance_id' => $item->assistance_id,
            'distribution_date' => $batch->scheduled_at?->toDateString(),
            'venue' => $batch->venue,
            'items' => $lines,
            'total_amount' => DecimalAmount::isPositive($total) ? $total : null,
            'acknowledgment' => 'I acknowledge that I received the assistance described in this receipt.',
            'acknowledgment_mode' => $mode?->label(),
            'alternative_reason' => $transaction->alternative_reason,
            'released_by' => $actor->name,
            'witness' => $witness?->name,
            'issued_at' => now()->toIso8601String(),
            'evidence_note' => 'The signature and photo proof of delivery, when captured, are printed on this receipt. The public verification page does not show them. A camera image or file hash does not by itself prove identity.',
        ];
    }
}
