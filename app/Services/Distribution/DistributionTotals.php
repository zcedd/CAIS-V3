<?php

namespace App\Services\Distribution;

use App\Enums\DistributionItemApprovalStatus;
use App\Models\DistributionBatch;
use App\Support\DecimalAmount;

class DistributionTotals
{
    public function sync(DistributionBatch $batch): DistributionBatch
    {
        $batch->load(['items.lines']);

        $proposed = '0.00';
        $approved = '0.00';
        $approvedCount = 0;

        foreach ($batch->items as $item) {
            foreach ($item->lines as $line) {
                $proposed = DecimalAmount::add($proposed, $line->proposed_amount);
            }

            if ($item->approval_status === DistributionItemApprovalStatus::Approved) {
                $approvedCount++;

                foreach ($item->lines as $line) {
                    $approved = DecimalAmount::add($approved, $line->approved_amount);
                }
            }
        }

        $batch->forceFill([
            'beneficiary_count' => $batch->items->count(),
            'approved_beneficiary_count' => $approvedCount,
            'proposed_cash_total' => $proposed,
            'approved_cash_total' => $approved,
        ])->save();

        return $batch;
    }
}
