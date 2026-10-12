<?php

namespace App\Services\Distribution;

use App\Models\DistributionAcknowledgmentReceipt;
use App\Models\DistributionTransaction;
use Illuminate\Support\Str;

class DistributionReferenceNumbers
{
    public function transactionNumber(): string
    {
        return $this->next(DistributionTransaction::class, 'transaction_number', 'DST');
    }

    public function receiptNumber(): string
    {
        return $this->next(DistributionAcknowledgmentReceipt::class, 'receipt_number', 'AR');
    }

    public function verificationToken(): string
    {
        return Str::random(48);
    }

    /**
     * @param  class-string<DistributionTransaction|DistributionAcknowledgmentReceipt>  $model
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
