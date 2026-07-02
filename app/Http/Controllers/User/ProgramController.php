<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Program\IndexRequest;
use App\Http\Requests\User\Program\ShowRequest;
use App\Http\Requests\User\Program\StoreRequest;
use App\Http\Requests\User\Program\UpdateRequest;
use App\Models\Department;
use App\Models\Program;
use App\Services\User\AssistanceService;
use App\Services\User\ProgramService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function __construct(
        private ProgramService $programService,
        private AssistanceService $assistanceService,
    ) {}

    /**
     * Display programs belonging to the authenticated user's department.
     */
    public function index(IndexRequest $request, Department $department): Response
    {
        $search = $request->search();
        $types = $request->types();
        $statuses = $request->statuses();

        $programs = $this->programService->paginateForDepartment(
            $department,
            $search,
            $types,
            $statuses,
        );

        return Inertia::render('user/programs/index', [
            'programs' => Inertia::scroll($programs),
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
            'type' => $types,
            'status' => $statuses,
            'funds' => $this->programService->departmentFundsForSelect($department),
            'items' => $this->programService->departmentItemsForSelect($department),
        ]);
    }

    /**
     * Store a newly created program for the authenticated user's department.
     */
    public function store(StoreRequest $request, Department $department): RedirectResponse
    {
        $this->programService->create($department, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Program created successfully.');
    }

    /**
     * Update a program for the authenticated user's department.
     */
    public function update(
        UpdateRequest $request,
        Program $program,
    ): RedirectResponse {
        $this->programService->update($program, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Program updated successfully.');
    }

    /**
     * Display a single program for the authenticated user's department.
     */
    public function show(
        ShowRequest $request,
        Department $department,
        Program $program,
    ): Response {
        $sort = $request->sort();
        $direction = $request->direction();
        $perPage = $request->perPage();
        $search = $request->search();
        $statuses = $request->statuses();
        $modes = $request->modes();

        return Inertia::render('user/programs/show', [
            'program' => fn () => $this->programService->showPayload($program),
            'summary' => fn () => $this->programService->summary($program),
            'department' => fn () => $department->only(['id', 'name', 'slug']),
            'funds' => Inertia::defer(
                fn () => $this->programService->departmentFundsForSelect($department),
                'edit',
            ),
            'items' => Inertia::defer(
                fn () => $this->programService->departmentItemsForSelect($department),
                'edit',
            ),
            'assistances' => Inertia::defer(
                fn () => $this->assistanceService->paginatedForProgram(
                    $program,
                    $sort,
                    $direction,
                    $perPage,
                    $search,
                    $statuses,
                    $modes,
                ),
                'table',
            ),
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
            'search' => $search,
            'status' => $statuses,
            'mode' => $modes,
            'mode_options' => Inertia::defer(
                fn () => $this->assistanceService->modeOptions($program),
                'table',
            ),
            'status_options' => Inertia::defer(
                fn () => $this->assistanceService->statusOptions($program),
                'table',
            ),
            'mode_of_request_options' => Inertia::defer(
                fn () => $this->assistanceService->modesOfRequestForSelect(),
                'table',
            ),
            'program_items' => Inertia::defer(
                fn () => $this->programService->programItemsForSelect($program),
                'table',
            ),
            'request_sub_status_options' => Inertia::defer(
                fn () => $this->assistanceService->requestSubStatusesForSelect(),
                'table',
            ),
        ]);
    }
}
