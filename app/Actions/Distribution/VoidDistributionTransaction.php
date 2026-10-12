<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionReceiptStatus;
use App\Enums\DistributionTransactionStatus;
use App\Models\DistributionBatch;
use App\Models\DistributionTransaction;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidDistributionTransaction
{
    public function __construct(private DistributionAudit $audit) {}

    public function __invoke(DistributionBatch $batch, DistributionTransaction $transaction, User $actor, string $reason): DistributionTransaction
    {
        return DB::transaction(function () use ($batch, $transaction, $actor, $reason): DistributionTransaction {
            $transaction = DistributionTransaction::query()
                ->where('distribution_batch_id', $batch->id)
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status !== DistributionTransactionStatus::Finalized) {
                throw ValidationException::withMessages([
                    'status' => 'Only a finalized release can be voided.',
                ]);
            }

            if (trim($reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Record why this release is being voided.',
                ]);
            }

            $transaction->status = DistributionTransactionStatus::Voided;
            $transaction->voided_at = now();
            $transaction->void_reason = $reason;
            $transaction->updated_by = $actor->id;
            $transaction->save();

            $transaction->receipts()
                ->where('status', DistributionReceiptStatus::Issued)
                ->update([
                    'status' => DistributionReceiptStatus::Voided->value,
                    'notes' => $reason,
                ]);

            $this->audit->record($actor, $batch, 'transaction_voided', [
                'distribution_transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'reason' => $reason,
            ]);

            return $transaction->refresh();
        });
    }
}
