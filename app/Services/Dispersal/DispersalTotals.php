<?php

namespace App\Services\Dispersal;

use App\Enums\DispersalItemApprovalStatus;
use App\Models\DispersalBatch;
use App\Support\DecimalAmount;

class DispersalTotals
{
    public function sync(DispersalBatch $batch): DispersalBatch
    {
        $batch->load(['items.lines']);

        $proposed = '0.00';
        $approved = '0.00';
        $approvedCount = 0;

        foreach ($batch->items as $item) {
            foreach ($item->lines as $line) {
                $proposed = DecimalAmount::add($proposed, $line->proposed_amount);
            }

            if ($item->approval_status === DispersalItemApprovalStatus::Approved) {
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
