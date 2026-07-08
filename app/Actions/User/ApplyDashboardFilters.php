<?php

namespace App\Actions\User;

use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApplyDashboardFilters
{
    /**
     * @var array<int, list<int>>
     */
    private const QUARTER_MONTHS = [
        1 => [1, 2, 3],
        2 => [4, 5, 6],
        3 => [7, 8, 9],
        4 => [10, 11, 12],
    ];

    /**
     * @param  Builder<Model>  $query
     * @param  array{
     *     year?: list<int>,
     *     quarter?: list<string>,
     *     program?: list<int>,
     *     beneficiary_type?: list<string>,
     *     sex?: list<string>,
     *     pwd?: list<string>,
     *     four_ps?: list<string>,
     *     solo_parent?: list<string>,
     *     indigenous?: list<string>
     * }  $filters
     */
    public function __invoke(Builder $query, array $filters): void
    {
        $years = $filters['year'] ?? [];
        $quarters = array_map('intval', $filters['quarter'] ?? []);
        $programs = $filters['program'] ?? [];
        $beneficiaryTypes = $filters['beneficiary_type'] ?? [];
        $sex = $filters['sex'] ?? [];
        $pwd = $filters['pwd'] ?? [];
        $fourPs = $filters['four_ps'] ?? [];
        $soloParent = $filters['solo_parent'] ?? [];
        $indigenous = $filters['indigenous'] ?? [];

        if ($years !== [] || $quarters !== []) {
            $query->whereNotNull('assistances.date_requested');

            if ($years !== []) {
                $query->where(function (Builder $yearQuery) use ($years): void {
                    foreach ($years as $year) {
                        $yearQuery->orWhereYear('assistances.date_requested', $year);
                    }
                });
            }

            if ($quarters !== []) {
                $months = [];

                foreach ($quarters as $quarter) {
                    if (isset(self::QUARTER_MONTHS[$quarter])) {
                        $months = array_merge($months, self::QUARTER_MONTHS[$quarter]);
                    }
                }

                $months = array_values(array_unique($months));

                if ($months !== []) {
                    $query->where(function (Builder $monthQuery) use ($months): void {
                        foreach ($months as $month) {
                            $monthQuery->orWhereMonth('assistances.date_requested', $month);
                        }
                    });
                }
            }
        }

        if ($programs !== []) {
            $query->whereIn('programs.id', $programs);
        }

        if ($beneficiaryTypes !== []) {
            $morphTypes = array_map(
                static fn (string $type): string => match ($type) {
                    'individual' => Individual::class,
                    'organization' => Organization::class,
                    default => $type,
                },
                $beneficiaryTypes,
            );

            $query->whereIn('beneficiaries.beneficiable_type', $morphTypes);
        }

        $hasIndividualDemographicFilters = $sex !== []
            || $pwd !== []
            || $fourPs !== []
            || $soloParent !== []
            || $indigenous !== [];

        $organizationOnly = $beneficiaryTypes === ['organization'];

        if ($hasIndividualDemographicFilters && ! $organizationOnly) {
            if ($beneficiaryTypes === []) {
                $query->where('beneficiaries.beneficiable_type', Individual::class);
            }

            if ($sex !== []) {
                $query->whereIn('individuals.sex', $sex);
            }

            $this->applyBooleanFilter($query, 'individuals.pwd', $pwd);
            $this->applyBooleanFilter($query, 'individuals.is_4ps_beneficiary', $fourPs);
            $this->applyBooleanFilter($query, 'individuals.is_solo_parent', $soloParent);
            $this->applyBooleanFilter($query, 'individuals.indigenous', $indigenous);
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $values
     */
    private function applyBooleanFilter(Builder $query, string $column, array $values): void
    {
        if ($values === []) {
            return;
        }

        $booleans = array_map(
            static fn (string $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            $values,
        );

        $query->whereIn($column, $booleans);
    }
}
