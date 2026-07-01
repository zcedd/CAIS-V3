<?php

namespace App\Services\User;

use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUnitMeasurement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemService
{
    private const DEFAULT_PER_PAGE = 15;

    private const SORTABLE_COLUMNS = ['name', 'unit'];

    public function paginateForDepartment(
        Department $department,
        string $search,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $sortColumn = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'name';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        $query = Item::query()
            ->select([
                'items.id',
                'items.name',
                'items.department_id',
                'items.item_unit_measurement_id',
            ])
            ->with(['unitMeasurement:id,name'])
            ->where('items.department_id', $department->id)
            ->when($search !== '', fn ($builder) => $builder->where('items.name', 'like', '%'.$search.'%'));

        if ($sortColumn === 'unit') {
            $query
                ->leftJoin('item_unit_measurements', 'items.item_unit_measurement_id', '=', 'item_unit_measurements.id')
                ->orderBy('item_unit_measurements.name', $sortDirection);
        } else {
            $query->orderBy('items.name', $sortDirection);
        }

        return $query
            ->paginate($perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE)
            ->withQueryString()
            ->through(static fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'item_unit_measurement_id' => $item->item_unit_measurement_id,
                'unit' => $item->unitMeasurement?->name,
            ]);
    }

    /**
     * @param  array{name: string, item_unit_measurement_id: int}  $validated
     */
    public function create(Department $department, array $validated): Item
    {
        return Item::query()->create([
            'name' => $validated['name'],
            'department_id' => $department->id,
            'item_unit_measurement_id' => $validated['item_unit_measurement_id'],
        ]);
    }

    /**
     * @param  array{name: string, item_unit_measurement_id: int}  $validated
     */
    public function update(Item $item, array $validated): void
    {
        $item->update([
            'name' => $validated['name'],
            'item_unit_measurement_id' => $validated['item_unit_measurement_id'],
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
     * @return list<array{id: int, name: string, unit: string|null}>
     */
    public function departmentItemsForSelect(Department $department): array
    {
        return Item::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->with('unitMeasurement:id,name')
            ->get(['id', 'name'])
            ->map(static fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unitMeasurement?->name,
            ])
            ->values()
            ->all();
    }
}
