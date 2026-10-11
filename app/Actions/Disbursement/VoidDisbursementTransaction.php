<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementReceiptStatus;
use App\Enums\DisbursementTransactionStatus;
use App\Models\DisbursementBatch;
use App\Models\DisbursementTransaction;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidDisbursementTransaction
{
    public function __construct(private DisbursementAudit $audit) {}

    public function __invoke(DisbursementBatch $batch, DisbursementTransaction $transaction, User $actor, string $reason): DisbursementTransaction
    {
        return DB::transaction(function () use ($batch, $transaction, $actor, $reason): DisbursementTransaction {
            $transaction = DisbursementTransaction::query()
                ->where('disbursement_batch_id', $batch->id)
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status !== DisbursementTransactionStatus::Finalized) {
                throw ValidationException::withMessages([
                    'status' => 'Only a finalized release can be voided.',
                ]);
            }

            if (trim($reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Record why this release is being voided.',
                ]);
            }

            $transaction->status = DisbursementTransactionStatus::Voided;
            $transaction->voided_at = now();
            $transaction->void_reason = $reason;
            $transaction->updated_by = $actor->id;
            $transaction->save();

            $transaction->receipts()
                ->where('status', DisbursementReceiptStatus::Issued)
                ->update([
                    'status' => DisbursementReceiptStatus::Voided->value,
                    'notes' => $reason,
                ]);

            $this->audit->record($actor, $batch, 'transaction_voided', [
                'disbursement_transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'reason' => $reason,
            ]);

            return $transaction->refresh();
        });
    }
}
