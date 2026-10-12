<?php

namespace App\Actions\Distribution;

use App\Enums\AssistanceItemOrigin;
use App\Enums\ItemKind;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\DistributionBatchItemLine;
use App\Models\Program;
use App\Models\User;
use App\Services\User\StockLedgerService;
use App\Support\DecimalAmount;
use Illuminate\Validation\ValidationException;

class ApplyDistributionReleaseToAssistance
{
    public function __construct(
        private StockLedgerService $stockLedger,
        private RecordDistributionAssistanceStatus $assistanceStatus,
    ) {}

    public function apply(DistributionBatchItemLine $line, string $actual, User $user): AssistanceItem
    {
        if (! DecimalAmount::isWhole($actual) || ! DecimalAmount::isPositive($actual)) {
            throw ValidationException::withMessages([
                'actual' => 'Released quantities and cash amounts must be whole numbers greater than zero.',
            ]);
        }

        $line->loadMissing(['item', 'batchItem.assistance.program']);
        $assistance = $line->batchItem?->assistance;
        $program = $assistance?->program;

        if (! $assistance instanceof Assistance || ! $program instanceof Program) {
            throw ValidationException::withMessages([
                'line' => 'The assistance request for this release could not be found.',
            ]);
        }

        $source = AssistanceItem::query()
            ->where('assistance_id', $assistance->id)
            ->whereKey($line->assistance_item_id)
            ->lockForUpdate()
            ->first();

        if (! $source instanceof AssistanceItem || $source->is_received) {
            throw ValidationException::withMessages([
                'line' => 'That assistance line has already been released.',
            ]);
        }

        $quantity = DecimalAmount::toInt($actual);
        $outstanding = (int) ($source->quantity ?? 0);

        if ($quantity > $outstanding) {
            throw ValidationException::withMessages([
                'actual' => 'The release is larger than the outstanding assistance line.',
            ]);
        }

        $released = $this->split($assistance, $source, $quantity);
        $kind = $line->item?->kind;

        if ($kind instanceof ItemKind && $kind->tracksInventory()) {
            $this->stockLedger->issue($released, $program, $user);
        }

        $this->assistanceStatus->recordRelease($assistance->refresh(), $user);

        return $released;
    }

    private function split(Assistance $assistance, AssistanceItem $assistanceItem, int $releasedQuantity): AssistanceItem
    {
        $outstandingQuantity = (int) ($assistanceItem->quantity ?? 0);
        $remainingQuantity = $outstandingQuantity - $releasedQuantity;

        if ($remainingQuantity > 0) {
            $assistanceItem->update([
                'quantity' => $remainingQuantity,
                'requested_quantity' => $remainingQuantity,
            ]);

            return AssistanceItem::query()->create([
                'assistance_id' => $assistance->id,
                'item_id' => $assistanceItem->item_id,
                'origin' => $assistanceItem->origin ?? AssistanceItemOrigin::Requested,
                'quantity' => $releasedQuantity,
                'requested_quantity' => $releasedQuantity,
                'specification' => $assistanceItem->specification,
                'is_received' => true,
            ]);
        }

        $assistanceItem->update([
            'quantity' => $releasedQuantity,
            'requested_quantity' => $releasedQuantity,
            'is_received' => true,
        ]);

        return $assistanceItem->refresh();
    }
}
