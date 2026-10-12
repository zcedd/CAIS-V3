<?php

namespace App\Services\User;

use App\Actions\User\JoinAssistanceTableRelations;
use App\Enums\ItemKind;
use App\Enums\NameSuffix;
use App\Models\AddressCity;
use App\Models\AddressProvince;
use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\CivilStatus;
use App\Models\Identification;
use App\Models\Individual;
use App\Models\Organization;
use App\Models\Program;
use App\Support\EmptyCell;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BeneficiaryService
{
    private const BENEFICIARIES_PER_PAGE = 25;

    private const ASSISTANCES_PER_PAGE = 10;

    public function __construct(
        private JoinAssistanceTableRelations $joinAssistanceTableRelations,
        private IndividualBeneficiaryService $individualBeneficiaryService,
        private OrganizationBeneficiaryService $organizationBeneficiaryService,
    ) {}

    /**
     * @param  list<string>  $types
     */
    public function paginate(
        string $search,
        array $types,
        int $perPage = self::BENEFICIARIES_PER_PAGE,
        ?int $cityId = null,
        ?string $sector = null,
        string $sort = 'name',
    ): LengthAwarePaginator {
        $paginator = Beneficiary::query()
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.$search.'%';
                $query->where(function ($builder) use ($needle): void {
                    $builder
                        ->where('name', 'like', $needle)
                        ->orWhere('cais_number', 'like', $needle);
                });
            })
            ->when(
                count($types) === 1 && in_array('individual', $types, true),
                fn ($query) => $query->where('beneficiable_type', Individual::class),
            )
            ->when(
                count($types) === 1 && in_array('organization', $types, true),
                fn ($query) => $query->where('beneficiable_type', Organization::class),
            )
            ->when($cityId !== null, function ($query) use ($cityId): void {
                $query->whereHasMorph(
                    'beneficiable',
                    [Individual::class, Organization::class],
                    function ($morph) use ($cityId): void {
                        $morph->whereHas(
                            'address',
                            fn ($address) => $address->where('address_city_id', $cityId),
                        );
                    },
                );
            })
            ->when($sector !== null, function ($query) use ($sector): void {
                $query
                    ->where('beneficiable_type', Individual::class)
                    ->whereIn('beneficiable_id', $this->individualsInSector($sector)->select('individuals.id'));
            })
            ->with(['beneficiable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    Individual::class => ['address.city', 'civilStatus', 'beneficiaryIdentification.identification'],
                    Organization::class => ['address.city'],
                ]);
            }])
            ->withCount('assistances')
            ->withCount([
                'assistances as delivered_count' => fn ($query) => $query->where('was_delivered', true),
            ])
            ->withMax('assistances as last_assisted_at', 'date_requested')
            ->when(
                $sort === 'newest',
                fn ($query) => $query->orderByDesc('beneficiaries.created_at'),
                fn ($query) => $sort === 'most_assisted'
                    ? $query->orderByDesc('assistances_count')->orderBy('name')
                    : $query->orderBy('name'),
            )
            ->paginate($perPage)
            ->withQueryString();

        $released = $this->releasedTotals(
            collect($paginator->items())->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        );

        return $paginator->through(function (Beneficiary $beneficiary) use ($released): array {
            $beneficiable = $beneficiary->beneficiable;
            $barangay = $beneficiable?->address;

            $address = $barangay === null
                ? null
                : collect([$barangay->name, $barangay->city?->name])
                    ->filter(static fn (?string $part): bool => $part !== null && trim($part) !== '')
                    ->implode(', ');

            $individual = $beneficiable instanceof Individual ? $beneficiable : null;
            $age = $individual?->birthday !== null
                ? (int) Carbon::parse($individual->birthday)->age
                : null;
            $identification = $individual?->beneficiaryIdentification->first();
            $totals = $released[$beneficiary->id] ?? ['cash' => 0, 'goods' => 0];

            return [
                'id' => $beneficiary->id,
                'cais_number' => $beneficiary->cais_number,
                'name' => $beneficiary->name,
                'type' => $beneficiary->beneficiable_type === Organization::class
                    ? 'organization'
                    : 'individual',
                'address' => $address !== '' ? $address : null,
                'city' => $barangay?->city?->name,
                'barangay' => $barangay?->name,
                'other_address' => $beneficiable?->other_address ?: null,
                'contact' => $beneficiable?->mobile_number ?: null,
                'age' => $age,
                'sex' => $individual?->sex,
                'civil_status' => $individual?->civilStatus?->name,
                'sectors' => $this->sectorLabels($individual, $age),
                'primary_id' => $identification === null ? null : [
                    'type' => $identification->identification?->name,
                    'number' => $identification->number,
                ],
                'assistances_count' => (int) $beneficiary->assistances_count,
                'delivered_count' => (int) $beneficiary->delivered_count,
                'cash_received' => $totals['cash'],
                'goods_received' => $totals['goods'],
                'last_assisted_at' => $beneficiary->last_assisted_at
                    ? Carbon::parse($beneficiary->last_assisted_at)->toDateString()
                    : null,
                'registered_at' => $beneficiary->created_at?->toIso8601String(),
            ];
        });
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function cityOptions(): array
    {
        $barangayIds = DB::table('individuals')
            ->whereNotNull('address_barangay_id')
            ->whereNull('deleted_at')
            ->select('address_barangay_id')
            ->union(
                DB::table('organizations')
                    ->whereNotNull('address_barangay_id')
                    ->whereNull('deleted_at')
                    ->select('address_barangay_id'),
            );

        return AddressCity::query()
            ->whereIn('id', function ($query) use ($barangayIds): void {
                $query->from('address_barangays')
                    ->whereIn('id', $barangayIds)
                    ->select('address_city_id');
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (AddressCity $city): array => [
                'value' => (string) $city->id,
                'label' => $city->name,
            ])
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     individual_id: int|null,
     *     organization_id: int|null,
     *     cais_number: string,
     *     name: string,
     *     label: string,
     *     sex: string|null,
     *     age: int|null,
     *     barangay: string|null,
     *     city: string|null,
     *     sectors: list<string>,
     * }
     */
    public function searchOption(Beneficiary $beneficiary): array
    {
        $beneficiable = $beneficiary->beneficiable;
        $individual = $beneficiable instanceof Individual ? $beneficiable : null;
        $barangay = $beneficiable?->address;
        $age = $individual?->birthday !== null
            ? (int) Carbon::parse($individual->birthday)->age
            : null;

        return [
            'id' => $beneficiary->id,
            'individual_id' => $beneficiary->beneficiable_type === Individual::class
                ? $beneficiary->beneficiable_id
                : null,
            'organization_id' => $beneficiary->beneficiable_type === Organization::class
                ? $beneficiary->beneficiable_id
                : null,
            'cais_number' => $beneficiary->cais_number,
            'name' => $beneficiary->name,
            'label' => trim("{$beneficiary->cais_number} — {$beneficiary->name}"),
            'sex' => $individual?->sex,
            'age' => $age,
            'barangay' => $barangay?->name,
            'city' => $barangay?->city?->name,
            'sectors' => $this->sectorLabels($individual, $age),
        ];
    }

    /**
     * @return Builder<Individual>
     */
    private function individualsInSector(string $sector): Builder
    {
        $query = Individual::query();

        return match ($sector) {
            'pwd' => $query->where('pwd', true),
            'solo_parent' => $query->where('is_solo_parent', true),
            'four_ps' => $query->where('is_4ps_beneficiary', true),
            'indigenous' => $query->where('indigenous', true),
            'senior' => $query->whereDate('birthday', '<=', now()->subYears(60)->toDateString()),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @return list<string>
     */
    private function sectorLabels(?Individual $individual, ?int $age): array
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

    /**
     * @param  list<int>  $beneficiaryIds
     * @return array<int, array{cash: int, goods: int}>
     */
    private function releasedTotals(array $beneficiaryIds): array
    {
        if ($beneficiaryIds === []) {
            return [];
        }

        $rows = DB::table('assistance_item as ai')
            ->join('assistances', 'assistances.id', '=', 'ai.assistance_id')
            ->join('items', 'items.id', '=', 'ai.item_id')
            ->whereIn('assistances.beneficiary_id', $beneficiaryIds)
            ->where('assistances.was_delivered', true)
            ->where('ai.is_received', true)
            ->whereNull('assistances.deleted_at')
            ->whereNull('ai.deleted_at')
            ->select('assistances.beneficiary_id', 'items.kind')
            ->selectRaw('SUM(ai.quantity) as total')
            ->groupBy('assistances.beneficiary_id', 'items.kind')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $id = (int) $row->beneficiary_id;
            $totals[$id] ??= ['cash' => 0, 'goods' => 0];

            if ($row->kind === ItemKind::Cash->value) {
                $totals[$id]['cash'] = (int) $row->total;
            }

            if ($row->kind === ItemKind::Goods->value) {
                $totals[$id]['goods'] = (int) $row->total;
            }
        }

        return $totals;
    }

    /**
     * @return array{
     *     total: int,
     *     individuals: int,
     *     organizations: int,
     *     assisted: int,
     *     new_this_month: int
     * }
     */
    public function registryStats(): array
    {
        $individualType = Individual::class;
        $organizationType = Organization::class;
        $assistanceTable = (new Assistance)->getTable();
        $startOfMonth = now()->startOfMonth()->toDateTimeString();

        $stats = Beneficiary::query()
            ->toBase()
            ->selectRaw(
                "COUNT(*) as total,
                SUM(CASE WHEN beneficiable_type = ? THEN 1 ELSE 0 END) as individuals,
                SUM(CASE WHEN beneficiable_type = ? THEN 1 ELSE 0 END) as organizations,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM {$assistanceTable} as assistances
                    WHERE assistances.beneficiary_id = beneficiaries.id
                    AND assistances.deleted_at IS NULL
                ) THEN 1 ELSE 0 END) as assisted,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_this_month",
                [$individualType, $organizationType, $startOfMonth],
            )
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'individuals' => (int) ($stats->individuals ?? 0),
            'organizations' => (int) ($stats->organizations ?? 0),
            'assisted' => (int) ($stats->assisted ?? 0),
            'new_this_month' => (int) ($stats->new_this_month ?? 0),
        ];
    }

    /**
     * @return array{
     *     civil_statuses: list<array{id: int, name: string}>,
     *     identifications: list<array{id: int, name: string}>,
     *     address_provinces: list<array{id: int, name: string}>,
     *     default_province_id: int|null,
     *     address_cities: list<array{id: int, name: string, address_province_id: int|null}>,
     *     address_barangays: list<array{id: int, name: string, address_city_id: int, city: string|null, label: string}>,
     *     suffixes: list<array{value: string, label: string}>
     * }
     */
    public function formOptions(): array
    {
        Cache::forget('beneficiary.form_options');

        $options = Cache::remember(
            'beneficiary.form_options.v2',
            now()->addDay(),
            function (): array {
                $defaultProvince = AddressProvince::query()
                    ->where('name', config('address.province'))
                    ->first(['id', 'name']);

                $barangays = DB::table('address_barangays')
                    ->leftJoin('address_cities', 'address_cities.id', '=', 'address_barangays.address_city_id')
                    ->leftJoin('address_provinces', 'address_provinces.id', '=', 'address_cities.address_province_id')
                    ->whereNull('address_barangays.deleted_at')
                    ->orderBy('address_barangays.name')
                    ->get([
                        'address_barangays.id',
                        'address_barangays.name',
                        'address_barangays.address_city_id',
                        'address_cities.name as city_name',
                        'address_provinces.name as province_name',
                    ])
                    ->map(static function (object $barangay): array {
                        $label = collect([
                            $barangay->name,
                            $barangay->city_name,
                            $barangay->province_name,
                        ])
                            ->filter(static fn (?string $part): bool => $part !== null && trim($part) !== '')
                            ->implode(', ');

                        return [
                            'id' => (int) $barangay->id,
                            'name' => (string) $barangay->name,
                            'address_city_id' => (int) $barangay->address_city_id,
                            'city' => $barangay->city_name,
                            'label' => $label !== '' ? $label : (string) $barangay->name,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'civil_statuses' => CivilStatus::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(static fn (CivilStatus $status): array => [
                            'id' => (int) $status->id,
                            'name' => (string) $status->name,
                        ])
                        ->values()
                        ->all(),
                    'identifications' => Identification::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(static fn (Identification $identification): array => [
                            'id' => (int) $identification->id,
                            'name' => (string) $identification->name,
                        ])
                        ->values()
                        ->all(),
                    'address_provinces' => AddressProvince::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(static fn (AddressProvince $province): array => [
                            'id' => (int) $province->id,
                            'name' => (string) $province->name,
                        ])
                        ->values()
                        ->all(),
                    'default_province_id' => $defaultProvince?->id,
                    'address_cities' => AddressCity::query()
                        ->orderBy('name')
                        ->get(['id', 'name', 'address_province_id'])
                        ->map(static fn (AddressCity $city): array => [
                            'id' => (int) $city->id,
                            'name' => (string) $city->name,
                            'address_province_id' => $city->address_province_id !== null
                                ? (int) $city->address_province_id
                                : null,
                        ])
                        ->values()
                        ->all(),
                    'address_barangays' => $barangays,
                ];
            },
        );

        $options['suffixes'] = NameSuffix::options();

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Beneficiary $beneficiary): array
    {
        $beneficiary->load('beneficiable');

        $type = $beneficiary->beneficiable_type === Organization::class
            ? 'organization'
            : 'individual';

        $details = $type === 'organization'
            ? $this->organizationBeneficiaryService->showDetails($beneficiary)
            : $this->individualBeneficiaryService->showDetails($beneficiary);

        $programIds = Assistance::query()
            ->where('beneficiary_id', $beneficiary->id)
            ->distinct()
            ->pluck('program_id');

        $programs = Program::query()
            ->with('department:id,name,slug')
            ->whereIn('id', $programIds)
            ->orderBy('name')
            ->get(['id', 'name', 'department_id', 'is_organization'])
            ->map(static fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
                'department' => $program->department?->only(['id', 'name', 'slug']),
                'is_organization' => (bool) $program->is_organization,
            ])
            ->values()
            ->all();

        return [
            'id' => $beneficiary->id,
            'cais_number' => $beneficiary->cais_number,
            'name' => $beneficiary->name,
            'type' => $type,
            'details' => $details,
            'programs' => $programs,
            'assistances_count' => Assistance::query()
                ->where('beneficiary_id', $beneficiary->id)
                ->count(),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     delivered: int,
     *     denied: int,
     *     in_progress: int,
     *     programs: int,
     *     last_requested_at: string|null
     * }
     */
    public function assistanceSummary(Beneficiary $beneficiary): array
    {
        $base = Assistance::query()->where('beneficiary_id', $beneficiary->id);

        $total = (clone $base)->count();
        $delivered = (clone $base)->where('was_delivered', true)->count();
        $denied = (clone $base)
            ->whereHas('currentRequestSubStatus.requestStatus', static function ($query): void {
                $query->where(function ($inner): void {
                    $inner
                        ->where('code', 'denied')
                        ->orWhere('name', 'Denied');
                });
            })
            ->count();
        $lastRequested = (clone $base)->max('date_requested');

        return [
            'total' => $total,
            'delivered' => $delivered,
            'denied' => $denied,
            'in_progress' => max(0, $total - $delivered - $denied),
            'programs' => (clone $base)->distinct()->count('program_id'),
            'last_requested_at' => $lastRequested
                ? Carbon::parse($lastRequested)->toDateString()
                : null,
        ];
    }

    public function paginatedAssistances(Beneficiary $beneficiary, string $search): LengthAwarePaginator
    {
        $assistancesQuery = Assistance::query()
            ->where('assistances.beneficiary_id', $beneficiary->id);

        ($this->joinAssistanceTableRelations)($assistancesQuery);

        $assistancesQuery
            ->join('programs', 'programs.id', '=', 'assistances.program_id')
            ->join('departments', 'departments.id', '=', 'programs.department_id')
            ->select([
                'assistances.id',
                'assistances.program_id',
                'assistances.date_requested',
                'assistances.date_delivered',
                'programs.name as program_name',
                'departments.name as department_name',
                'departments.slug as department_slug',
                'mode_of_requests.name as mode_of_request_name',
                'rss.name as request_sub_status_name',
                'rs.name as request_status_name',
                'assistances.current_status_recorded_at as request_sub_status_recorded_at',
            ]);

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $assistancesQuery->where(function ($query) use ($needle): void {
                $query
                    ->where('programs.name', 'like', $needle)
                    ->orWhere('departments.name', 'like', $needle);
            });
        }

        return $assistancesQuery
            ->orderByDesc('assistances.date_requested')
            ->paginate(self::ASSISTANCES_PER_PAGE)
            ->withQueryString()
            ->through(static function (Assistance $assistance): array {
                $status = $assistance->request_sub_status_name
                    ?? $assistance->request_status_name
                    ?? 'Unrequested';

                return [
                    'id' => $assistance->id,
                    'program_id' => $assistance->program_id,
                    'program_name' => $assistance->program_name ?? EmptyCell::VALUE,
                    'department_name' => $assistance->department_name ?? EmptyCell::VALUE,
                    'department_slug' => $assistance->department_slug,
                    'mode_of_request' => $assistance->mode_of_request_name ?? EmptyCell::VALUE,
                    'date_requested' => $assistance->date_requested
                        ? Carbon::parse($assistance->date_requested)->toDateString()
                        : null,
                    'status' => $status,
                    'request_status' => $assistance->request_status_name,
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function editPayload(Beneficiary $beneficiary): array
    {
        $beneficiary->load('beneficiable');

        if ($beneficiary->beneficiable_type === Individual::class) {
            return $this->individualBeneficiaryService->editPayload($beneficiary);
        }

        return $this->organizationBeneficiaryService->editPayload($beneficiary);
    }
}
