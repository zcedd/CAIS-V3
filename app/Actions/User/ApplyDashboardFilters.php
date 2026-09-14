<?php

namespace App\Actions\User;

use App\Enums\ProgramKind;
use App\Models\Individual;
use App\Models\Organization;
use App\Models\Program;
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
        $years = array_values(array_unique(array_map('intval', $filters['year'] ?? [])));
        $quarters = array_values(array_unique(array_map('intval', $filters['quarter'] ?? [])));
        $programs = $filters['program'] ?? [];
        $beneficiaryTypes = $filters['beneficiary_type'] ?? [];
        $sex = $filters['sex'] ?? [];
        $pwd = $filters['pwd'] ?? [];
        $fourPs = $filters['four_ps'] ?? [];
        $soloParent = $filters['solo_parent'] ?? [];
        $indigenous = $filters['indigenous'] ?? [];

        if ($years !== [] || $quarters !== []) {
            $query->whereNotNull('assistances.date_requested');
            $this->applyDateRequestedRanges($query, $years, $quarters);
        }

        if ($programs !== []) {
            $query->whereIn('programs.id', $this->expandProgramFilterIds($programs));
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
     * @param  list<int>  $years
     * @param  list<int>  $quarters
     */
    private function applyDateRequestedRanges(Builder $query, array $years, array $quarters): void
    {
        $months = [];

        foreach ($quarters as $quarter) {
            if (isset(self::QUARTER_MONTHS[$quarter])) {
                $months = array_merge($months, self::QUARTER_MONTHS[$quarter]);
            }
        }

        $months = array_values(array_unique($months));
        sort($months);

        if ($years === [] && $months === []) {
            return;
        }

        if ($years === []) {
            $placeholders = implode(', ', array_fill(0, count($months), '?'));
            $query->whereRaw(
                "MONTH(assistances.date_requested) IN ({$placeholders})",
                $months,
            );

            return;
        }

        $ranges = $this->dateRequestedRanges($years, $months);

        if ($ranges === []) {
            return;
        }

        $query->where(function (Builder $rangeQuery) use ($ranges): void {
            foreach ($ranges as $index => [$start, $end]) {
                if ($index === 0) {
                    $rangeQuery->whereBetween('assistances.date_requested', [$start, $end]);

                    continue;
                }

                $rangeQuery->orWhereBetween('assistances.date_requested', [$start, $end]);
            }
        });
    }

    /**
     * @param  list<int>  $years
     * @param  list<int>  $months
     * @return list<array{0: string, 1: string}>
     */
    private function dateRequestedRanges(array $years, array $months): array
    {
        sort($years);

        $ranges = [];

        foreach ($years as $year) {
            if ($months === []) {
                $ranges[] = [
                    sprintf('%04d-01-01', $year),
                    sprintf('%04d-12-31', $year),
                ];

                continue;
            }

            foreach ($this->contiguousMonthRanges($months) as [$startMonth, $endMonth]) {
                $ranges[] = [
                    sprintf('%04d-%02d-01', $year, $startMonth),
                    sprintf(
                        '%04d-%02d-%02d',
                        $year,
                        $endMonth,
                        (int) date('t', mktime(0, 0, 0, $endMonth, 1, $year)),
                    ),
                ];
            }
        }

        return $ranges;
    }

    /**
     * @param  list<int>  $months
     * @return list<array{0: int, 1: int}>
     */
    private function contiguousMonthRanges(array $months): array
    {
        if ($months === []) {
            return [];
        }

        $ranges = [];
        $start = $months[0];
        $previous = $months[0];

        for ($i = 1, $count = count($months); $i < $count; $i++) {
            $month = $months[$i];

            if ($month === $previous + 1) {
                $previous = $month;

                continue;
            }

            $ranges[] = [$start, $previous];
            $start = $month;
            $previous = $month;
        }

        $ranges[] = [$start, $previous];

        return $ranges;
    }

    /**
     * @param  list<int|string>  $programIds
     * @return list<int>
     */
    private function expandProgramFilterIds(array $programIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $programIds)));

        if ($ids === []) {
            return [];
        }

        $selected = Program::query()
            ->whereIn('id', $ids)
            ->get(['id', 'kind']);

        $schemeIds = $selected
            ->filter(static fn (Program $program): bool => $program->kind === ProgramKind::Scheme)
            ->pluck('id')
            ->all();

        $directIds = $selected
            ->reject(static fn (Program $program): bool => $program->kind === ProgramKind::Scheme)
            ->pluck('id')
            ->all();

        $batchIds = $schemeIds === []
            ? []
            : Program::query()
                ->whereIn('parent_id', $schemeIds)
                ->where('kind', ProgramKind::Batch)
                ->pluck('id')
                ->all();

        return array_values(array_unique(array_map('intval', [...$directIds, ...$batchIds])));
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
