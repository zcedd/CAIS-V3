<?php

namespace App\Http\Controllers\User;

use App\Actions\User\ApplyAssistanceTableFilters;
use App\Actions\User\ApplyAssistanceTableSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProgramAssistanceTableRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\ModeOfRequest;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    /**
     * Display programs belonging to the authenticated user's department.
     */
    public function index(Request $request, Department $department): Response
    {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = isset($validated['search']) ? trim($validated['search']) : '';

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
            ->orderByDesc('id')
            ->get();

        return Inertia::render('user/programs/index', [
            'programs' => $programs,
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
        ]);
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

                $status = match (true) {
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
        ]);
    }
}
