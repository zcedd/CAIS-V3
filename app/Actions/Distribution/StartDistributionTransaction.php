<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionItemApprovalStatus;
use App\Enums\DistributionTransactionStatus;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\DistributionTransaction;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionReferenceNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartDistributionTransaction
{
    public function __construct(
        private DistributionReferenceNumbers $numbers,
        private DistributionAudit $audit,
    ) {}

    public function __invoke(DistributionBatch $batch, DistributionBatchItem $item, User $actor, string $idempotencyKey): DistributionTransaction
    {
        return DB::transaction(function () use ($batch, $item, $actor, $idempotencyKey): DistributionTransaction {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $item = DistributionBatchItem::query()
                ->where('distribution_batch_id', $batch->id)
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOpen($batch, $item);

            $existingKey = DistributionTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingKey instanceof DistributionTransaction) {
                if ((int) $existingKey->distribution_batch_item_id !== (int) $item->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This release attempt is already tied to another beneficiary.',
                    ]);
                }

                return $existingKey;
            }

            $openDraft = DistributionTransaction::query()
                ->where('distribution_batch_item_id', $item->id)
                ->where('status', DistributionTransactionStatus::Draft)
                ->lockForUpdate()
                ->first();

            if ($openDraft instanceof DistributionTransaction) {
                if ((int) $openDraft->created_by !== (int) $actor->id) {
                    throw ValidationException::withMessages([
                        'transaction' => 'Another officer already has an open release for this beneficiary.',
                    ]);
                }

                return $openDraft;
            }

            $transaction = DistributionTransaction::query()->create([
                'distribution_batch_id' => $batch->id,
                'distribution_batch_item_id' => $item->id,
                'transaction_number' => $this->numbers->transactionNumber(),
                'idempotency_key' => $idempotencyKey,
                'status' => DistributionTransactionStatus::Draft,
                'verification_status' => 'pending',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->audit->record($actor, $batch, 'transaction_started', [
                'distribution_transaction_id' => $transaction->id,
                'distribution_batch_item_id' => $item->id,
                'transaction_number' => $transaction->transaction_number,
            ]);

            return $transaction;
        });
    }

    private function assertOpen(DistributionBatch $batch, DistributionBatchItem $item): void
    {
        if ($batch->status !== DistributionBatchStatus::InProgress) {
            throw ValidationException::withMessages([
                'status' => 'Start the distribution before releasing assistance.',
            ]);
        }

        if ($item->approval_status !== DistributionItemApprovalStatus::Approved) {
            throw ValidationException::withMessages([
                'distribution_batch_item_id' => 'Only an approved beneficiary in this batch can be released.',
            ]);
        }
    }
}
