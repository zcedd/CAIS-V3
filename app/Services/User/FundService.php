<?php

namespace App\Services\User;

use App\Models\Department;
use App\Models\Fund;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class FundService
{
    private const DEFAULT_PER_PAGE = 15;

    /** @var list<string> */
    private const SORTABLE_COLUMNS = ['name', 'amount', 'year', 'is_active'];

    /**
     * @param  list<string>  $statuses
     */
    public function paginateForDepartment(
        Department $department,
        string $search,
        array $statuses,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $sortColumn = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'name';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        return Fund::query()
            ->select([
                'id',
                'name',
                'amount',
                'year',
                'is_active',
                'department_id',
            ])
            ->where('department_id', $department->id)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when(
                count($statuses) === 1 && in_array('active', $statuses, true),
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                count($statuses) === 1 && in_array('inactive', $statuses, true),
                fn ($query) => $query->where('is_active', false),
            )
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(Department $department, array $validated): Fund
    {
        return Fund::query()->create([
            'name' => $validated['name'],
            'amount' => $validated['amount'] ?? null,
            'year' => $validated['year'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'department_id' => $department->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Fund $fund, array $validated): Fund
    {
        $fund->update([
            'name' => $validated['name'],
            'amount' => $validated['amount'] ?? null,
            'year' => $validated['year'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return $fund;
    }

    public function delete(Fund $fund): void
    {
        if ($fund->programs()->exists()) {
            throw ValidationException::withMessages([
                'fund' => 'This fund cannot be deleted because it is linked to one or more programs.',
            ]);
        }

        $fund->delete();
    }
}
