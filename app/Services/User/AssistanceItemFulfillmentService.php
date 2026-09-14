<?php

namespace App\Services\User;

use App\Models\AssistanceItem;
use App\Support\AssistanceItemOrigin;
use App\Support\EmptyCell;
use Illuminate\Support\Collection;

/**
 * Turns the raw `assistance_item` rows of a single assistance into the two facts staff care
 * about: what was requested, and what was actually released.
 */
class AssistanceItemFulfillmentService
{
    /**
     * @param  Collection<int, AssistanceItem>  $items
     * @return array{
     *     requested: list<array{
     *         item_id: int,
     *         name: string,
     *         kind: string|null,
     *         unit: string|null,
     *         specification: string|null,
     *         requested_quantity: int,
     *         released_quantity: int,
     *         substituted_quantity: int,
     *         pending_quantity: int
     *     }>,
     *     released: list<array{
     *         id: int,
     *         item_id: int,
     *         name: string,
     *         kind: string|null,
     *         unit: string|null,
     *         quantity: int,
     *         specification: string|null,
     *         origin: string,
     *         fulfillment_reason: string|null,
     *         substituted_for_name: string|null
     *     }>,
     *     variance: array{
     *         requested_quantity: int,
     *         released_quantity: int,
     *         fulfilled_quantity: int,
     *         additional_quantity: int,
     *         substitute_quantity: int,
     *         substituted_quantity: int,
     *         shortfall_quantity: int,
     *         has_variance: bool
     *     }
     * }
     */
    public function summarize(Collection $items): array
    {
        return [
            'requested' => $this->requestedLines($items),
            'released' => $this->releasedLines($items),
            'variance' => $this->variance($items),
        ];
    }

    /**
     * Requested lines are split across rows as they get released, so roll them back up per item.
     *
     * @param  Collection<int, AssistanceItem>  $items
     * @return list<array<string, mixed>>
     */
    private function requestedLines(Collection $items): array
    {
        return $this->requestedRows($items)
            ->sortBy('id')
            ->groupBy('item_id')
            ->map(function (Collection $group): array {
                /** @var AssistanceItem $first */
                $first = $group->first();

                $requestedQuantity = (int) $group->sum('requested_quantity');
                $releasedQuantity = (int) $group->where('is_received', true)->sum('quantity');
                $substitutedQuantity = (int) $group
                    ->filter(static fn (AssistanceItem $item): bool => $item->isSubstituted())
                    ->sum('requested_quantity');

                $specification = $group
                    ->pluck('specification')
                    ->map(static fn (?string $value): string => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->implode(', ');

                return [
                    'item_id' => (int) $first->item_id,
                    'name' => $first->item?->name ?? EmptyCell::VALUE,
                    'kind' => $first->item?->kind,
                    'unit' => $first->item?->unitMeasurement?->name,
                    'specification' => $specification === '' ? null : $specification,
                    'requested_quantity' => $requestedQuantity,
                    'released_quantity' => $releasedQuantity,
                    'substituted_quantity' => $substitutedQuantity,
                    'pending_quantity' => max(
                        $requestedQuantity - $releasedQuantity - $substitutedQuantity,
                        0,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AssistanceItem>  $items
     * @return list<array<string, mixed>>
     */
    private function releasedLines(Collection $items): array
    {
        return $items
            ->filter(static fn (AssistanceItem $item): bool => (bool) $item->is_received)
            ->sortBy('id')
            ->map(static fn (AssistanceItem $item): array => [
                'id' => (int) $item->id,
                'item_id' => (int) $item->item_id,
                'name' => $item->item?->name ?? EmptyCell::VALUE,
                'kind' => $item->item?->kind,
                'unit' => $item->item?->unitMeasurement?->name,
                'quantity' => (int) $item->quantity,
                'specification' => $item->specification,
                'origin' => $item->origin ?? AssistanceItemOrigin::Requested,
                'fulfillment_reason' => $item->fulfillment_reason,
                'substituted_for_name' => $item->substituted_for_assistance_item_id !== null
                    ? $items
                        ->firstWhere('id', $item->substituted_for_assistance_item_id)
                        ?->item
                        ?->name
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AssistanceItem>  $items
     * @return array<string, int|bool>
     */
    private function variance(Collection $items): array
    {
        $requestedRows = $this->requestedRows($items);

        $requestedQuantity = (int) $requestedRows->sum('requested_quantity');
        $fulfilledQuantity = (int) $requestedRows->where('is_received', true)->sum('quantity');
        $substitutedQuantity = (int) $requestedRows
            ->filter(static fn (AssistanceItem $item): bool => $item->isSubstituted())
            ->sum('requested_quantity');

        $additionalQuantity = (int) $items
            ->where('origin', AssistanceItemOrigin::Additional)
            ->where('is_received', true)
            ->sum('quantity');
        $substituteQuantity = (int) $items
            ->where('origin', AssistanceItemOrigin::Substitute)
            ->where('is_received', true)
            ->sum('quantity');

        $shortfallQuantity = max(
            $requestedQuantity - $fulfilledQuantity - $substitutedQuantity,
            0,
        );

        return [
            'requested_quantity' => $requestedQuantity,
            'released_quantity' => $fulfilledQuantity + $additionalQuantity + $substituteQuantity,
            'fulfilled_quantity' => $fulfilledQuantity,
            'additional_quantity' => $additionalQuantity,
            'substitute_quantity' => $substituteQuantity,
            'substituted_quantity' => $substitutedQuantity,
            'shortfall_quantity' => $shortfallQuantity,
            'has_variance' => $additionalQuantity > 0
                || $substituteQuantity > 0
                || $shortfallQuantity > 0,
        ];
    }

    /**
     * @param  Collection<int, AssistanceItem>  $items
     * @return Collection<int, AssistanceItem>
     */
    private function requestedRows(Collection $items): Collection
    {
        return $items->filter(static fn (AssistanceItem $item): bool => ($item->origin ?? AssistanceItemOrigin::Requested) === AssistanceItemOrigin::Requested);
    }
}
