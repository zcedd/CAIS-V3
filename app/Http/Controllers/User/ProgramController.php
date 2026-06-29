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
            'department' => fn () => $department->only(['id', 'name', 'slug']),
            'funds' => fn () => $this->programService->departmentFundsForSelect($department),
            'items' => fn () => $this->programService->departmentItemsForSelect($department),
            'assistances' => Inertia::defer(fn () => $this->assistanceService->paginatedForProgram(
                $program,
                $sort,
                $direction,
                $perPage,
                $search,
                $statuses,
                $modes,
            )),
            'sort' => fn () => $sort,
            'direction' => fn () => $direction,
            'per_page' => fn () => $perPage,
            'search' => fn () => $search,
            'status' => fn () => $statuses,
            'mode' => fn () => $modes,
            'mode_options' => fn () => $this->assistanceService->modeOptions($program),
            'status_options' => fn () => $this->assistanceService->statusOptions($program),
            'mode_of_request_options' => fn () => $this->assistanceService->modesOfRequestForSelect(),
            'program_items' => fn () => $this->programService->programItemsForSelect($program),
            'request_sub_status_options' => fn () => $this->assistanceService->requestSubStatusesForSelect(),
        ]);
    }
}
