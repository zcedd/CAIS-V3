<?php

namespace App\Services\User;

use App\Enums\ItemKind;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use App\Models\Program;
use App\Models\StockLot;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemService
{
    private const DEFAULT_PER_PAGE = 15;

    private const SORTABLE_COLUMNS = ['name', 'unit', 'on_hand', 'available'];

    public function paginateForDepartment(
        Department $department,
        string $search,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $sortColumn = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'name';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        $nearestExpiry = StockLot::query()
            ->select('item_id')
            ->selectRaw('min(expires_at) as nearest_expiry')
            ->join('stock_lot_balances', 'stock_lot_balances.stock_lot_id', '=', 'stock_lots.id')
            ->where('stock_lots.department_id', $department->id)
            ->where('stock_lot_balances.on_hand', '>', 0)
            ->groupBy('item_id');

        $query = Item::query()
            ->select([
                'items.id',
                'items.name',
                'items.kind',
                'items.department_id',
                'items.item_unit_measurement_id',
                'items.unspsc_code_id',
                'items.is_perishable',
                'items.low_stock_threshold',
            ])
            ->with([
                'unitMeasurement:id,name',
                'unspscCode:id,code,title',
            ])
            ->leftJoin('item_stock_balances', function ($join) use ($department): void {
                $join->on('item_stock_balances.item_id', '=', 'items.id')
                    ->where('item_stock_balances.department_id', '=', $department->id);
            })
            ->leftJoinSub($nearestExpiry, 'nearest_lots', 'nearest_lots.item_id', '=', 'items.id')
            ->addSelect([
                DB::raw('coalesce(item_stock_balances.on_hand, 0) as on_hand'),
                DB::raw('coalesce(item_stock_balances.allocated, 0) as allocated'),
                DB::raw('coalesce(item_stock_balances.available, 0) as available'),
                'nearest_lots.nearest_expiry as nearest_expiry',
            ])
            ->where('items.department_id', $department->id)
            ->when($search !== '', fn ($builder) => $builder->where('items.name', 'like', '%'.$search.'%'));

        if ($sortColumn === 'unit') {
            $query
                ->leftJoin('item_unit_measurements', 'items.item_unit_measurement_id', '=', 'item_unit_measurements.id')
                ->orderBy('item_unit_measurements.name', $sortDirection);
        } elseif ($sortColumn === 'on_hand') {
            $query->orderByRaw('coalesce(item_stock_balances.on_hand, 0) '.$sortDirection);
        } elseif ($sortColumn === 'available') {
            $query->orderByRaw('coalesce(item_stock_balances.available, 0) '.$sortDirection);
        } else {
            $query->orderBy('items.name', $sortDirection);
        }

        return $query
            ->paginate($perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE)
            ->withQueryString()
            ->through(static function (Item $item): array {
                $threshold = $item->low_stock_threshold;
                $onHand = (int) $item->getAttribute('on_hand');

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'kind' => $item->kind,
                    'item_unit_measurement_id' => $item->item_unit_measurement_id,
                    'unit' => $item->unitMeasurement?->name,
                    'unspsc_code_id' => $item->unspsc_code_id,
                    'unspsc_code' => $item->unspscCode?->code,
                    'unspsc_title' => $item->unspscCode?->title,
                    'is_perishable' => (bool) $item->is_perishable,
                    'low_stock_threshold' => $threshold,
                    'on_hand' => $onHand,
                    'allocated' => (int) $item->getAttribute('allocated'),
                    'available' => (int) $item->getAttribute('available'),
                    'nearest_expiry' => $item->getAttribute('nearest_expiry'),
                    'is_low_stock' => $item->tracksInventory()
                        && $threshold !== null
                        && $onHand <= $threshold,
                ];
            });
    }

    /**
     * @param  array{
     *     name: string,
     *     kind: string,
     *     item_unit_measurement_id: int,
     *     unspsc_code_id?: int|null,
     *     is_perishable?: bool,
     *     low_stock_threshold?: int|null
     * }  $validated
     */
    public function create(Department $department, array $validated): Item
    {
        return Item::query()->create([
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'department_id' => $department->id,
            'item_unit_measurement_id' => $validated['item_unit_measurement_id'],
            'unspsc_code_id' => $validated['unspsc_code_id'] ?? null,
            ...$this->inventoryAttributes($validated),
        ]);
    }

    /**
     * @param  array{
     *     name: string,
     *     kind: string,
     *     item_unit_measurement_id: int,
     *     unspsc_code_id?: int|null,
     *     is_perishable?: bool,
     *     low_stock_threshold?: int|null
     * }  $validated
     */
    public function update(Item $item, array $validated): void
    {
        $item->update([
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'item_unit_measurement_id' => $validated['item_unit_measurement_id'],
            'unspsc_code_id' => $validated['unspsc_code_id'] ?? null,
            ...$this->inventoryAttributes($validated),
        ]);
    }

    public function delete(Item $item): void
    {
        $isLinkedToProgram = DB::table('item_program')
            ->where('item_id', $item->id)
            ->exists();

        $isLinkedToAssistance = AssistanceItem::query()
            ->where('item_id', $item->id)
            ->exists();

        if ($isLinkedToProgram || $isLinkedToAssistance) {
            throw ValidationException::withMessages([
                'item' => ['This item cannot be deleted because it is linked to a program or assistance.'],
            ]);
        }

        $item->delete();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function unitMeasurementsForSelect(): array
    {
        return ItemUnitMeasurement::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (ItemUnitMeasurement $unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, unit: string|null, kind: string}>
     */
    public function departmentItemsForSelect(Department $department): array
    {
        return Item::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->with('unitMeasurement:id,name')
            ->get(['id', 'name', 'kind', 'item_unit_measurement_id'])
            ->map(static fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unitMeasurement?->name,
                'kind' => $item->kind,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function programsForItem(Item $item): array
    {
        return Program::query()
            ->where('department_id', $item->department_id)
            ->whereHas('item', fn ($query) => $query->where('items.id', $item->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     lots: list<array<string, mixed>>,
     *     movements: list<array<string, mixed>>
     * }
     */
    public function stockPayload(Item $item): array
    {
        $lots = StockLot::query()
            ->where('item_id', $item->id)
            ->where('department_id', $item->department_id)
            ->with('balance:id,stock_lot_id,on_hand')
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (StockLot $lot): array => [
                'id' => $lot->id,
                'batch_number' => $lot->batch_number,
                'expires_at' => $lot->expires_at?->toDateString(),
                'received_at' => $lot->received_at?->toDateTimeString(),
                'on_hand' => (int) ($lot->balance?->on_hand ?? 0),
                'is_expired' => $lot->isExpired(),
            ])
            ->values()
            ->all();

        $movements = StockMovement::query()
            ->where('item_id', $item->id)
            ->where('department_id', $item->department_id)
            ->with(['program:id,name', 'lot:id,batch_number', 'user:id,firstName,lastName'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(static fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'program_name' => $movement->program?->name,
                'batch_number' => $movement->lot?->batch_number,
                'reason' => $movement->reason,
                'user_name' => trim(($movement->user?->firstName ?? '').' '.($movement->user?->lastName ?? '')) ?: null,
                'occurred_at' => $movement->occurred_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return [
            'lots' => $lots,
            'movements' => $movements,
            'programs' => $this->programsForItem($item),
        ];
    }

    /**
     * @param  array{
     *     kind: string,
     *     is_perishable?: bool,
     *     low_stock_threshold?: int|null
     * }  $validated
     * @return array{is_perishable: bool, low_stock_threshold: int|null}
     */
    private function inventoryAttributes(array $validated): array
    {
        if (! ItemKind::from((string) $validated['kind'])->tracksInventory()) {
            return [
                'is_perishable' => false,
                'low_stock_threshold' => null,
            ];
        }

        return [
            'is_perishable' => (bool) ($validated['is_perishable'] ?? false),
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? null,
        ];
    }
}
