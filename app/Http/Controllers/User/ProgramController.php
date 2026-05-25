<?php

namespace App\Http\Controllers\User;

use App\Actions\User\ApplyAssistanceTableFilters;
use App\Actions\User\ApplyAssistanceTableSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProgramAssistanceTableRequest;
use App\Http\Requests\User\ProgramIndexRequest;
use App\Http\Requests\User\StoreProgramRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\ModeOfRequest;
use App\Models\Program;
use App\Models\RequestStatus;
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
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when(
                count($types) === 1 && in_array('individual', $types, true),
                fn ($query) => $query->where('is_organization', false),
            )
            ->when(
                count($types) === 1 && in_array('organization', $types, true),
                fn ($query) => $query->where('is_organization', true),
            )
            ->when(
                count($statuses) === 1 && in_array('open', $statuses, true),
                fn ($query) => $query->where('is_closed', false),
            )
            ->when(
                count($statuses) === 1 && in_array('closed', $statuses, true),
                fn ($query) => $query->where('is_closed', true),
            )
            ->orderByDesc('id')
            ->paginate(self::PROGRAMS_PER_PAGE)
            ->withQueryString();

        $funds = Fund::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->get(['id', 'name', 'year']);

        $items = Item::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->with('unitMeasurement:id,name')
            ->get(['id', 'name']);

        $items = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unitMeasurement?->name,
            ];
        });

        return Inertia::render('user/programs/index', [
            'programs' => Inertia::scroll($programs),
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
            'type' => $types,
            'status' => $statuses,
            'funds' => $funds,
            'items' => $items,
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
     * Display a single program for the authenticated user's department.
     */
    public function show(
        ProgramAssistanceTableRequest $request,
        Department $department,
        Program $program,
        ApplyAssistanceTableSort $applyAssistanceTableSort,
        ApplyAssistanceTableFilters $applyAssistanceTableFilters,
    ): Response {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);
        abort_unless($program->department_id === $department->id, 404);

        $program->loadMissing(['department:id,name,slug']);

        $sort = $request->sort();
        $direction = $request->direction();
        $perPage = $request->perPage();
        $search = $request->search();
        $statuses = $request->statuses();
        $modes = $request->modes();

        $modeOptions = ModeOfRequest::query()
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
            ->map(static fn (string $name): array => [
                'label' => $name,
                'value' => $name,
            ])
            ->values()
            ->all();

        $statusOptions = RequestStatus::query()
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
            ->map(static fn (string $name): array => [
                'label' => $name,
                'value' => $name,
            ])
            ->values()
            ->all();

        $assistancesQuery = Assistance::query()
            ->where('program_id', $program->id)
            ->select([
                'id',
                'beneficiary_id',
                'organization_id',
                'mode_of_request_id',
                'date_requested',
                'date_verified',
                'date_delivered',
                'date_denied',
                'remark',
            ])
            ->with([
                'beneficiary:id,cais_number',
                'organization:id,name',
                'modeOfRequest:id,name',
                'assistanceItem:id,assistance_id,item_id,quantity,specification',
                'assistanceItem.item:id,name,item_unit_measurement_id',
                'assistanceItem.item.unitMeasurement:id,name',
                'latestAssistanceRequestSubStatus.requestSubStatus:id,name,request_status_id',
                'latestAssistanceRequestSubStatus.requestSubStatus.requestStatus:id,name',
            ]);

        $applyAssistanceTableFilters($assistancesQuery, $search, $statuses, $modes);
        $applyAssistanceTableSort($assistancesQuery, $sort, $direction);

        $assistances = $assistancesQuery
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (Assistance $assistance): array {
                $formatDate = static function ($value): ?string {
                    if ($value === null) {
                        return null;
                    }

                    return Carbon::parse($value)->toDateString();
                };

                $latestSubStatus = $assistance
                    ->latestAssistanceRequestSubStatus
                    ?->requestSubStatus;
                $requestSubStatus = $latestSubStatus?->name;
                $requestStatus = $latestSubStatus?->requestStatus?->name;

                $status = $requestSubStatus ?? match (true) {
                    $assistance->date_denied !== null => 'Denied',
                    $assistance->date_delivered !== null => 'Delivered',
                    $assistance->date_verified !== null => 'Verified',
                    $assistance->date_requested !== null => 'Pending',
                    default => 'Unrequested',
                };

                return [
                    'id' => $assistance->id,
                    'cais_number' => $assistance->beneficiary?->cais_number
                        ?? '—',
                    'items' => $assistance->assistanceItem
                        ->map(static fn ($assistanceItem): array => [
                            'name' => $assistanceItem->item?->name ?? '—',
                            'quantity' => $assistanceItem->quantity,
                            'unit' => $assistanceItem->item?->unitMeasurement?->name,
                            'specification' => $assistanceItem->specification,
                        ])
                        ->values()
                        ->all(),
                    'mode_of_request' => $assistance->modeOfRequest?->name ?? '—',
                    'date_requested' => $formatDate($assistance->date_requested),
                    'date_verified' => $formatDate($assistance->date_verified),
                    'date_delivered' => $formatDate($assistance->date_delivered),
                    'date_denied' => $formatDate($assistance->date_denied),
                    'request_status' => $requestStatus,
                    'request_sub_status' => $requestSubStatus,
                    'status' => $status,
                    'remark' => $assistance->remark,
                ];
            });

        return Inertia::render('user/programs/show', [
            'program' => [
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
            ],
            'department' => $department->only(['id', 'name', 'slug']),
            'assistances' => $assistances,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
            'search' => $search,
            'status' => $statuses,
            'mode' => $modes,
            'mode_options' => $modeOptions,
            'status_options' => $statusOptions,
        ]);
    }
}
