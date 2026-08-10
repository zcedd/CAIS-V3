<?php

namespace App\Actions\User;

use App\Models\Assistance;
use Illuminate\Database\Eloquent\Builder;

class ApplyAssistanceTableFilters
{
    /**
     * @param  Builder<Assistance>  $query
     * @param  list<string>  $statuses
     * @param  list<string>  $modes
     */
    public function __invoke(
        Builder $query,
        string $search,
        array $statuses,
        array $modes,
    ): void {
        if ($search !== '') {
            $needle = '%'.$search.'%';

            $query->where(
                fn (Builder $searchQuery) => $searchQuery
                    ->where('beneficiaries.name', 'like', $needle)
                    ->orWhere('beneficiaries.cais_number', 'like', $needle),
            );
        }

        if ($modes !== []) {
            $query->whereIn('mode_of_requests.name', $modes);
        }

        if ($statuses !== []) {
            $query->whereIn('rs.name', $statuses);
        }
    }
}
