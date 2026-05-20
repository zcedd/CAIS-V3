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
            $query->whereHas(
                'beneficiary',
                fn (Builder $beneficiaryQuery) => $beneficiaryQuery->where(
                    'cais_number',
                    'like',
                    '%'.$search.'%',
                ),
            );
        }

        if ($statuses !== []) {
            $query->where(function (Builder $statusQuery) use ($statuses): void {
                foreach ($statuses as $status) {
                    $statusQuery->orWhere(
                        fn (Builder $constraintQuery) => $this->applyStatusConstraint(
                            $constraintQuery,
                            $status,
                        ),
                    );
                }
            });
        }

        if ($modes !== []) {
            $query->whereHas(
                'modeOfRequest',
                fn (Builder $modeQuery) => $modeQuery->whereIn('name', $modes),
            );
        }
    }

    /**
     * @param  Builder<Assistance>  $query
     */
    private function applyStatusConstraint(Builder $query, string $status): void
    {
        match ($status) {
            'Denied' => $query
                ->whereNotNull('date_denied'),
            'Delivered' => $query
                ->whereNotNull('date_delivered')
                ->whereNull('date_denied'),
            'Verified' => $query
                ->whereNotNull('date_verified')
                ->whereNull('date_delivered')
                ->whereNull('date_denied'),
            'Pending' => $query
                ->whereNotNull('date_requested')
                ->whereNull('date_verified')
                ->whereNull('date_delivered')
                ->whereNull('date_denied'),
            'Unrequested' => $query
                ->whereNull('date_requested')
                ->whereNull('date_verified')
                ->whereNull('date_delivered')
                ->whereNull('date_denied'),
            default => null,
        };
    }
}
