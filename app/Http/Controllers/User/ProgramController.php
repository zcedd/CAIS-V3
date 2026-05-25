<?php

namespace App\Http\Controllers\User;

use App\Actions\User\ApplyAssistanceTableFilters;
use App\Actions\User\ApplyAssistanceTableSort;
use App\Actions\User\JoinAssistanceTableRelations;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProgramAssistanceTableRequest;
use App\Http\Requests\User\ProgramIndexRequest;
use App\Http\Requests\User\StoreProgramRequest;
use App\Http\Requests\User\UpdateProgramRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\RequestStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    private const PROGRAMS_PER_PAGE = 12;

    /**
     * Display programs belonging to the authenticated user's department.
     */
    public function index(ProgramIndexRequest $request, Department $department): Response
    {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);

        $search = $request->search();
        $types = $request->types();
        $statuses = $request->statuses();

        $programs = Program::query()
            ->select([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'department_id',
            ])
            ->with(['department:id,name,slug'])
            ->where('department_id', $department->id)
            ->when($search !== '', fn($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->when(
                count($types) === 1 && in_array('individual', $types, true),
                fn($query) => $query->where('is_organization', false),
            )
            ->when(
                count($types) === 1 && in_array('organization', $types, true),
                fn($query) => $query->where('is_organization', true),
            )
            ->when(
                count($statuses) === 1 && in_array('open', $statuses, true),
                fn($query) => $query->where('is_closed', false),
            )
            ->when(
                count($statuses) === 1 && in_array('closed', $statuses, true),
                fn($query) => $query->where('is_closed', true),
            )
            ->orderByDesc('id')
            ->paginate(self::PROGRAMS_PER_PAGE)
            ->withQueryString();

        return Inertia::render('user/programs/index', [
            'programs' => Inertia::scroll($programs),
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
            'type' => $types,
            'status' => $statuses,
            'funds' => $this->departmentFundsForSelect($department),
            'items' => $this->departmentItemsForSelect($department),
        ]);
    }

    /**
     * Store a newly created program for the authenticated user's department.
     */
    public function store(StoreProgramRequest $request, Department $department): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);

        $validated = $request->validated();

        $program = Program::query()->create([
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'department_id' => $department->id,
            'is_closed' => false,
            'is_organization' => $validated['is_organization'] ?? false,
        ]);

        $program->fund()->attach($validated['fund_ids']);
        $program->item()->attach($validated['item_ids']);

        return redirect()
            ->route('user.programs.index', ['department' => $department->slug])
            ->with('success', 'Program created successfully.');
    }

    /**
     * Update a program for the authenticated user's department.
     */
    public function update(
        UpdateProgramRequest $request,
        Department $department,
        Program $program,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);
        abort_unless($program->department_id === $department->id, 404);

        $validated = $request->validated();

        $program->update([
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'is_organization' => $validated['is_organization'] ?? false,
            'is_closed' => $validated['is_closed'] ?? false,
        ]);

        $program->fund()->sync($validated['fund_ids']);
        $program->item()->sync($validated['item_ids']);

        return redirect()
            ->route('user.programs.show', [
                'department' => $department->slug,
                'program' => $program->id,
            ])
            ->with('success', 'Program updated successfully.');
    }

    /**
     * Display a single program for the authenticated user's department.
     */
    public function show(
        ProgramAssistanceTableRequest $request,
        Department $department,
        Program $program,
        ApplyAssistanceTableSort $applyAssistanceTableSort,
        ApplyAssistanceTableFilters $applyAssistanceTableFilters,
        JoinAssistanceTableRelations $joinAssistanceTableRelations,
    ): Response {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);
        abort_unless($program->department_id === $department->id, 404);

        $sort = $request->sort();
        $direction = $request->direction();
        $perPage = $request->perPage();
        $search = $request->search();
        $statuses = $request->statuses();
        $modes = $request->modes();

        return Inertia::render('user/programs/show', [
            'program' => fn() => $this->programShowPayload($program),
            'department' => fn() => $department->only(['id', 'name', 'slug']),
            'funds' => fn() => $this->departmentFundsForSelect($department),
            'items' => fn() => $this->departmentItemsForSelect($department),
            'assistances' => Inertia::defer(fn() => $this->paginatedProgramAssistances(
                $program,
                $joinAssistanceTableRelations,
                $applyAssistanceTableSort,
                $applyAssistanceTableFilters,
                $sort,
                $direction,
                $perPage,
                $search,
                $statuses,
                $modes,
            )),
            'sort' => fn() => $sort,
            'direction' => fn() => $direction,
            'per_page' => fn() => $perPage,
            'search' => fn() => $search,
            'status' => fn() => $statuses,
            'mode' => fn() => $modes,
            'mode_options' => fn() => $this->programAssistanceModeOptions($program),
            'status_options' => fn() => $this->programAssistanceStatusOptions($program),
        ]);
    }

    /**
     * @return list<array{id: int, name: string, year: string}>
     */
    private function departmentFundsForSelect(Department $department): array
    {
        return Fund::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->get(['id', 'name', 'year'])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, unit: string|null}>
     */
    private function departmentItemsForSelect(Department $department): array
    {
        return Item::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->with('unitMeasurement:id,name')
            ->get(['id', 'name'])
            ->map(static fn(Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unitMeasurement?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function programShowPayload(Program $program): array
    {
        $program->loadMissing(['department:id,name,slug', 'fund:id', 'item:id']);

        return [
            ...$program->only([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'department_id',
            ]),
            'department' => $program->department?->only(['id', 'name', 'slug']),
            'start_at_input' => $this->programDateForInput($program->getRawOriginal('start_at')),
            'end_at_input' => $this->programDateForInput($program->getRawOriginal('end_at')),
            'fund_ids' => $program->fund->pluck('id')->values()->all(),
            'item_ids' => $program->item->pluck('id')->values()->all(),
        ];
    }

    private function programDateForInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function programAssistanceModeOptions(Program $program): array
    {
        return ModeOfRequest::query()
            ->whereIn(
                'id',
                Assistance::query()
                    ->where('program_id', $program->id)
                    ->whereNotNull('mode_of_request_id')
                    ->distinct()
                    ->pluck('mode_of_request_id'),
            )
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn(string $name): array => [
                'label' => $name,
                'value' => $name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function programAssistanceStatusOptions(Program $program): array
    {
        return RequestStatus::query()
            ->whereIn(
                'id',
                Assistance::query()
                    ->where('program_id', $program->id)
                    ->join(
                        'assistance_request_sub_status',
                        'assistance_request_sub_status.assistance_id',
                        '=',
                        'assistances.id',
                    )
                    ->join(
                        'request_sub_statuses',
                        'request_sub_statuses.id',
                        '=',
                        'assistance_request_sub_status.request_sub_status_id',
                    )
                    ->whereNull('assistance_request_sub_status.deleted_at')
                    ->distinct()
                    ->pluck('request_sub_statuses.request_status_id'),
            )
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn(string $name): array => [
                'label' => $name,
                'value' => $name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $modes
     */
    private function paginatedProgramAssistances(
        Program $program,
        JoinAssistanceTableRelations $joinAssistanceTableRelations,
        ApplyAssistanceTableSort $applyAssistanceTableSort,
        ApplyAssistanceTableFilters $applyAssistanceTableFilters,
        string $sort,
        string $direction,
        int $perPage,
        string $search,
        array $statuses,
        array $modes,
    ): LengthAwarePaginator {
        $assistancesQuery = Assistance::query()
            ->where('assistances.program_id', $program->id);

        $joinAssistanceTableRelations($assistancesQuery);

        $assistancesQuery->select([
            'assistances.id',
            'assistances.beneficiary_id',
            'assistances.mode_of_request_id',
            'assistances.date_requested',
            'assistances.date_verified',
            'assistances.date_delivered',
            'assistances.date_denied',
            'assistances.remark',
            'beneficiaries.cais_number as beneficiary_cais_number',
            'beneficiaries.name as beneficiary_name',
            'mode_of_requests.name as mode_of_request_name',
            'rss.name as request_sub_status_name',
            'rs.name as request_status_name',
            'arss.recorded_at as request_sub_status_recorded_at',
        ])->with([
            'assistanceItem:id,assistance_id,item_id,quantity,specification',
            'assistanceItem.item:id,name,item_unit_measurement_id',
            'assistanceItem.item.unitMeasurement:id,name',
        ]);

        $applyAssistanceTableFilters($assistancesQuery, $search, $statuses, $modes);
        $applyAssistanceTableSort($assistancesQuery, $sort, $direction);

        return $assistancesQuery
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (Assistance $assistance): array {
                $formatDate = static function ($value): ?string {
                    if ($value === null) {
                        return null;
                    }

                    return Carbon::parse($value)->toDateString();
                };

                $requestSubStatus = $assistance->request_sub_status_name;
                $requestStatus = $assistance->request_status_name;
                $requestSubStatusRecordedAt = $assistance->request_sub_status_recorded_at;

                $status = $requestSubStatus ?? match (true) {
                    $assistance->date_denied !== null => 'Denied',
                    $assistance->date_delivered !== null => 'Delivered',
                    $assistance->date_verified !== null => 'Verified',
                    $assistance->date_requested !== null => 'Pending',
                    default => 'Unrequested',
                };

                return [
                    'id' => $assistance->id,
                    'cais_number' => $assistance->beneficiary_cais_number ?? '—',
                    'beneficiary_name' => $assistance->beneficiary_name ?? '—',
                    'items' => $assistance->assistanceItem
                        ->map(static fn($assistanceItem): array => [
                            'name' => $assistanceItem->item?->name ?? '—',
                            'quantity' => $assistanceItem->quantity,
                            'unit' => $assistanceItem->item?->unitMeasurement?->name,
                            'specification' => $assistanceItem->specification,
                        ])
                        ->values()
                        ->all(),
                    'mode_of_request' => $assistance->mode_of_request_name ?? '—',
                    'date_requested' => $formatDate($assistance->date_requested),
                    'date_verified' => $formatDate($assistance->date_verified),
                    'date_delivered' => $formatDate($assistance->date_delivered),
                    'date_denied' => $formatDate($assistance->date_denied),
                    'request_status' => $requestStatus,
                    'request_sub_status' => $requestSubStatus,
                    'request_sub_status_recorded_at' => $requestSubStatusRecordedAt !== null
                        ? Carbon::parse($requestSubStatusRecordedAt)->toIso8601String()
                        : null,
                    'status' => $status,
                    'remark' => $assistance->remark,
                ];
            });
    }
}
