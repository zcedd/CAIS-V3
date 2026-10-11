<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementItemApprovalStatus;
use App\Enums\DisbursementTransactionStatus;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchItem;
use App\Models\DisbursementTransaction;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementReferenceNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartDisbursementTransaction
{
    public function __construct(
        private DisbursementReferenceNumbers $numbers,
        private DisbursementAudit $audit,
    ) {}

    public function __invoke(DisbursementBatch $batch, DisbursementBatchItem $item, User $actor, string $idempotencyKey): DisbursementTransaction
    {
        return DB::transaction(function () use ($batch, $item, $actor, $idempotencyKey): DisbursementTransaction {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $item = DisbursementBatchItem::query()
                ->where('disbursement_batch_id', $batch->id)
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOpen($batch, $item);

            $existingKey = DisbursementTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingKey instanceof DisbursementTransaction) {
                if ((int) $existingKey->disbursement_batch_item_id !== (int) $item->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This release attempt is already tied to another beneficiary.',
                    ]);
                }

                return $existingKey;
            }

            $openDraft = DisbursementTransaction::query()
                ->where('disbursement_batch_item_id', $item->id)
                ->where('status', DisbursementTransactionStatus::Draft)
                ->lockForUpdate()
                ->first();

            if ($openDraft instanceof DisbursementTransaction) {
                if ((int) $openDraft->created_by !== (int) $actor->id) {
                    throw ValidationException::withMessages([
                        'transaction' => 'Another officer already has an open release for this beneficiary.',
                    ]);
                }

                return $openDraft;
            }

            $transaction = DisbursementTransaction::query()->create([
                'disbursement_batch_id' => $batch->id,
                'disbursement_batch_item_id' => $item->id,
                'transaction_number' => $this->numbers->transactionNumber(),
                'idempotency_key' => $idempotencyKey,
                'status' => DisbursementTransactionStatus::Draft,
                'verification_status' => 'pending',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->audit->record($actor, $batch, 'transaction_started', [
                'disbursement_transaction_id' => $transaction->id,
                'disbursement_batch_item_id' => $item->id,
                'transaction_number' => $transaction->transaction_number,
            ]);

            return $transaction;
        });
    }

    private function assertOpen(DisbursementBatch $batch, DisbursementBatchItem $item): void
    {
        if ($batch->status !== DisbursementBatchStatus::InProgress) {
            throw ValidationException::withMessages([
                'status' => 'Start the disbursement before releasing assistance.',
            ]);
        }

        if ($item->approval_status !== DisbursementItemApprovalStatus::Approved) {
            throw ValidationException::withMessages([
                'disbursement_batch_item_id' => 'Only an approved beneficiary in this batch can be released.',
            ]);
        }
    }
}
