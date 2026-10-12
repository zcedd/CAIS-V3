<?php

namespace App\Services\User;

use App\Models\Assistance;
use App\Models\Individual;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProgramAnalyticsService
{
    public function __construct(private ProgramService $programService) {}

    /**
     * @param  array{search?: string, sex?: string|null, classification?: string|null, page?: int}  $filters
     * @return array<string, mixed>
     */
    public function payload(Program $program, array $filters = []): array
    {
        $program->loadMissing('department:id,name,slug');
        $scopeIds = $this->programService->assistanceProgramIds($program);
        $metrics = $this->programService->cardMetrics(collect([$program]))[$program->id] ?? [];
        $people = $this->people($scopeIds);

        $ages = array_values(array_filter(
            array_map(fn (array $person): ?int => $person['age'], $people),
            fn (?int $age): bool => $age !== null,
        ));

        $budget = (float) ($metrics['budget'] ?? 0);
        $cashReleased = (int) ($metrics['cash_released'] ?? 0);

        return [
            'summary' => [
                'beneficiaries' => $this->beneficiaryCount($scopeIds),
                'avg_age' => $ages === [] ? null : round(array_sum($ages) / count($ages), 1),
                'age_min' => $ages === [] ? null : min($ages),
                'age_max' => $ages === [] ? null : max($ages),
                'pwd' => count(array_filter($people, fn (array $person): bool => $person['pwd'])),
                'seniors' => count(array_filter($people, fn (array $person): bool => ($person['age'] ?? 0) >= 60)),
                'budget' => $budget,
                'cash_released' => $cashReleased,
                'budget_burn' => $budget > 0 ? round(($cashReleased / $budget) * 100, 1) : null,
            ],
            'gender' => [
                'female' => count(array_filter($people, fn (array $person): bool => $person['sex'] === 'female')),
                'male' => count(array_filter($people, fn (array $person): bool => $person['sex'] === 'male')),
                'other' => count(array_filter($people, fn (array $person): bool => ! in_array($person['sex'], ['female', 'male'], true))),
            ],
            'vulnerability' => [
                ['key' => 'pwd', 'label' => 'Person with Disability', 'count' => count(array_filter($people, fn (array $person): bool => $person['pwd']))],
                ['key' => 'solo_parent', 'label' => 'Solo Parent', 'count' => count(array_filter($people, fn (array $person): bool => $person['solo_parent']))],
                ['key' => 'four_ps', 'label' => '4Ps Beneficiary', 'count' => count(array_filter($people, fn (array $person): bool => $person['four_ps']))],
                ['key' => 'indigenous', 'label' => 'Indigenous', 'count' => count(array_filter($people, fn (array $person): bool => $person['indigenous']))],
            ],
            'age_brackets' => $this->ageBrackets($people),
            'municipalities' => $this->municipalities($people),
            'roster' => $this->roster($program, $scopeIds, $filters),
        ];
    }

    /**
     * @param  list<int>  $scopeIds
     * @return list<array<string, mixed>>
     */
    private function people(array $scopeIds): array
    {
        if ($scopeIds === []) {
            return [];
        }

        return DB::table('assistances')
            ->join('beneficiaries', 'beneficiaries.id', '=', 'assistances.beneficiary_id')
            ->join('individuals', function ($join): void {
                $join->on('beneficiaries.beneficiable_id', '=', 'individuals.id')
                    ->where('beneficiaries.beneficiable_type', '=', Individual::class);
            })
            ->leftJoin('address_barangays', 'address_barangays.id', '=', 'individuals.address_barangay_id')
            ->leftJoin('address_cities', 'address_cities.id', '=', 'address_barangays.address_city_id')
            ->whereIn('assistances.program_id', $scopeIds)
            ->whereNull('assistances.deleted_at')
            ->whereNull('beneficiaries.deleted_at')
            ->whereNull('individuals.deleted_at')
            ->groupBy('individuals.id')
            ->get([
                'individuals.id',
                DB::raw('MAX(individuals.sex) as sex'),
                DB::raw('MAX(individuals.pwd) as pwd'),
                DB::raw('MAX(individuals.is_solo_parent) as is_solo_parent'),
                DB::raw('MAX(individuals.is_4ps_beneficiary) as is_4ps_beneficiary'),
                DB::raw('MAX(individuals.indigenous) as indigenous'),
                DB::raw('MAX(individuals.birthday) as birthday'),
                DB::raw('MAX(address_cities.name) as city'),
            ])
            ->map(function (object $row): array {
                $age = $row->birthday !== null ? (int) Carbon::parse($row->birthday)->age : null;

                return [
                    'sex' => strtolower(trim((string) $row->sex)),
                    'pwd' => (int) $row->pwd === 1,
                    'solo_parent' => (int) $row->is_solo_parent === 1,
                    'four_ps' => (int) $row->is_4ps_beneficiary === 1,
                    'indigenous' => (int) $row->indigenous === 1,
                    'age' => $age,
                    'city' => $row->city !== null && trim((string) $row->city) !== ''
                        ? (string) $row->city
                        : 'Unspecified',
                ];
            })
            ->all();
    }

    /**
     * @param  list<int>  $scopeIds
     */
    private function beneficiaryCount(array $scopeIds): int
    {
        if ($scopeIds === []) {
            return 0;
        }

        return (int) DB::table('assistances')
            ->whereIn('program_id', $scopeIds)
            ->whereNull('deleted_at')
            ->whereNotNull('beneficiary_id')
            ->distinct()
            ->count('beneficiary_id');
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return list<array{label: string, count: int}>
     */
    private function ageBrackets(array $people): array
    {
        $brackets = [
            'Under 18' => 0,
            '18-29' => 0,
            '30-44' => 0,
            '45-59' => 0,
            '60+' => 0,
        ];

        foreach ($people as $person) {
            $age = $person['age'];

            if (! is_int($age)) {
                continue;
            }

            $label = match (true) {
                $age < 18 => 'Under 18',
                $age <= 29 => '18-29',
                $age <= 44 => '30-44',
                $age <= 59 => '45-59',
                default => '60+',
            };
            $brackets[$label]++;
        }

        return collect($brackets)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return list<array{city: string, count: int, percent: float}>
     */
    private function municipalities(array $people): array
    {
        $total = count($people);

        return collect($people)
            ->countBy(fn (array $person): string => (string) $person['city'])
            ->map(fn (int $count, string $city): array => [
                'city' => $city,
                'count' => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $scopeIds
     * @param  array{search?: string, sex?: string|null, classification?: string|null, page?: int}  $filters
     * @return array<string, mixed>
     */
    private function roster(Program $program, array $scopeIds, array $filters): array
    {
        if ($scopeIds === []) {
            return [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 8,
                'total' => 0,
            ];
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $sex = $filters['sex'] ?? null;
        $classification = $filters['classification'] ?? null;
        $page = max(1, (int) ($filters['page'] ?? 1));

        $paginator = Assistance::query()
            ->whereIn('program_id', $scopeIds)
            ->with([
                'beneficiary.beneficiable' => function ($morphTo): void {
                    $morphTo->morphWith([
                        Individual::class => ['address.city'],
                    ]);
                },
                'currentRequestSubStatus.requestStatus',
                'program.department:id,slug',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('beneficiary', function (Builder $beneficiary) use ($search): void {
                    $beneficiary
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('cais_number', 'like', '%'.$search.'%');
                });
            })
            ->when($sex !== null, function (Builder $query) use ($sex): void {
                $query->whereHas('beneficiary', function (Builder $beneficiary) use ($sex): void {
                    $beneficiary
                        ->where('beneficiable_type', Individual::class)
                        ->whereIn('beneficiable_id', Individual::query()->where('sex', $sex)->select('id'));
                });
            })
            ->when($classification !== null, function (Builder $query) use ($classification): void {
                $query->whereHas('beneficiary', function (Builder $beneficiary) use ($classification): void {
                    $beneficiary
                        ->where('beneficiable_type', Individual::class)
                        ->whereIn(
                            'beneficiable_id',
                            $this->classifiedIndividuals($classification)->select('id'),
                        );
                });
            })
            ->orderByDesc('id')
            ->paginate(perPage: 8, page: $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (Assistance $assistance): array => $this->rosterRow($assistance))
                ->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return Builder<Individual>
     */
    private function classifiedIndividuals(string $classification): Builder
    {
        $query = Individual::query();

        return match ($classification) {
            'pwd' => $query->where('pwd', true),
            'solo_parent' => $query->where('is_solo_parent', true),
            'four_ps' => $query->where('is_4ps_beneficiary', true),
            'indigenous' => $query->where('indigenous', true),
            'senior' => $query->whereDate('birthday', '<=', now()->subYears(60)->toDateString()),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function rosterRow(Assistance $assistance): array
    {
        $beneficiary = $assistance->beneficiary;
        $individual = $beneficiary?->beneficiable instanceof Individual
            ? $beneficiary->beneficiable
            : null;
        $barangay = $individual?->address;
        $age = $individual?->birthday !== null
            ? (int) Carbon::parse($individual->birthday)->age
            : null;
        $departmentSlug = $assistance->program?->department?->slug;
        $status = $assistance->currentRequestSubStatus?->requestStatus?->name
            ?? $assistance->currentRequestSubStatus?->name;

        return [
            'assistance_id' => $assistance->id,
            'program_id' => $assistance->program_id,
            'name' => $beneficiary?->name,
            'cais_number' => $beneficiary?->cais_number,
            'sex' => $individual?->sex,
            'age' => $age,
            'city' => $barangay?->city?->name,
            'barangay' => $barangay?->name,
            'classifications' => $this->classifications($individual, $age),
            'status' => $status,
            'was_delivered' => (bool) $assistance->was_delivered,
            'receipt_url' => $assistance->was_delivered && $departmentSlug !== null
                ? route('user.assistances.receipt', [
                    'department' => $departmentSlug,
                    'program' => $assistance->program_id,
                    'assistance' => $assistance->id,
                ])
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function classifications(?Individual $individual, ?int $age): array
    {
        if (! $individual instanceof Individual) {
            return [];
        }

        $labels = [];

        if ($individual->pwd) {
            $labels[] = 'PWD';
        }

        if ($individual->is_solo_parent) {
            $labels[] = 'Solo Parent';
        }

        if ($individual->is_4ps_beneficiary) {
            $labels[] = '4Ps';
        }

        if ($individual->indigenous) {
            $labels[] = 'Indigenous';
        }

        if ($age !== null && $age >= 60) {
            $labels[] = 'Senior';
        }

        return $labels;
    }
}
