<?php

namespace App\Services\Distribution;

use App\Enums\DistributionItemApprovalStatus;
use App\Enums\DistributionReleaseOutcome;
use App\Enums\DistributionTransactionStatus;
use App\Enums\ItemKind;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;
use App\Models\DistributionBatchItemLine;
use App\Models\DistributionReleaseRecord;
use App\Models\DistributionTransaction;
use App\Support\DecimalAmount;

class DistributionReconciliation
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(DistributionBatch $batch): array
    {
        $batch->load(['items.lines.item.unitMeasurement', 'items.releases', 'items.beneficiary']);

        $served = 0;
        $partial = 0;
        $absent = 0;
        $excluded = 0;
        $outstanding = 0;
        $composition = $this->blankComposition();

        foreach ($batch->items as $item) {
            if ($item->approval_status === DistributionItemApprovalStatus::Excluded) {
                $excluded++;

                continue;
            }

            $isApproved = $item->approval_status === DistributionItemApprovalStatus::Approved;

            foreach ($item->lines as $line) {
                $this->addLine($composition, $line, $isApproved);
            }

            if (! $isApproved) {
                continue;
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
                $this->addRelease($composition, $item, $release);
            }
        }

        $this->finalizeComposition($composition);

        $approvedBeneficiaries = $batch->items
            ->filter(fn (DistributionBatchItem $item): bool => $item->approval_status === DistributionItemApprovalStatus::Approved)
            ->count();

        return [
            'approved_beneficiaries' => $approvedBeneficiaries,
            'served_beneficiaries' => $served,
            'partial_beneficiaries' => $partial,
            'absent_beneficiaries' => $absent,
            'excluded_beneficiaries' => $excluded,
            'unserved_beneficiaries' => $outstanding,
            'approved_cash' => $composition['cash']['approved_amount'],
            'actual_cash' => $composition['cash']['actual_amount'],
            'remaining_cash' => $composition['cash']['remaining_amount'],
            'voided_transactions' => DistributionTransaction::query()
                ->where('distribution_batch_id', $batch->id)
                ->where('status', DistributionTransactionStatus::Voided)
                ->count(),
            'items' => [
                ...$composition['goods']['lines'],
                ...$composition['service']['lines'],
            ],
            'composition' => $composition,
            'outstanding_count' => $outstanding,
            'ready_to_complete' => $outstanding === 0 && $approvedBeneficiaries > 0,
        ];
    }

    /**
     * Compact goods, service, and cash figures for a batch list.
     *
     * @return array{
     *     goods: list<array{name: string, unit: string|null, quantity: string}>,
     *     service: list<array{name: string, unit: string|null, quantity: string}>,
     *     cash: string
     * }
     */
    public function offer(DistributionBatch $batch): array
    {
        $batch->loadMissing(['items.lines.item.unitMeasurement']);
        $composition = $this->blankComposition();

        foreach ($batch->items as $item) {
            if ($item->approval_status === DistributionItemApprovalStatus::Excluded) {
                continue;
            }

            $isApproved = $item->approval_status === DistributionItemApprovalStatus::Approved;

            foreach ($item->lines as $line) {
                $this->addLine($composition, $line, $isApproved);
            }
        }

        $this->finalizeComposition($composition);

        return [
            'goods' => $this->offerLines($composition['goods']['lines']),
            'service' => $this->offerLines($composition['service']['lines']),
            'cash' => $this->offerAmount(
                $composition['cash']['proposed_amount'],
                $composition['cash']['approved_amount'],
            ),
        ];
    }

    /**
     * @return array{
     *     goods: array{label: string, lines: array<int, array<string, mixed>>},
     *     service: array{label: string, lines: array<int, array<string, mixed>>},
     *     cash: array{
     *         label: string,
     *         proposed_amount: string,
     *         approved_amount: string,
     *         actual_amount: string,
     *         remaining_amount: string,
     *         lines: array<int, array<string, mixed>>
     *     }
     * }
     */
    private function blankComposition(): array
    {
        return [
            'goods' => ['label' => 'Goods', 'lines' => []],
            'service' => ['label' => 'Services', 'lines' => []],
            'cash' => [
                'label' => 'Cash',
                'proposed_amount' => '0.00',
                'approved_amount' => '0.00',
                'actual_amount' => '0.00',
                'remaining_amount' => '0.00',
                'lines' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $composition
     */
    private function addLine(array &$composition, DistributionBatchItemLine $line, bool $isApproved): void
    {
        $kind = $line->item?->kind ?? ItemKind::Goods;
        $itemId = (int) $line->item_id;
        $name = $line->item?->name ?? ($kind->isCash() ? 'Cash' : 'Item');

        if ($kind->isCash()) {
            $composition['cash']['proposed_amount'] = DecimalAmount::add(
                $composition['cash']['proposed_amount'],
                $line->proposed_amount,
            );
            $this->ensureCashLine($composition, $itemId, $name);
            $composition['cash']['lines'][$itemId]['proposed_amount'] = DecimalAmount::add(
                $composition['cash']['lines'][$itemId]['proposed_amount'],
                $line->proposed_amount,
            );

            if ($isApproved) {
                $composition['cash']['approved_amount'] = DecimalAmount::add(
                    $composition['cash']['approved_amount'],
                    $line->approved_amount,
                );
                $composition['cash']['lines'][$itemId]['approved_amount'] = DecimalAmount::add(
                    $composition['cash']['lines'][$itemId]['approved_amount'],
                    $line->approved_amount,
                );
            }

            return;
        }

        $bucket = $kind === ItemKind::Service ? 'service' : 'goods';

        if (! isset($composition[$bucket]['lines'][$itemId])) {
            $composition[$bucket]['lines'][$itemId] = [
                'name' => $name,
                'unit' => $line->item?->unitMeasurement?->name,
                'proposed_quantity' => '0.00',
                'approved_quantity' => '0.00',
                'actual_quantity' => '0.00',
                'remaining_quantity' => '0.00',
            ];
        }

        $composition[$bucket]['lines'][$itemId]['proposed_quantity'] = DecimalAmount::add(
            $composition[$bucket]['lines'][$itemId]['proposed_quantity'],
            $line->proposed_quantity,
        );

        if ($isApproved) {
            $composition[$bucket]['lines'][$itemId]['approved_quantity'] = DecimalAmount::add(
                $composition[$bucket]['lines'][$itemId]['approved_quantity'],
                $line->approved_quantity,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $composition
     */
    private function ensureCashLine(array &$composition, int $itemId, string $name): void
    {
        if (isset($composition['cash']['lines'][$itemId])) {
            return;
        }

        $composition['cash']['lines'][$itemId] = [
            'name' => $name,
            'proposed_amount' => '0.00',
            'approved_amount' => '0.00',
            'actual_amount' => '0.00',
            'remaining_amount' => '0.00',
        ];
    }

    /**
     * @param  array<string, mixed>  $composition
     */
    private function addRelease(array &$composition, DistributionBatchItem $item, DistributionReleaseRecord $release): void
    {
        $releasedLine = $item->lines->firstWhere('id', $release->distribution_batch_item_line_id);
        $kind = $releasedLine?->item?->kind;
        $itemId = (int) ($releasedLine?->item_id ?? 0);

        if (! $kind instanceof ItemKind || $kind->isCash()) {
            $composition['cash']['actual_amount'] = DecimalAmount::add(
                $composition['cash']['actual_amount'],
                $release->actual_amount,
            );

            if (isset($composition['cash']['lines'][$itemId])) {
                $composition['cash']['lines'][$itemId]['actual_amount'] = DecimalAmount::add(
                    $composition['cash']['lines'][$itemId]['actual_amount'],
                    $release->actual_amount,
                );
            }

            return;
        }

        $bucket = $kind === ItemKind::Service ? 'service' : 'goods';

        if (! isset($composition[$bucket]['lines'][$itemId])) {
            return;
        }

        $composition[$bucket]['lines'][$itemId]['actual_quantity'] = DecimalAmount::add(
            $composition[$bucket]['lines'][$itemId]['actual_quantity'],
            $release->actual_quantity,
        );
    }

    /**
     * @param  array<string, mixed>  $composition
     */
    private function finalizeComposition(array &$composition): void
    {
        foreach (['goods', 'service'] as $bucket) {
            $lines = [];

            foreach ($composition[$bucket]['lines'] as $line) {
                $line['remaining_quantity'] = DecimalAmount::sub(
                    $line['approved_quantity'],
                    $line['actual_quantity'],
                );
                $lines[] = $line;
            }

            $composition[$bucket]['lines'] = $lines;
        }

        $cashLines = [];

        foreach ($composition['cash']['lines'] as $line) {
            $line['remaining_amount'] = DecimalAmount::sub(
                $line['approved_amount'],
                $line['actual_amount'],
            );
            $cashLines[] = $line;
        }

        $composition['cash']['lines'] = $cashLines;
        $composition['cash']['remaining_amount'] = DecimalAmount::sub(
            $composition['cash']['approved_amount'],
            $composition['cash']['actual_amount'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{name: string, unit: string|null, quantity: string}>
     */
    private function offerLines(array $lines): array
    {
        return array_map(function (array $line): array {
            return [
                'name' => (string) $line['name'],
                'unit' => $line['unit'],
                'quantity' => $this->offerAmount(
                    (string) $line['proposed_quantity'],
                    (string) $line['approved_quantity'],
                ),
            ];
        }, $lines);
    }

    private function offerAmount(string $proposed, string $approved): string
    {
        return DecimalAmount::isPositive($approved) ? $approved : $proposed;
    }

    private function outcomeFor(DistributionBatchItem $item): string
    {
        if ($item->releases->contains(fn (DistributionReleaseRecord $release): bool => $release->outcome === DistributionReleaseOutcome::Absent)) {
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
                if ((int) $release->distribution_batch_item_line_id !== (int) $line->id) {
                    continue;
                }

                $releasedQuantity = DecimalAmount::add($releasedQuantity, $release->actual_quantity);
                $releasedAmount = DecimalAmount::add($releasedAmount, $release->actual_amount);

                if ($release->outcome === DistributionReleaseOutcome::Partial && trim((string) $release->remarks) !== '') {
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

        if ($item->releases->contains(fn (DistributionReleaseRecord $release): bool => $release->outcome === DistributionReleaseOutcome::Partial)) {
            return 'partial';
        }

        return 'served';
    }
}
