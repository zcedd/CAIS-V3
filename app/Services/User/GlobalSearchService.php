<?php

namespace App\Services\User;

use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class GlobalSearchService
{
    private const DEFAULT_LIMIT = 5;

    private const SCOPED_LIMIT = 10;

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function search(User $user, Department $department, string $query, string $scope = 'all'): array
    {
        $needle = $this->like($query);
        $groups = [];

        if ($this->includes($scope, 'programs') && Gate::forUser($user)->allows('viewAny', [Program::class, $department])) {
            $groups['programs'] = $this->programs($department, $needle, $this->limit($scope, 'programs'));
        }

        if ($this->includes($scope, 'beneficiaries') && Gate::forUser($user)->allows('viewAny', [Beneficiary::class, $department])) {
            $groups['beneficiaries'] = $this->beneficiaries($department, $needle, $this->limit($scope, 'beneficiaries'));
        }

        if ($this->includes($scope, 'requests') && Gate::forUser($user)->allows('viewAny', [Assistance::class, $department])) {
            $groups['requests'] = $this->requests($department, $query, $needle, $this->limit($scope, 'requests'));
        }

        if ($this->includes($scope, 'items') && Gate::forUser($user)->allows('viewAny', [Item::class, $department])) {
            $groups['items'] = $this->items($department, $needle, $this->limit($scope, 'items'));
        }

        if ($this->includes($scope, 'funds') && Gate::forUser($user)->allows('viewAny', [Fund::class, $department])) {
            $groups['funds'] = $this->funds($department, $needle, $this->limit($scope, 'funds'));
        }

        return $groups;
    }

    /**
     * @return list<array{id: int, name: string, parent_name: string|null, is_open: bool, approval_status: string|null, url: string}>
     */
    private function programs(Department $department, string $needle, int $limit): array
    {
        return Program::query()
            ->where('department_id', $department->id)
            ->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->where('name', 'like', $needle)
                    ->orWhere('batch_name', 'like', $needle);
            })
            ->with('parent:id,name')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
                'parent_name' => $program->parent?->name,
                'is_open' => ! $program->is_closed,
                'approval_status' => $program->approval_status?->value,
                'url' => route('user.programs.show', [
                    'department' => $department->slug,
                    'program' => $program->id,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, cais_number: string|null, type: string, barangay: string|null, city: string|null, url: string}>
     */
    private function beneficiaries(Department $department, string $needle, int $limit): array
    {
        return Beneficiary::query()
            ->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->where('name', 'like', $needle)
                    ->orWhere('cais_number', 'like', $needle);
            })
            ->with(['beneficiable.address.city'])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function (Beneficiary $beneficiary) use ($department): array {
                $address = $beneficiary->beneficiable?->address;

                return [
                    'id' => $beneficiary->id,
                    'name' => $beneficiary->name,
                    'cais_number' => $beneficiary->cais_number,
                    'type' => $beneficiary->beneficiable_type === Organization::class
                        ? 'organization'
                        : 'individual',
                    'barangay' => $address?->name,
                    'city' => $address?->city?->name,
                    'url' => route('user.beneficiaries.show', [
                        'department' => $department->slug,
                        'beneficiary' => $beneficiary->id,
                    ]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, beneficiary_name: string|null, cais_number: string|null, program_name: string|null, status: string|null, date_requested: string|null, url: string}>
     */
    private function requests(Department $department, string $query, string $needle, int $limit): array
    {
        $exactId = ctype_digit($query) ? (int) $query : null;

        return Assistance::query()
            ->whereHas('program', function (Builder $program) use ($department): void {
                $program->where('department_id', $department->id);
            })
            ->where(function (Builder $builder) use ($needle, $exactId): void {
                $builder->whereHas('beneficiary', function (Builder $beneficiary) use ($needle): void {
                    $beneficiary
                        ->where('name', 'like', $needle)
                        ->orWhere('cais_number', 'like', $needle);
                });

                if ($exactId !== null) {
                    $builder->orWhere('id', $exactId);
                }
            })
            ->with([
                'beneficiary:id,name,cais_number',
                'program:id,name,department_id',
                'currentRequestSubStatus.requestStatus:id,name',
            ])
            ->latest('date_requested')
            ->limit($limit)
            ->get()
            ->map(fn (Assistance $assistance): array => [
                'id' => $assistance->id,
                'beneficiary_name' => $assistance->beneficiary?->name,
                'cais_number' => $assistance->beneficiary?->cais_number,
                'program_name' => $assistance->program?->name,
                'status' => $assistance->currentRequestSubStatus?->requestStatus?->name,
                'date_requested' => $assistance->date_requested !== null
                    ? Carbon::parse($assistance->date_requested)->toDateString()
                    : null,
                'url' => route('user.assistances.show', [
                    'department' => $department->slug,
                    'program' => $assistance->program_id,
                    'assistance' => $assistance->id,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, url: string}>
     */
    private function items(Department $department, string $needle, int $limit): array
    {
        return Item::query()
            ->where('department_id', $department->id)
            ->where('name', 'like', $needle)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'url' => route('user.items.index', [
                    'department' => $department->slug,
                    'search' => $item->name,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, url: string}>
     */
    private function funds(Department $department, string $needle, int $limit): array
    {
        return Fund::query()
            ->where('department_id', $department->id)
            ->where('name', 'like', $needle)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn (Fund $fund): array => [
                'id' => $fund->id,
                'name' => $fund->name,
                'url' => route('user.funds.index', [
                    'department' => $department->slug,
                    'search' => $fund->name,
                ]),
            ])
            ->values()
            ->all();
    }

    private function includes(string $scope, string $group): bool
    {
        return $scope === 'all' || $scope === $group;
    }

    private function limit(string $scope, string $group): int
    {
        return $scope === $group ? self::SCOPED_LIMIT : self::DEFAULT_LIMIT;
    }

    private function like(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%'.$escaped.'%';
    }
}
