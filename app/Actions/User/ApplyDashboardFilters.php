<?php

namespace App\Actions\User;

use App\Models\Individual;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApplyDashboardFilters
{
    /**
     * @param  Builder<Model>  $query
     * @param  array{
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
        $programs = $filters['program'] ?? [];
        $beneficiaryTypes = $filters['beneficiary_type'] ?? [];
        $sex = $filters['sex'] ?? [];
        $pwd = $filters['pwd'] ?? [];
        $fourPs = $filters['four_ps'] ?? [];
        $soloParent = $filters['solo_parent'] ?? [];
        $indigenous = $filters['indigenous'] ?? [];

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
