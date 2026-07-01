<?php

namespace App\Services\User;

use App\Models\Department;
use App\Models\Fund;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class FundService
{
    private const FUNDS_PER_PAGE = 12;

    /**
     * @param  list<string>  $statuses
     */
    public function paginateForDepartment(
        Department $department,
        string $search,
        array $statuses,
    ): LengthAwarePaginator {
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
            ->orderBy('name')
            ->paginate(self::FUNDS_PER_PAGE)
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
