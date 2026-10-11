<?php

namespace App\Services\Disbursement;

use App\Enums\DisbursementItemApprovalStatus;
use App\Enums\DisbursementReleaseOutcome;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchItem;
use App\Models\DisbursementReleaseRecord;
use App\Support\DecimalAmount;

class DisbursementReconciliation
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(DisbursementBatch $batch): array
    {
        $batch->load(['items.lines.item', 'items.releases', 'items.beneficiary']);

        $approvedCash = '0.00';
        $actualCash = '0.00';
        $served = 0;
        $partial = 0;
        $absent = 0;
        $excluded = 0;
        $outstanding = 0;
        $quantities = [];

        foreach ($batch->items as $item) {
            if ($item->approval_status === DisbursementItemApprovalStatus::Excluded) {
                $excluded++;

                continue;
            }

            if ($item->approval_status !== DisbursementItemApprovalStatus::Approved) {
                continue;
            }

            foreach ($item->lines as $line) {
                $approvedCash = DecimalAmount::add($approvedCash, $line->approved_amount);
                $itemId = (int) $line->item_id;
                $quantities[$itemId]['name'] = $line->item?->name ?? 'Item';
                $quantities[$itemId]['approved_quantity'] = DecimalAmount::add(
                    $quantities[$itemId]['approved_quantity'] ?? '0.00',
                    $line->approved_quantity,
                );
                $quantities[$itemId]['actual_quantity'] = $quantities[$itemId]['actual_quantity'] ?? '0.00';
            }

            $outcome = $this->outcomeFor($item);

            if ($outcome === 'absent') {
                $absent++;
            } elseif ($outcome === 'served') {
                $served++;
            } elseif ($outcome === 'partial') {
                $partial++;
            } else {
                $outstanding++;
            }

            foreach ($item->releases as $release) {
                $actualCash = DecimalAmount::add($actualCash, $release->actual_amount);

                if ($release->line?->item_id === null && $release->disbursement_batch_item_line_id !== null) {
                    $release->loadMissing('line');
                }

                $releasedItemId = (int) ($release->line?->item_id ?? 0);

                if ($releasedItemId > 0) {
                    $quantities[$releasedItemId]['name'] = $quantities[$releasedItemId]['name'] ?? 'Item';
                    $quantities[$releasedItemId]['approved_quantity'] = $quantities[$releasedItemId]['approved_quantity'] ?? '0.00';
                    $quantities[$releasedItemId]['actual_quantity'] = DecimalAmount::add(
                        $quantities[$releasedItemId]['actual_quantity'] ?? '0.00',
                        $release->actual_quantity,
                    );
                }
            }
        }

        $approvedBeneficiaries = $batch->items
            ->filter(fn (DisbursementBatchItem $item): bool => $item->approval_status === DisbursementItemApprovalStatus::Approved)
            ->count();

        return [
            'approved_beneficiaries' => $approvedBeneficiaries,
            'served_beneficiaries' => $served,
            'partial_beneficiaries' => $partial,
            'absent_beneficiaries' => $absent,
            'excluded_beneficiaries' => $excluded,
            'unserved_beneficiaries' => $outstanding,
            'approved_cash' => $approvedCash,
            'actual_cash' => $actualCash,
            'items' => array_values($quantities),
            'outstanding_count' => $outstanding,
            'ready_to_complete' => $outstanding === 0 && $approvedBeneficiaries > 0,
        ];
    }

    private function outcomeFor(DisbursementBatchItem $item): string
    {
        if ($item->releases->contains(fn (DisbursementReleaseRecord $release): bool => $release->outcome === DisbursementReleaseOutcome::Absent)) {
            return 'absent';
        }

        if ($item->releases->isEmpty()) {
            return 'outstanding';
        }

        foreach ($item->lines as $line) {
            $releasedQuantity = '0.00';
            $releasedAmount = '0.00';
            $hasDocumentedPartial = false;

            foreach ($item->releases as $release) {
                if ((int) $release->disbursement_batch_item_line_id !== (int) $line->id) {
                    continue;
                }

                $releasedQuantity = DecimalAmount::add($releasedQuantity, $release->actual_quantity);
                $releasedAmount = DecimalAmount::add($releasedAmount, $release->actual_amount);

                if ($release->outcome === DisbursementReleaseOutcome::Partial && trim((string) $release->remarks) !== '') {
                    $hasDocumentedPartial = true;
                }
            }

            $quantityMet = $line->approved_quantity === null
                || DecimalAmount::comp($releasedQuantity, $line->approved_quantity) >= 0;
            $amountMet = $line->approved_amount === null
                || DecimalAmount::comp($releasedAmount, $line->approved_amount) >= 0;

            if (! $quantityMet || ! $amountMet) {
                if (! $hasDocumentedPartial) {
                    return 'outstanding';
                }

                return 'partial';
            }
        }

        if ($item->releases->contains(fn (DisbursementReleaseRecord $release): bool => $release->outcome === DisbursementReleaseOutcome::Partial)) {
            return 'partial';
        }

        return 'served';
    }
}
