<?php

namespace App\Services\User;

use App\Actions\User\JoinAssistanceTableRelations;
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
    ): LengthAwarePaginator {
        return Beneficiary::query()
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
            ->with(['beneficiable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    Individual::class => ['address.city'],
                    Organization::class => ['address.city'],
                ]);
            }])
            ->withCount('assistances')
            ->withMax('assistances as last_assisted_at', 'date_requested')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (Beneficiary $beneficiary): array {
                $beneficiable = $beneficiary->beneficiable;
                $barangay = $beneficiable?->address;

                $address = $barangay === null
                    ? null
                    : collect([$barangay->name, $barangay->city?->name])
                        ->filter(static fn (?string $part): bool => $part !== null && trim($part) !== '')
                        ->implode(', ');

                return [
                    'id' => $beneficiary->id,
                    'cais_number' => $beneficiary->cais_number,
                    'name' => $beneficiary->name,
                    'type' => $beneficiary->beneficiable_type === Organization::class
                        ? 'organization'
                        : 'individual',
                    'address' => $address !== '' ? $address : null,
                    'contact' => $beneficiable?->mobile_number ?: null,
                    'assistances_count' => (int) $beneficiary->assistances_count,
                    'last_assisted_at' => $beneficiary->last_assisted_at
                        ? Carbon::parse($beneficiary->last_assisted_at)->toDateString()
                        : null,
                    'registered_at' => $beneficiary->created_at?->toIso8601String(),
                ];
            });
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
     *     address_barangays: list<array{id: int, name: string, address_city_id: int, city: string|null, label: string}>
     * }
     */
    public function formOptions(): array
    {
        Cache::forget('beneficiary.form_options');

        return Cache::remember(
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
