<?php

namespace App\Services\Disbursement;

use App\Models\DisbursementAcknowledgmentReceipt;
use App\Models\DisbursementTransaction;
use Illuminate\Support\Str;

class DisbursementReferenceNumbers
{
    public function transactionNumber(): string
    {
        return $this->next(DisbursementTransaction::class, 'transaction_number', 'DST');
    }

    public function receiptNumber(): string
    {
        return $this->next(DisbursementAcknowledgmentReceipt::class, 'receipt_number', 'AR');
    }

    public function verificationToken(): string
    {
        return Str::random(48);
    }

    /**
     * @param  class-string<DisbursementTransaction|DisbursementAcknowledgmentReceipt>  $model
     */
    private function next(string $model, string $column, string $prefix): string
    {
        $stem = $prefix.'-'.now()->format('Y').'-';
        $latest = $model::query()
            ->where($column, 'like', $stem.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value($column);
        $sequence = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $stem.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
