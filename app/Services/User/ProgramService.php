<?php

namespace App\Services\User;

use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\Program;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ProgramService
{
    private const PROGRAMS_PER_PAGE = 12;

    /**
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     */
    public function paginateForDepartment(
        Department $department,
        string $search,
        array $types,
        array $statuses,
    ): LengthAwarePaginator {
        return Program::query()
            ->select([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'department_id',
            ])
            ->with(['department:id,name,slug'])
            ->where('department_id', $department->id)
            ->when($search !== '', fn($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->when(
                count($types) === 1 && in_array('individual', $types, true),
                fn($query) => $query->where('is_organization', false),
            )
            ->when(
                count($types) === 1 && in_array('organization', $types, true),
                fn($query) => $query->where('is_organization', true),
            )
            ->when(
                count($statuses) === 1 && in_array('open', $statuses, true),
                fn($query) => $query->where('is_closed', false),
            )
            ->when(
                count($statuses) === 1 && in_array('closed', $statuses, true),
                fn($query) => $query->where('is_closed', true),
            )
            ->orderByDesc('id')
            ->paginate(self::PROGRAMS_PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(Department $department, array $validated): Program
    {
        $program = Program::query()->create([
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'department_id' => $department->id,
            'is_closed' => false,
            'is_organization' => $validated['is_organization'] ?? false,
        ]);

        $program->fund()->attach($validated['fund_ids']);
        $program->item()->attach($validated['item_ids']);

        return $program;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Program $program, array $validated): void
    {
        $program->update([
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'is_organization' => $validated['is_organization'] ?? false,
            'is_closed' => $validated['is_closed'] ?? false,
        ]);

        $program->fund()->sync($validated['fund_ids']);
        $program->item()->sync($validated['item_ids']);
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Program $program): array
    {
        $program->loadMissing(['department:id,name,slug', 'fund:id', 'item:id']);

        return [
            ...$program->only([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'department_id',
            ]),
            'department' => $program->department?->only(['id', 'name', 'slug']),
            'start_at_input' => $this->programDateForInput($program->getRawOriginal('start_at')),
            'end_at_input' => $this->programDateForInput($program->getRawOriginal('end_at')),
            'fund_ids' => $program->fund->pluck('id')->values()->all(),
            'item_ids' => $program->item->pluck('id')->values()->all(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, year: string}>
     */
    public function departmentFundsForSelect(Department $department): array
    {
        return Fund::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->get(['id', 'name', 'year'])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, unit: string|null}>
     */
    public function departmentItemsForSelect(Department $department, ItemService $itemService): array
    {
        return $itemService->departmentItemsForSelect($department);
    }

    /**
     * @return list<array{id: int, name: string, unit: string|null}>
     */
    public function programItemsForSelect(Program $program): array
    {
        $itemIds = $program->item()->pluck('items.id');

        if ($itemIds->isEmpty()) {
            return [];
        }

        return Item::query()
            ->whereIn('id', $itemIds)
            ->orderBy('name')
            ->with('unitMeasurement:id,name')
            ->get(['id', 'name'])
            ->map(static fn(Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unitMeasurement?->name,
            ])
            ->values()
            ->all();
    }

    private function programDateForInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
