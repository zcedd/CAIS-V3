<?php

namespace App\Services\User;

use App\Actions\User\JoinAssistanceTableRelations;
use App\Models\AddressBarangay;
use App\Models\AddressCity;
use App\Models\AddressProvince;
use App\Models\Assistance;
use App\Models\Beneficiary;
use App\Models\CivilStatus;
use App\Models\Identification;
use App\Models\Individual;
use App\Models\Organization;
use App\Models\Program;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class BeneficiaryService
{
    private const BENEFICIARIES_PER_PAGE = 15;

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
            ->orderBy('name')
            ->paginate(self::BENEFICIARIES_PER_PAGE)
            ->withQueryString()
            ->through(static function (Beneficiary $beneficiary): array {
                return [
                    'id' => $beneficiary->id,
                    'cais_number' => $beneficiary->cais_number,
                    'name' => $beneficiary->name,
                    'type' => $beneficiary->beneficiable_type === Organization::class
                        ? 'organization'
                        : 'individual',
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        $defaultProvince = AddressProvince::query()
            ->where('name', config('address.province'))
            ->first(['id', 'name']);

        return [
            'civil_statuses' => CivilStatus::query()->orderBy('name')->get(['id', 'name']),
            'identifications' => Identification::query()->orderBy('name')->get(['id', 'name']),
            'address_provinces' => AddressProvince::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'default_province_id' => $defaultProvince?->id,
            'address_cities' => AddressCity::query()
                ->orderBy('name')
                ->get(['id', 'name', 'address_province_id']),
            'address_barangays' => AddressBarangay::query()
                ->with('city:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'address_city_id'])
                ->map(static fn (AddressBarangay $barangay): array => [
                    'id' => $barangay->id,
                    'name' => $barangay->name,
                    'address_city_id' => $barangay->address_city_id,
                    'city' => $barangay->city?->name,
                    'label' => $barangay->city
                        ? "{$barangay->name}, {$barangay->city->name}"
                        : $barangay->name,
                ])
                ->values()
                ->all(),
        ];
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
                'assistances.date_verified',
                'assistances.date_delivered',
                'assistances.date_denied',
                'programs.name as program_name',
                'departments.name as department_name',
                'departments.slug as department_slug',
                'mode_of_requests.name as mode_of_request_name',
                'rss.name as request_sub_status_name',
                'rs.name as request_status_name',
                'arss.recorded_at as request_sub_status_recorded_at',
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
                $status = $assistance->request_sub_status_name ?? match (true) {
                    $assistance->date_denied !== null => 'Denied',
                    $assistance->date_delivered !== null => 'Delivered',
                    $assistance->date_verified !== null => 'Verified',
                    $assistance->date_requested !== null => 'Pending',
                    default => 'Unrequested',
                };

                return [
                    'id' => $assistance->id,
                    'program_id' => $assistance->program_id,
                    'program_name' => $assistance->program_name ?? '—',
                    'department_name' => $assistance->department_name ?? '—',
                    'department_slug' => $assistance->department_slug,
                    'mode_of_request' => $assistance->mode_of_request_name ?? '—',
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
